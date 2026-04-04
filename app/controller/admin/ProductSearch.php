<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\ProductStyleItem as ItemModel;
use app\service\ProductStyleEmbeddingService;
use app\service\ProductStyleImportService;
use app\service\ProductStyleXlsxImportService;
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
            $items[] = [
                'id' => (int) $row->id,
                'product_code' => (string) ($row->product_code ?? ''),
                'image_ref' => (string) ($row->image_ref ?? ''),
                'hot_type' => (string) ($row->hot_type ?? ''),
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

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
            'python_ok' => $pythonOk,
            'python_diag' => $pythonDiag,
        ]);
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
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonErr('请上传文件');
        }
        $ext = strtolower((string) $file->extension());
        $tmp = $file->getPathname();
        if (!is_readable($tmp)) {
            return $this->jsonErr('无法读取上传文件');
        }

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
        $ok = 0;
        $fail = 0;
        $errors = [];
        $maxImports = 5000;

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
            if (strpos($resolved['temp'], 'style_import') !== false && is_file($resolved['temp'])) {
                @unlink($resolved['temp']);
            }
            if (!is_array($vec)) {
                $fail++;
                if (count($errors) < 30) {
                    $errors[] = "第{$rowIndex}行 {$code}：特征提取失败（请检查 Python 与 torch）";
                }
                continue;
            }

            ItemModel::create([
                'product_code' => $code,
                'image_ref' => $resolved['ref'],
                'hot_type' => $hot,
                'embedding' => json_encode($vec, JSON_UNESCAPED_UNICODE),
                'status' => 1,
            ]);
            $ok++;
            if ($ok >= $maxImports) {
                break;
            }
        }
        fclose($handle);

        return $this->jsonOk([
            'imported' => $ok,
            'failed' => $fail,
            'errors' => $errors,
        ], '导入完成');
    }

    private function importExcelSpreadsheet(string $tmp, string $publicRoot)
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return $this->jsonErr('未安装 Excel 解析依赖，请在项目根目录执行 composer install（需 phpoffice/phpspreadsheet）');
        }

        $ok = 0;
        $fail = 0;
        $errors = [];
        $maxImports = 5000;

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

                if ($imgTemp !== null && is_file($imgTemp) && is_readable($imgTemp)) {
                    $resolved = ['ref' => '(Excel嵌入图)', 'temp' => $imgTemp, 'ok' => true];
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
                if (strpos($resolved['temp'], 'style_import') !== false && is_file($resolved['temp'])) {
                    @unlink($resolved['temp']);
                }
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

                ItemModel::create([
                    'product_code' => $code,
                    'image_ref' => $resolved['ref'],
                    'hot_type' => $hot,
                    'embedding' => json_encode($vec, JSON_UNESCAPED_UNICODE),
                    'status' => 1,
                ]);
                $ok++;
                if ($ok >= $maxImports) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            return $this->jsonErr('Excel 解析失败：' . $e->getMessage());
        }

        return $this->jsonOk([
            'imported' => $ok,
            'failed' => $fail,
            'errors' => $errors,
        ], '导入完成');
    }

    public function delete()
    {
        $id = (int) $this->request->param('id', 0);
        ItemModel::destroy($id);

        return $this->jsonOk([], '已删除');
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
