<?php
declare(strict_types=1);

namespace app\service;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel 嵌入图：先遍历 {@see Worksheet::getDrawingCollection()} / {@see Worksheet::getInCellDrawingCollection()}，
 * 按锚点与「图片列」重叠关系建立 **行号 => 已落盘站内路径**；再扫单元格内图片；最后用 OOXML Zip 补洞。
 * 性能：先批量落盘（md5 去重），再供按行读文本，避免长时间持有二进制于内存。
 */
class ProductStyleXlsxDrawingRowMapBuilder
{
    /**
     * @return array{ok: bool, map: array<int, string>, img_col: int, error?: string}
     *               map 的 value 为 /uploads/products/{md5}.ext
     */
    public static function build(string $xlsxPath, string $publicRoot): array
    {
        if (!\class_exists(IOFactory::class)) {
            return ['ok' => false, 'map' => [], 'img_col' => 0, 'error' => '未安装 PhpSpreadsheet'];
        }
        if (!\is_file($xlsxPath) || !\is_readable($xlsxPath)) {
            return ['ok' => false, 'map' => [], 'img_col' => 0, 'error' => '文件不可读'];
        }
        $ext = \strtolower((string) \pathinfo($xlsxPath, PATHINFO_EXTENSION));
        if (!\in_array($ext, ['xlsx', 'xlsm'], true)) {
            return ['ok' => true, 'map' => [], 'img_col' => 0];
        }

        $spreadsheet = null;
        try {
            $reader = IOFactory::createReaderForFile($xlsxPath);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($xlsxPath);
        } catch (\Throwable $e) {
            return ['ok' => false, 'map' => [], 'img_col' => 0, 'error' => $e->getMessage()];
        }

        $map = [];
        try {
            $sheet = $spreadsheet->getActiveSheet();
            $hr = \max(1, (int) $sheet->getHighestRow());
            $hc = $sheet->getHighestColumn();
            $headerMatrix = $sheet->rangeToArray('A1:' . $hc . '1', null, true, false);
            $headerRow = \array_map(static function ($v) {
                return \trim((string) ($v ?? ''));
            }, $headerMatrix[0] ?? []);
            $headerMap = ProductStyleImportService::mapHeader($headerRow);
            if ($headerMap === null) {
                $headerMap = ['code' => 0, 'image' => 1, 'hot' => 2];
            }
            $imgCol = $headerMap['image'] + 1;

            $seen = [];
            foreach (self::iterAllDrawings($sheet) as $drawing) {
                $oid = \spl_object_id($drawing);
                if (isset($seen[$oid])) {
                    continue;
                }
                $seen[$oid] = true;
                $tmp = ProductStyleXlsxImportService::exportEmbeddedDrawingToTemp($drawing);
                if ($tmp === null || !\is_file($tmp)) {
                    continue;
                }
                $web = ProductStyleImportService::saveImportImageToProductsDir($tmp, $publicRoot);
                if (\is_file($tmp)) {
                    @\unlink($tmp);
                }
                if ($web === null) {
                    continue;
                }
                foreach (self::rowsMatchingDrawing($drawing, $imgCol) as $r) {
                    if ($r >= 2 && $r <= $hr) {
                        $map[$r] = $web;
                    }
                }
            }

            for ($r = 2; $r <= $hr; $r++) {
                $coord = Coordinate::stringFromColumnIndex($imgCol) . $r;
                try {
                    $cell = $sheet->getCell($coord);
                } catch (\Throwable $e) {
                    continue;
                }
                $v = $cell->getValue();
                if ($v instanceof BaseDrawing) {
                    $tmp = ProductStyleXlsxImportService::exportEmbeddedDrawingToTemp($v);
                    if ($tmp !== null && \is_file($tmp)) {
                        $web = ProductStyleImportService::saveImportImageToProductsDir($tmp, $publicRoot);
                        if (\is_file($tmp)) {
                            @\unlink($tmp);
                        }
                        if ($web !== null) {
                            $map[$r] = $web;
                        }
                    }
                }
            }

            $real = \realpath($xlsxPath);
            $openPath = $real !== false ? $real : $xlsxPath;
            $title = $sheet->getTitle();
            for ($r = 2; $r <= $hr; $r++) {
                if (isset($map[$r])) {
                    continue;
                }
                $web = ProductStyleXlsxZipEmbeddedImageService::extractRowImageToProductsDir($openPath, $title, $imgCol, $r, $publicRoot);
                if ($web !== null) {
                    $map[$r] = $web;
                }
            }
            for ($r = 2; $r <= $hr; $r++) {
                if (isset($map[$r])) {
                    continue;
                }
                for ($d = -2; $d <= 2; $d++) {
                    if ($d === 0) {
                        continue;
                    }
                    $c = $imgCol + $d;
                    if ($c < 1) {
                        continue;
                    }
                    $web = ProductStyleXlsxZipEmbeddedImageService::extractRowImageToProductsDir($openPath, $title, $c, $r, $publicRoot);
                    if ($web !== null) {
                        $map[$r] = $web;
                        break;
                    }
                }
            }

            \ksort($map, SORT_NUMERIC);

            return ['ok' => true, 'map' => $map, 'img_col' => $imgCol];
        } catch (\Throwable $e) {
            return ['ok' => false, 'map' => [], 'img_col' => 0, 'error' => $e->getMessage()];
        } finally {
            if ($spreadsheet !== null) {
                $spreadsheet->disconnectWorksheets();
            }
        }
    }

    /**
     * @return list<BaseDrawing|\PhpOffice\PhpSpreadsheet\Worksheet\Drawing|\PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing>
     */
    private static function iterAllDrawings(Worksheet $sheet): array
    {
        $list = [];
        if (\method_exists($sheet, 'getInCellDrawingCollection')) {
            foreach ($sheet->getInCellDrawingCollection() as $d) {
                $list[] = $d;
            }
        }
        foreach ($sheet->getDrawingCollection() as $d) {
            $list[] = $d;
        }

        return $list;
    }

    /**
     * 列与锚点矩形相交则覆盖区间内所有行；否则用锚点中心格（Disjoint 近似）。
     *
     * @return list<int> 1-based Excel 行号
     */
    private static function rowsMatchingDrawing(BaseDrawing $drawing, int $imgCol1Based): array
    {
        $coordStr = \trim($drawing->getCoordinates());
        if ($coordStr === '') {
            return [];
        }
        try {
            $from = Coordinate::indexesFromString($coordStr);
        } catch (\Throwable $e) {
            return [];
        }
        $fc = (int) $from[0];
        $fr = (int) $from[1];
        $to2 = \trim((string) $drawing->getCoordinates2());
        if ($to2 === '') {
            $tc = $fc;
            $tr = $fr;
        } else {
            try {
                $to = Coordinate::indexesFromString($to2);
                $tc = (int) $to[0];
                $tr = (int) $to[1];
            } catch (\Throwable $e) {
                $tc = $fc;
                $tr = $fr;
            }
        }
        $minC = \min($fc, $tc);
        $maxC = \max($fc, $tc);
        $minR = \min($fr, $tr);
        $maxR = \max($fr, $tr);

        if ($maxC < $imgCol1Based || $minC > $imgCol1Based) {
            $cc = (int) \round(($minC + $maxC) / 2);
            $cr = (int) \round(($minR + $maxR) / 2);
            if ($cc === $imgCol1Based) {
                return [$cr];
            }

            return [];
        }
        $rows = [];
        for ($r = $minR; $r <= $maxR; $r++) {
            $rows[] = $r;
        }

        return $rows;
    }
}
