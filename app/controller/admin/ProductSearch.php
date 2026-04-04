<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\ProductStyleItem as ItemModel;
use app\service\AliyunImageSearchConfig;
use app\service\ProductStyleAliyunQueueService;
use app\service\ProductStyleEmbeddingService;
use app\service\VisionOpenAIConfig;
use app\service\VisionSearchService;
use think\facade\Db;
use app\service\ProductStyleImportService;
use app\service\ProductStyleXlsxImportService;
use think\facade\Log;
use think\facade\View;

/**
 * 图片搜款式：索引导入与后台列表
 */
class ProductSearch extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null)
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }

    /**
     * Excel/大批量导入逐行调 Python，默认 PHP/Nginx 易超时或内存不足导致 502；在允许时放宽执行时间与内存。
     */
    private function prepareLongRunningImport(): void
    {
        if (\function_exists('set_time_limit')) {
            @\set_time_limit(0);
        }
        @\ini_set('max_execution_time', '0');
        $cur = (string) \ini_get('memory_limit');
        if ($cur !== '-1' && $cur !== '') {
            @\ini_set('memory_limit', '512M');
        }
    }

    /**
     * 按 product_code 插入或更新：同一编号重复导入不新增行，仅更新参考图、爆款类型、向量与可选 ai_description。
     *
     * @return array{inserted:bool, updated:bool}
     */
    private function upsertStyleItem(string $code, string $imageRef, string $hotType, array $embeddingVec, ?string $aiDescription = null): array
    {
        $code = trim($code);
        $embJson = json_encode($embeddingVec, JSON_UNESCAPED_UNICODE);
        $row = ItemModel::where('product_code', $code)->find();
        if ($row) {
            $data = [
                'image_ref' => $imageRef,
                'hot_type' => $hotType,
                'embedding' => $embJson,
                'status' => 1,
            ];
            if ($aiDescription !== null && $aiDescription !== '') {
                $data['ai_description'] = $aiDescription;
            }
            $row->save($data);

            return ['inserted' => false, 'updated' => true];
        }

        ItemModel::create([
            'product_code' => $code,
            'image_ref' => $imageRef,
            'hot_type' => $hotType,
            'ai_description' => $aiDescription ?? '',
            'embedding' => $embJson,
            'status' => 1,
        ]);

        return ['inserted' => true, 'updated' => false];
    }

    private function syncProductAiDescription(string $productCode, ?string $desc): void
    {
        if ($desc === null || $desc === '') {
            return;
        }
        try {
            Db::name('products')->where('name', $productCode)->update(['ai_description' => $desc]);
        } catch (\Throwable $e) {
            Log::warning('syncProductAiDescription: ' . $e->getMessage());
        }
    }

    public function index()
    {
        return View::fetch('admin/product_search/index', []);
    }

    public function listJson()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) {
            $pageSize = 10;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        $q = ItemModel::order('id', 'desc');
        if ($keyword !== '') {
            $q->whereLike('product_code', '%' . $keyword . '%');
        }

        $list = $q->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $emb = (string) ($row->embedding ?? '');
            $aiDesc = trim((string) ($row->ai_description ?? ''));
            $items[] = [
                'id' => (int) $row->id,
                'product_code' => (string) ($row->product_code ?? ''),
                'image_ref' => (string) ($row->image_ref ?? ''),
                'hot_type' => (string) ($row->hot_type ?? ''),
                'ai_description' => $aiDesc,
                'ai_description_short' => $aiDesc !== '' ? (mb_strlen($aiDesc) > 60 ? mb_substr($aiDesc, 0, 60) . '…' : $aiDesc) : '',
                'has_embedding' => $emb !== '' && $emb[0] === '[',
                'created_at' => (string) ($row->created_at ?? ''),
            ];
        }

        $pythonOk = $this->checkPythonEmbed();
        $pythonDiag = '';
        if (!$pythonOk) {
            $log = ProductStyleEmbeddingService::getLastRawOutput();
            $pythonDiag = $log !== ''
                ? substr(preg_replace('/\s+/u', ' ', $log), 0, 500)
                : '无子进程输出：可能未找到 Python、php.ini 禁用了 exec，或 Web 服务账号 PATH 与 shell 不一致。Linux 请在 .env 设置 PRODUCT_SEARCH_PYTHON=python3 或 /usr/bin/python3；Windows 请指定 python.exe 绝对路径。';
        }

        $visionCfg = VisionOpenAIConfig::get();
        $visionDescCount = 0;
        try {
            $visionDescCount = (int) Db::name('product_style_items')
                ->where('status', 1)
                ->whereRaw('ai_description IS NOT NULL AND TRIM(ai_description) <> \'\'')
                ->count();
        } catch (\Throwable $e) {
            $visionDescCount = 0;
        }

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
            'python_ok' => $pythonOk,
            'python_diag' => $pythonDiag,
            'vision_openai_enabled' => $visionCfg['enabled'],
            'vision_items_with_desc' => $visionDescCount,
            'aliyun_is_enabled' => AliyunImageSearchConfig::get()['enabled'],
            'aliyun_is_pending' => ProductStyleAliyunQueueService::pendingCount(),
        ]);
    }

    /**
     * 手动消费阿里云图传队列（导入未完成同步时可点此重试）
     */
    public function syncAliyunQueue()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $max = (int) $this->request->post('max', 300);
        $sec = (int) $this->request->post('seconds', 180);
        if ($max < 1) {
            $max = 300;
        }
        if ($max > 2000) {
            $max = 2000;
        }
        if ($sec < 10) {
            $sec = 10;
        }
        if ($sec > 600) {
            $sec = 600;
        }
        $sync = ProductStyleAliyunQueueService::drain($max, $sec);

        return $this->jsonOk([
            'sync' => $sync,
            'pending' => ProductStyleAliyunQueueService::pendingCount(),
        ], '同步批次完成');
    }

    private function checkPythonEmbed(): bool
    {
        $dir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'temp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $probe = $dir . DIRECTORY_SEPARATOR . '_probe_style.jpg';
        if (!is_file($probe)) {
            $im = imagecreatetruecolor(32, 32);
            if ($im) {
                imagejpeg($im, $probe, 85);
                imagedestroy($im);
            }
        }
        if (!is_file($probe)) {
            return false;
        }
        $vec = ProductStyleEmbeddingService::embedFile($probe);

        return is_array($vec) && isset($vec[0]);
    }

    /**
     * POST 文件：CSV/TXT（链接、路径、Base64）或 Excel（xlsx/xls，图片列支持单元格嵌入图）
     */
    public function importCsv()
    {
        try {
            return $this->importCsvInner();
        } catch (\Throwable $e) {
            Log::error('product_search importCsv: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return $this->jsonErr('导入失败：' . $e->getMessage());
        }
    }

    private function importCsvInner()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonErr('请上传文件');
        }
        $ext = strtolower((string) $file->extension());
        if ($ext === '') {
            $ext = strtolower(pathinfo((string) $file->getOriginalName(), PATHINFO_EXTENSION));
        }
        $tmp = $file->getPathname();
        if (!is_readable($tmp)) {
            return $this->jsonErr('无法读取上传文件');
        }

        $this->prepareLongRunningImport();

        $publicRoot = root_path() . 'public';

        if (in_array($ext, ['xlsx', 'xls', 'xlsm'], true)) {
            return $this->importExcelSpreadsheet($tmp, $publicRoot);
        }
        if (!in_array($ext, ['csv', 'txt'], true)) {
            return $this->jsonErr('仅支持 .csv / .txt / .xlsx / .xls / .xlsm');
        }
        $handle = fopen($tmp, 'rb');
        if ($handle === false) {
            return $this->jsonErr('无法打开文件');
        }
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $rowIndex = 0;
        $headerMap = null;
        $inserted = 0;
        $updated = 0;
        $fail = 0;
        $errors = [];
        $maxImports = 5000;
        $visionDescribed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowIndex++;
            if ($row === [null] || $row === false) {
                continue;
            }
            $row = array_map(static function ($c) {
                return trim((string) $c);
            }, $row);
            if (count($row) === 1 && $row[0] === '') {
                continue;
            }

            if ($headerMap === null) {
                $detected = ProductStyleImportService::mapHeader($row);
                if ($detected !== null) {
                    $headerMap = $detected;
                    continue;
                }
                $headerMap = ['code' => 0, 'image' => 1, 'hot' => 2];
                if (!isset($row[0], $row[1])) {
                    fclose($handle);

                    return $this->jsonErr('CSV 首行需为表头（含「产品编号」「图片」列），或前两列为编号+图片');
                }
            }

            $ci = $headerMap['code'];
            $ii = $headerMap['image'];
            $hi = $headerMap['hot'] ?? null;
            if (!isset($row[$ci], $row[$ii])) {
                $fail++;
                if (count($errors) < 30) {
                    $errors[] = "第{$rowIndex}行：列不完整";
                }
                continue;
            }
            $code = trim((string) $row[$ci]);
            $imgRaw = (string) $row[$ii];
            $hot = $hi !== null && isset($row[$hi]) ? trim((string) $row[$hi]) : '';
            if ($code === '' || $imgRaw === '') {
                continue;
            }

            $resolved = ProductStyleImportService::resolveImage($imgRaw, $publicRoot);
            if (!$resolved['ok'] || $resolved['temp'] === '') {
                $fail++;
                if (count($errors) < 30) {
                    $errors[] = "第{$rowIndex}行 {$code}：图片无法下载或路径无效";
                }
                continue;
            }

            $vec = ProductStyleEmbeddingService::embedFile($resolved['temp']);
            if (!is_array($vec)) {
                if (strpos($resolved['temp'], 'style_import') !== false && is_file($resolved['temp'])) {
                    @unlink($resolved['temp']);
                }
                $fail++;
                if (count($errors) < 30) {
                    $errors[] = "第{$rowIndex}行 {$code}：特征提取失败（请检查 Python 与 torch）";
                }
                continue;
            }

            $aiDesc = null;
            $vCfg = VisionOpenAIConfig::get();
            if ($vCfg['enabled'] && $vCfg['describe_on_import']) {
                $aiDesc = VisionSearchService::describeEarringImage($resolved['temp']);
                if ($aiDesc !== null && $aiDesc !== '') {
                    $visionDescribed++;
                }
            }
            $u = $this->upsertStyleItem($code, $resolved['ref'], $hot, $vec, $aiDesc);
            $this->syncProductAiDescription($code, $aiDesc);
            $picName = ProductStyleAliyunQueueService::makePicName($resolved['ref'], $resolved['temp']);
            ProductStyleAliyunQueueService::enqueue($code, $resolved['temp'], $picName, $hot);
            if (strpos($resolved['temp'], 'style_import') !== false && is_file($resolved['temp'])) {
                @unlink($resolved['temp']);
            }
            if ($u['inserted']) {
                $inserted++;
            } else {
                $updated++;
            }
            $success = $inserted + $updated;
            if ($success % 5 === 0) {
                \gc_collect_cycles();
            }
            if ($success >= $maxImports) {
                break;
            }
        }
        fclose($handle);

        $aliyunSync = ProductStyleAliyunQueueService::drain(800, 300);

        return $this->jsonOk([
            'imported' => $inserted,
            'updated' => $updated,
            'failed' => $fail,
            'errors' => $errors,
            'vision' => [
                'openai_enabled' => VisionOpenAIConfig::get()['enabled'],
                'describe_on_import' => VisionOpenAIConfig::get()['describe_on_import'],
                'described_rows' => $visionDescribed,
            ],
            'aliyun' => [
                'sync_batch' => $aliyunSync,
                'pending' => ProductStyleAliyunQueueService::pendingCount(),
            ],
        ], '导入完成');
    }

    private function importExcelSpreadsheet(string $tmp, string $publicRoot)
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return $this->jsonErr('未安装 Excel 解析依赖，请在项目根目录执行 composer install（需 phpoffice/phpspreadsheet）');
        }

        $inserted = 0;
        $updated = 0;
        $fail = 0;
        $errors = [];
        $maxImports = 5000;
        $visionDescribed = 0;

        try {
            foreach (ProductStyleXlsxImportService::iterateRows($tmp) as $rec) {
                $rowIndex = (int) $rec['row'];
                $code = $rec['code'];
                if ($code === '') {
                    continue;
                }
                $imgTemp = $rec['imageTemp'];
                $imgRaw = $rec['imageRaw'];
                $hot = $rec['hot'];

                if (($imgTemp === null || !is_file($imgTemp) || !is_readable($imgTemp)) && trim($imgRaw) === '') {
                    $fail++;
                    if (count($errors) < 30) {
                        $errors[] = "第{$rowIndex}行 {$code}：图片列为空且无嵌入图（请确认图片插在「图片」列对应单元格内）";
                    }
                    continue;
                }

                $excelEmbedSource = null;
                if ($imgTemp !== null && is_file($imgTemp) && is_readable($imgTemp)) {
                    $resolved = ['ref' => '(Excel嵌入图)', 'temp' => $imgTemp, 'ok' => true];
                    $excelEmbedSource = $imgTemp;
                } else {
                    $resolved = ProductStyleImportService::resolveImage($imgRaw, $publicRoot);
                }
                if (!$resolved['ok'] || $resolved['temp'] === '') {
                    $fail++;
                    if (count($errors) < 30) {
                        $errors[] = "第{$rowIndex}行 {$code}：图片无法解析（嵌入图失败或链接/路径无效）";
                    }
                    continue;
                }

                $vec = ProductStyleEmbeddingService::embedFile($resolved['temp']);
                if (!is_array($vec)) {
                    if (strpos($resolved['temp'], 'style_import') !== false && is_file($resolved['temp'])) {
                        @unlink($resolved['temp']);
                    }
                    $fail++;
                    if (count($errors) < 30) {
                        $errors[] = "第{$rowIndex}行 {$code}：特征提取失败（请检查 Python 与 torch）";
                    }
                    continue;
                }

                $imageRef = $resolved['ref'];
                if ($excelEmbedSource !== null && is_file($excelEmbedSource)) {
                    $saved = ProductStyleImportService::persistStyleImageToPublic($excelEmbedSource, $publicRoot);
                    if ($saved !== null) {
                        $imageRef = $saved;
                    }
                }

                $aiDesc = null;
                $vCfg = VisionOpenAIConfig::get();
                if ($vCfg['enabled'] && $vCfg['describe_on_import']) {
                    $aiDesc = VisionSearchService::describeEarringImage($resolved['temp']);
                    if ($aiDesc !== null && $aiDesc !== '') {
                        $visionDescribed++;
                    }
                }
                $u = $this->upsertStyleItem($code, $imageRef, $hot, $vec, $aiDesc);
                $this->syncProductAiDescription($code, $aiDesc);
                $picName = ProductStyleAliyunQueueService::makePicName($imageRef, $resolved['temp']);
                ProductStyleAliyunQueueService::enqueue($code, $resolved['temp'], $picName, $hot);
                if (strpos($resolved['temp'], 'style_import') !== false && is_file($resolved['temp'])) {
                    @unlink($resolved['temp']);
                }
                if ($u['inserted']) {
                    $inserted++;
                } else {
                    $updated++;
                }
                $success = $inserted + $updated;
                if ($success % 5 === 0) {
                    \gc_collect_cycles();
                }
                if ($success >= $maxImports) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            return $this->jsonErr('Excel 解析失败：' . $e->getMessage());
        }

        $aliyunSync = ProductStyleAliyunQueueService::drain(800, 300);

        return $this->jsonOk([
            'imported' => $inserted,
            'updated' => $updated,
            'failed' => $fail,
            'errors' => $errors,
            'vision' => [
                'openai_enabled' => VisionOpenAIConfig::get()['enabled'],
                'describe_on_import' => VisionOpenAIConfig::get()['describe_on_import'],
                'described_rows' => $visionDescribed,
            ],
            'aliyun' => [
                'sync_batch' => $aliyunSync,
                'pending' => ProductStyleAliyunQueueService::pendingCount(),
            ],
        ], '导入完成');
    }

    public function delete()
    {
        $id = (int) $this->request->param('id', 0);
        ItemModel::destroy($id);

        return $this->jsonOk([], '已删除');
    }

    /**
     * POST：批量删除索引。JSON body: {"ids":[1,2,3]} 或表单 ids[] / ids=1,2
     */
    public function deleteBatch()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->jsonErr('仅支持 POST');
            }
            $ids = $this->parseIdListParam();
            if ($ids === []) {
                return $this->jsonErr('请选择要删除的记录（ids 不能为空）');
            }
            $max = 500;
            if (count($ids) > $max) {
                return $this->jsonErr('单次最多删除 ' . $max . ' 条');
            }
            ItemModel::whereIn('id', $ids)->delete();

            return $this->jsonOk(['deleted' => count($ids)], '已批量删除');
        } catch (\Throwable $e) {
            Log::error('product_search deleteBatch: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return $this->jsonErr('批量删除失败：' . $e->getMessage());
        }
    }

    /** @return int[] */
    private function parseIdListParam(): array
    {
        $ids = $this->request->param('ids');
        if (($ids === null || $ids === '' || $ids === []) && $this->request->getContent() !== '') {
            $json = json_decode((string) $this->request->getContent(), true);
            if (is_array($json) && isset($json['ids'])) {
                $ids = $json['ids'];
            }
        }
        if (is_string($ids)) {
            $ids = preg_split('/[\s,]+/', $ids, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }
        if (!is_array($ids)) {
            return [];
        }
        $out = [];
        foreach ($ids as $v) {
            $n = (int) $v;
            if ($n > 0) {
                $out[$n] = true;
            }
        }

        return array_map('intval', array_keys($out));
    }

    /**
     * POST multipart/form-data：更新编号、爆款类型；可选修改参考图（文本链接/路径或上传新图，会重算向量）
     */
    public function updateItem()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->jsonErr('仅支持 POST');
            }
            $id = (int) $this->request->param('id', 0);
            if ($id <= 0) {
                return $this->jsonErr('无效 ID');
            }
            $row = ItemModel::find($id);
            if (!$row) {
                return $this->jsonErr('记录不存在');
            }
            $productCode = trim((string) $this->request->param('product_code', ''));
            if ($productCode === '') {
                return $this->jsonErr('产品编号不能为空');
            }
            $hotType = trim((string) $this->request->param('hot_type', ''));
            $imageRefInput = trim((string) $this->request->param('image_ref', ''));
            $publicRoot = root_path() . 'public';

            $update = [
                'product_code' => $productCode,
                'hot_type' => $hotType,
            ];

            $file = $this->request->file('image');
            if ($file && $file->isValid()) {
                $ext = strtolower((string) $file->extension());
                if ($ext === '') {
                    $ext = strtolower(pathinfo((string) $file->getOriginalName(), PATHINFO_EXTENSION));
                }
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($ext, $allowed, true)) {
                    return $this->jsonErr('仅支持 jpg / png / gif / webp 图片');
                }
                $tmp = $file->getPathname();
                if (!is_readable($tmp)) {
                    return $this->jsonErr('无法读取上传图片');
                }
                $vec = ProductStyleEmbeddingService::embedFile($tmp);
                if (!is_array($vec)) {
                    return $this->jsonErr('特征提取失败，请检查 Python 环境');
                }
                if (VisionOpenAIConfig::get()['enabled']) {
                    $aiNew = VisionSearchService::describeEarringImage($tmp);
                    if ($aiNew !== null && $aiNew !== '') {
                        $update['ai_description'] = $aiNew;
                    }
                }
                $saved = ProductStyleImportService::persistStyleImageToPublic($tmp, $publicRoot);
                $update['image_ref'] = $saved ?? ($imageRefInput !== '' ? $imageRefInput : (string) ($row->image_ref ?? ''));
                if ($update['image_ref'] === '') {
                    $update['image_ref'] = '(本地上传)';
                }
                $update['embedding'] = json_encode($vec, JSON_UNESCAPED_UNICODE);
            } elseif ($imageRefInput !== (string) ($row->image_ref ?? '')) {
                if ($imageRefInput === '') {
                    return $this->jsonErr('参考图不能为空；不修改图片时请保持原文本或上传新图');
                }
                $resolved = ProductStyleImportService::resolveImage($imageRefInput, $publicRoot);
                if (!$resolved['ok'] || $resolved['temp'] === '') {
                    return $this->jsonErr('参考图无法解析（链接无效或文件不存在）');
                }
                $vec = ProductStyleEmbeddingService::embedFile($resolved['temp']);
                if (!is_array($vec)) {
                    if (strpos($resolved['temp'], 'style_import') !== false && is_file($resolved['temp'])) {
                        @unlink($resolved['temp']);
                    }

                    return $this->jsonErr('特征提取失败，请检查 Python 环境');
                }
                if (VisionOpenAIConfig::get()['enabled']) {
                    $aiNew = VisionSearchService::describeEarringImage($resolved['temp']);
                    if ($aiNew !== null && $aiNew !== '') {
                        $update['ai_description'] = $aiNew;
                    }
                }
                if (strpos($resolved['temp'], 'style_import') !== false && is_file($resolved['temp'])) {
                    @unlink($resolved['temp']);
                }
                $update['image_ref'] = $resolved['ref'];
                $update['embedding'] = json_encode($vec, JSON_UNESCAPED_UNICODE);
            }

            $row->save($update);
            if (isset($update['ai_description']) && (string) $update['ai_description'] !== '') {
                $this->syncProductAiDescription($productCode, (string) $update['ai_description']);
            }

            return $this->jsonOk([], '已保存');
        } catch (\Throwable $e) {
            Log::error('product_search updateItem: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return $this->jsonErr('保存失败：' . $e->getMessage());
        }
    }

    /**
     * 下载示例 CSV
     */
    public function sampleCsv()
    {
        $csv = "\xEF\xBB\xBF产品编号,图片,爆款类型\nEH001,https://example.com/a.jpg,耳钉\nEH002,/uploads/demo.jpg,耳环\n";
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sample_product_style.csv"',
        ], 'html');
    }
}
