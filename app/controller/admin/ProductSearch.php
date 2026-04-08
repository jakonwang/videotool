<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\ProductStyleItem as ItemModel;
use app\service\AdminAuthService;
use app\service\CatalogTokenService;
use app\service\VolcArkVisionConfig;
use app\service\ProductStyleEmbeddingService;
use app\service\VisionOpenAIConfig;
use think\facade\Db;
use app\service\ProductStyleVisionDescribeService;
use app\service\ProductStyleImportService;
use app\service\ProductStyleImportTaskRunner;
use app\service\ProductStyleIndexRowService;
use think\facade\Log;
use think\facade\View;

/**
 * 图片搜款式：索引导入与后台列表
 */
class ProductSearch extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return $this->apiJsonOk($data, $msg);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        return $this->apiJsonErr($msg, $code, $data, $errorKey);
    }

    /** @return string[] lowercase column names */
    private function styleItemColumns(): array
    {
        static $cache = null;
        if (is_array($cache)) {
            return $cache;
        }
        try {
            $table = (string) (new ItemModel())->getTable();
            $table = str_replace('`', '', trim($table));
            if ($table === '') {
                $table = 'product_style_items';
            }
            $rows = Db::query('SHOW COLUMNS FROM `' . $table . '`');
            $cols = [];
            foreach ($rows as $row) {
                $name = strtolower((string) ($row['Field'] ?? ''));
                if ($name !== '') {
                    $cols[] = $name;
                }
            }
            $cache = array_values(array_unique($cols));
        } catch (\Throwable $e) {
            // Fallback to a conservative subset for older schemas.
            $cache = [
                'id',
                'product_code',
                'image_ref',
                'hot_type',
                'wholesale_price',
                'min_order_qty',
                'price_levels_json',
                'embedding',
                'status',
                'created_at',
                'updated_at',
            ];
        }

        return $cache;
    }

    private function styleItemHasColumn(string $name): bool
    {
        return in_array(strtolower(trim($name)), $this->styleItemColumns(), true);
    }

    public function index()
    {
        return View::fetch('admin/product_search/index', []);
    }

    public function listJson()
    {
        $hasAiDescription = $this->styleItemHasColumn('ai_description');
        $keyword = trim((string) $this->request->param('keyword', ''));
        $category = trim((string) $this->request->param('category', ''));
        $priceMinRaw = trim((string) $this->request->param('price_min', ''));
        $priceMaxRaw = trim((string) $this->request->param('price_max', ''));
        $moqMinRaw = trim((string) $this->request->param('moq_min', ''));
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
            $q->where(static function ($query) use ($keyword, $hasAiDescription): void {
                $query->whereLike('product_code', '%' . $keyword . '%')
                    ->whereOr('hot_type', 'like', '%' . $keyword . '%');
                if ($hasAiDescription) {
                    $query->whereOr('ai_description', 'like', '%' . $keyword . '%');
                }
            });
        }
        if ($category !== '') {
            $q->where('hot_type', $category);
        }

        $priceMin = is_numeric($priceMinRaw) ? (float) $priceMinRaw : null;
        $priceMax = is_numeric($priceMaxRaw) ? (float) $priceMaxRaw : null;
        if ($priceMin !== null && $priceMax !== null && $priceMax < $priceMin) {
            [$priceMin, $priceMax] = [$priceMax, $priceMin];
        }
        if ($priceMin !== null) {
            $q->where('wholesale_price', '>=', $priceMin);
        }
        if ($priceMax !== null) {
            $q->where('wholesale_price', '<=', $priceMax);
        }

        $moqMin = is_numeric($moqMinRaw) ? (int) $moqMinRaw : null;
        if ($moqMin !== null && $moqMin > 0) {
            $q->where('min_order_qty', '>=', $moqMin);
        }

        $list = $q->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $emb = (string) ($row->embedding ?? '');
            $aiDesc = $hasAiDescription ? trim((string) ($row->ai_description ?? '')) : '';
            $items[] = [
                'id' => (int) $row->id,
                'product_code' => (string) ($row->product_code ?? ''),
                'image_ref' => (string) ($row->image_ref ?? ''),
                'hot_type' => (string) ($row->hot_type ?? ''),
                'wholesale_price' => round((float) ($row->wholesale_price ?? 0), 2),
                'min_order_qty' => max(1, (int) ($row->min_order_qty ?? 1)),
                'price_levels_json' => trim((string) ($row->price_levels_json ?? '')),
                'ai_description' => $aiDesc,
                'ai_description_short' => $aiDesc !== '' ? (mb_strlen($aiDesc) > 60 ? mb_substr($aiDesc, 0, 60) . '...' : $aiDesc) : '',
                'has_embedding' => $emb !== '' && $emb[0] === '[',
                'created_at' => (string) ($row->created_at ?? ''),
                'updated_at' => (string) ($row->updated_at ?? ''),
            ];
        }

        $categoryOptions = [];
        try {
            $categoryRows = ItemModel::whereRaw("TRIM(IFNULL(hot_type,'')) <> ''")
                ->group('hot_type')
                ->order('hot_type', 'asc')
                ->column('hot_type');
            foreach ($categoryRows as $cat) {
                $name = trim((string) $cat);
                if ($name === '') {
                    continue;
                }
                $categoryOptions[] = [
                    'value' => $name,
                    'label' => $name,
                ];
            }
        } catch (\Throwable $e) {
            $categoryOptions = [];
        }

        $pythonOk = $this->checkPythonEmbed();
        $pythonDiag = '';
        if (!$pythonOk) {
            $log = ProductStyleEmbeddingService::getLastRawOutput();
            $pythonDiag = $log !== ''
                ? substr((string) preg_replace('/\s+/u', ' ', $log), 0, 500)
                : 'No subprocess output. Check Python path, exec permission, and web user PATH.';
        }

        $visionCfg = VisionOpenAIConfig::get();
        $visionDescCount = 0;
        if ($hasAiDescription) {
            try {
                $visionDescCount = (int) Db::name('product_style_items')
                    ->where('status', 1)
                    ->whereRaw('ai_description IS NOT NULL AND TRIM(ai_description) <> \'\'')
                    ->count();
            } catch (\Throwable $e) {
                $visionDescCount = 0;
            }
        }

        $volcCfg = VolcArkVisionConfig::get();

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
            'category_options' => $categoryOptions,
            'current_role' => AdminAuthService::role(),
            'python_ok' => $pythonOk,
            'python_diag' => $pythonDiag,
            /** 兼容旧前端字段：寻款仅使用豆包，此处恒为 false */
            'vision_openai_enabled' => false,
            'vision_describe_on_import' => $visionCfg['describe_on_import'],
            /** 豆包（火山方舟）已启用且配置完整时导入可写 ai_description */
            'vision_any_provider_ready' => $volcCfg['enabled'],
            'vision_items_with_desc' => $visionDescCount,
            /** 导入已不再入队阿里云 / 同步 Google，恒为关闭 */
            'aliyun_is_enabled' => false,
            'aliyun_is_pending' => 0,
            'google_ps_enabled' => false,
            'volc_ark_enabled' => $volcCfg['enabled'],
        ]);
    }

    /**
     * 已停用：导入不再入队阿里云图搜。
     */
    public function syncAliyunQueue()
    {
        return $this->jsonErr('阿里云图传入队已关闭（寻款仅使用豆包）');
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
     * POST 文件：CSV/TXT 异步任务；Excel（xlsx/xls）仍为同步长请求（大表建议转 CSV）
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

    /**
     * GET：仅查询任务进度（不推进）
     */
    public function importTaskStatus()
    {
        try {
            $id = (int) $this->request->param('task_id', 0);
            if ($id <= 0) {
                return $this->jsonErr('无效 task_id');
            }
            ProductStyleImportTaskRunner::bumpMemoryAndTime();
            $snap = ProductStyleImportTaskRunner::snapshot($id);
            if ($snap === null) {
                return $this->jsonErr('任务不存在');
            }

            return $this->jsonOk($snap);
        } catch (\Throwable $e) {
            Log::error('importTaskStatus: ' . $e->getMessage());

            return $this->jsonErr('查询失败：' . $e->getMessage());
        }
    }

    /**
     * POST：推进一行（JSON 或表单 task_id）
     */
    public function importTaskTick()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->jsonErr('仅支持 POST');
            }
            $id = 0;
            $raw = (string) $this->request->getContent();
            if ($raw !== '') {
                $j = json_decode($raw, true);
                if (is_array($j) && isset($j['task_id'])) {
                    $id = (int) $j['task_id'];
                }
            }
            if ($id <= 0) {
                $id = (int) $this->request->post('task_id', 0);
            }
            if ($id <= 0) {
                $id = (int) $this->request->param('task_id', 0);
            }
            if ($id <= 0) {
                return $this->jsonErr('无效 task_id');
            }
            $r = ProductStyleImportTaskRunner::tick($id);
            if (isset($r['_error'])) {
                return $this->jsonErr((string) $r['_error']);
            }

            return $this->jsonOk($r);
        } catch (\Throwable $e) {
            Log::error('importTaskTick: ' . $e->getMessage());

            return $this->jsonErr('处理失败：' . $e->getMessage());
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

        if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls', 'xlsm'], true)) {
            return $this->jsonErr('仅支持 .csv / .txt / .xlsx / .xls / .xlsm');
        }

        ProductStyleImportTaskRunner::bumpMemoryAndTime();
        try {
            $taskId = ProductStyleImportTaskRunner::createFromUploadedFile($tmp, $ext);
        } catch (\Throwable $e) {
            Log::warning('create import task: ' . $e->getMessage());

            return $this->jsonErr('创建导入任务失败：' . $e->getMessage() . '（若首次使用请先执行 database/run_migration_product_style_import_tasks.php 建表；Excel 需已 composer install phpoffice/phpspreadsheet）');
        }

        return $this->jsonOk([
            'mode' => 'async',
            'task_id' => $taskId,
        ], '任务已创建');
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
            $wholesalePrice = (float) $this->request->param('wholesale_price', (string) ($row->wholesale_price ?? '0'));
            if (!is_finite($wholesalePrice) || $wholesalePrice < 0) {
                return $this->jsonErr('鎵归噺浠锋牸鏍煎紡閿欒');
            }
            $wholesalePrice = round($wholesalePrice, 2);
            $minOrderQty = (int) $this->request->param('min_order_qty', (string) ($row->min_order_qty ?? '1'));
            if ($minOrderQty < 1) {
                $minOrderQty = 1;
            }
            $hasPriceLevels = $this->styleItemHasColumn('price_levels_json');
            $hasAiDescription = $this->styleItemHasColumn('ai_description');
            $priceLevelsRaw = trim((string) $this->request->param('price_levels_json', (string) ($row->price_levels_json ?? '')));
            $priceLevelsNormalized = '';
            if ($hasPriceLevels && $priceLevelsRaw !== '') {
                $decoded = json_decode($priceLevelsRaw, true);
                if (!is_array($decoded)) {
                    return $this->jsonErr('price_levels_json must be a valid JSON object');
                }
                $clean = [];
                foreach ($decoded as $k => $v) {
                    $key = CatalogTokenService::normalizeLevel((string) $k);
                    if ($key === '') {
                        continue;
                    }
                    $num = (float) $v;
                    if (!is_finite($num) || $num < 0) {
                        continue;
                    }
                    $clean[$key] = round($num, 2);
                }
                $priceLevelsNormalized = $clean === []
                    ? ''
                    : (string) json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $publicRoot = root_path() . 'public';

            $update = [
                'product_code' => $productCode,
                'hot_type' => $hotType,
            ];
            if ($this->styleItemHasColumn('wholesale_price')) {
                $update['wholesale_price'] = $wholesalePrice;
            }
            if ($this->styleItemHasColumn('min_order_qty')) {
                $update['min_order_qty'] = $minOrderQty;
            }
            if ($hasPriceLevels) {
                $update['price_levels_json'] = $priceLevelsNormalized;
            }

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
                $aiNew = ProductStyleVisionDescribeService::describeEarring($tmp);
                if ($hasAiDescription && $aiNew !== null && $aiNew !== '') {
                    $update['ai_description'] = $aiNew;
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
                $aiNew = ProductStyleVisionDescribeService::describeEarring($resolved['temp']);
                if ($hasAiDescription && $aiNew !== null && $aiNew !== '') {
                    $update['ai_description'] = $aiNew;
                }
                if (strpos($resolved['temp'], 'style_import') !== false && is_file($resolved['temp'])) {
                    @unlink($resolved['temp']);
                }
                $update['image_ref'] = $resolved['ref'];
                $update['embedding'] = json_encode($vec, JSON_UNESCAPED_UNICODE);
            }

            $row->save($update);
            if ($hasAiDescription && isset($update['ai_description']) && (string) $update['ai_description'] !== '') {
                ProductStyleIndexRowService::syncProductAiDescription($productCode, (string) $update['ai_description']);
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
    /**
     * POST: generate signed customer catalog token link.
     */
    public function generateCatalogToken()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->jsonErr('only_post');
            }
            $priceLevel = CatalogTokenService::normalizeLevel((string) $this->request->param('price_level', 'level1'));
            $expireDays = (int) $this->request->param('expire_days', 30);
            if ($expireDays < 1) {
                $expireDays = 1;
            }
            if ($expireDays > 3650) {
                $expireDays = 3650;
            }

            $tokenInfo = CatalogTokenService::generate($priceLevel, $expireDays);
            $url = '/index.php/product_search?token=' . rawurlencode($tokenInfo['token']);

            return $this->jsonOk([
                'token' => $tokenInfo['token'],
                'price_level' => $tokenInfo['price_level'],
                'expire_at' => $tokenInfo['expire_at'],
                'expire_at_text' => date('Y-m-d H:i:s', (int) $tokenInfo['expire_at']),
                'url' => $url,
            ]);
        } catch (\Throwable $e) {
            Log::error('product_search generateCatalogToken: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            return $this->jsonErr('generate_token_failed');
        }
    }

    public function sampleCsv()
    {
        $csv = "\xEF\xBB\xBF产品编号,图片,爆款类型\nEH001,https://example.com/a.jpg,耳钉\nEH002,/uploads/demo.jpg,耳环\n";
        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sample_product_style.csv"',
        ], 'html');
    }

    /**
     * 全量导出索引 CSV（UTF-8 BOM；不含 embedding 向量，便于备份与核对）
     */
    public function exportCsv()
    {
        try {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $filename = 'product_style_items_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return $this->jsonErr('无法写入响应');
            }
            fwrite($out, "\xEF\xBB\xBF");

            $keys = array_keys(Db::name('product_style_items')->getFields());
            $hasImagePath = in_array('image_path', $keys, true);
            $hasAiDescription = in_array('ai_description', $keys, true);

            $headers = ['id', 'product_code', 'image_ref'];
            if ($hasImagePath) {
                $headers[] = 'image_path';
            }
            $headers[] = 'hot_type';
            if ($hasAiDescription) {
                $headers[] = 'ai_description';
            }
            $headers = array_merge($headers, ['status', 'created_at', 'updated_at']);
            fputcsv($out, $headers);

            $field = 'id,product_code,image_ref,hot_type,status,created_at,updated_at';
            if ($hasAiDescription) {
                $field = 'id,product_code,image_ref,hot_type,ai_description,status,created_at,updated_at';
            }
            if ($hasImagePath) {
                $field = 'id,product_code,image_ref,image_path,hot_type,status,created_at,updated_at';
                if ($hasAiDescription) {
                    $field = 'id,product_code,image_ref,image_path,hot_type,ai_description,status,created_at,updated_at';
                }
            }

            $batch = 2000;
            $lastId = 0;
            while (true) {
                $rows = Db::name('product_style_items')
                    ->field($field)
                    ->where('id', '>', $lastId)
                    ->order('id', 'asc')
                    ->limit($batch)
                    ->select();
                if ($rows === null || count($rows) === 0) {
                    break;
                }
                foreach ($rows as $row) {
                    $r = is_array($row) ? $row : $row->toArray();
                    $lastId = (int) ($r['id'] ?? 0);
                    $line = [
                        $r['id'] ?? '',
                        $r['product_code'] ?? '',
                        $r['image_ref'] ?? '',
                    ];
                    if ($hasImagePath) {
                        $line[] = (string) ($r['image_path'] ?? '');
                    }
                    $line[] = (string) ($r['hot_type'] ?? '');
                    if ($hasAiDescription) {
                        $line[] = (string) ($r['ai_description'] ?? '');
                    }
                    $line[] = (int) ($r['status'] ?? 0);
                    $line[] = (string) ($r['created_at'] ?? '');
                    $line[] = (string) ($r['updated_at'] ?? '');
                    fputcsv($out, $line);
                }
            }
            fclose($out);
        } catch (\Throwable $e) {
            Log::error('product_search exportCsv: ' . $e->getMessage());

            if (!headers_sent()) {
                return $this->jsonErr('导出失败：' . $e->getMessage());
            }
        }

        exit;
    }
}
