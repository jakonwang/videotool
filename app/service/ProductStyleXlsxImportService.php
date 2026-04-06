<?php
declare(strict_types=1);

namespace app\service;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\BaseDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing as SheetDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel（xlsx/xls）导入：识别表头列，并按行读取「编号 + 图片列中的嵌入图」
 */
class ProductStyleXlsxImportService
{
    /**
     * 扫描首个工作表：最高行号（用于异步任务边界）
     *
     * @return array{ok:bool, highest_row:int, error?:string}
     */
    public static function scanSheetMeta(string $path): array
    {
        if (!\class_exists(IOFactory::class)) {
            return ['ok' => false, 'highest_row' => 0, 'error' => '未安装 PhpSpreadsheet，请执行 composer install'];
        }
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
            try {
                $sheet = $spreadsheet->getActiveSheet();
                $highestRow = (int) $sheet->getHighestRow();

                return ['ok' => true, 'highest_row' => max(1, $highestRow)];
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'highest_row' => 0, 'error' => 'Excel 读取失败：' . $e->getMessage()];
        }
    }

    /**
     * 一次加载：最高物理行号 + **有效数据行数**（与 {@see extractDataRow} 一致：编号/图片/嵌入图全空则不计入，排除 Excel 尾部大量空行带来的虚高）。
     *
     * @return array{ok:bool, highest_row:int, substantive_rows:int, error?:string}
     */
    public static function analyzeSheet(string $path): array
    {
        if (!\class_exists(IOFactory::class)) {
            return ['ok' => false, 'highest_row' => 0, 'substantive_rows' => 0, 'error' => '未安装 PhpSpreadsheet，请执行 composer install'];
        }
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($path);
            try {
                $sheet = $spreadsheet->getActiveSheet();
                $highestRow = \max(1, (int) $sheet->getHighestRow());
                $drawingsByCell = self::indexDrawingsByCell($sheet);

                $highestColumn = $sheet->getHighestColumn();
                $headerMatrix = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false);
                $headerRow = $headerMatrix[0] ?? [];
                $headerCells = \array_map(static function ($v) {
                    return \trim((string) ($v ?? ''));
                }, $headerRow);

                $headerMap = ProductStyleImportService::mapHeader($headerCells);
                if ($headerMap === null) {
                    $headerMap = ['code' => 0, 'image' => 1, 'hot' => 2];
                }

                $codeCol = $headerMap['code'] + 1;
                $imgCol = $headerMap['image'] + 1;
                $hotCol = isset($headerMap['hot']) ? $headerMap['hot'] + 1 : null;

                $n = 0;
                for ($r = 2; $r <= $highestRow; $r++) {
                    $rec = self::extractDataRow($sheet, $drawingsByCell, $codeCol, $imgCol, $hotCol, $r, $path, null, null);
                    if ($rec !== null) {
                        $n++;
                    }
                }

                return ['ok' => true, 'highest_row' => $highestRow, 'substantive_rows' => $n];
            } finally {
                $spreadsheet->disconnectWorksheets();
            }
        } catch (\Throwable $e) {
            return ['ok' => false, 'highest_row' => 0, 'substantive_rows' => 0, 'error' => 'Excel 读取失败：' . $e->getMessage()];
        }
    }

    /**
     * 读取指定 Excel 行（1-based 行号，数据从第 2 行起）。与 {@see iterateRows} 单行语义一致。
     * 若该行在同步导入中会被 continue 跳过，则返回 null。
     *
     * @return array{row: int, code: string, hot: string, imageTemp: ?string, imageRaw: string}|null
     */
    public static function readDataRowAt(string $path, int $excelRow): ?array
    {
        if (!\class_exists(IOFactory::class) || $excelRow < 2) {
            return null;
        }
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($path);
        try {
            $sheet = $spreadsheet->getActiveSheet();
            $highestRow = (int) $sheet->getHighestRow();
            if ($excelRow > $highestRow) {
                return null;
            }
            $drawingsByCell = self::indexDrawingsByCell($sheet);

            $highestColumn = $sheet->getHighestColumn();
            $headerMatrix = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false);
            $headerRow = $headerMatrix[0] ?? [];
            $headerCells = \array_map(static function ($v) {
                return \trim((string) ($v ?? ''));
            }, $headerRow);

            $headerMap = ProductStyleImportService::mapHeader($headerCells);
            if ($headerMap === null) {
                $headerMap = ['code' => 0, 'image' => 1, 'hot' => 2];
            }

            $codeCol = $headerMap['code'] + 1;
            $imgCol = $headerMap['image'] + 1;
            $hotCol = isset($headerMap['hot']) ? $headerMap['hot'] + 1 : null;

            return self::extractDataRow($sheet, $drawingsByCell, $codeCol, $imgCol, $hotCol, $excelRow, $path, null, null);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * 异步 tick 专用：**只加载一次**工作簿，从 startRow 顺序扫到 highestRow，返回第一条有效行。
     * 避免旧实现每扫一行空行就 {@see readDataRowAt} 全表 load 导致的极慢（数百次磁盘解压）。
     *
     * @param array<int|string, string>|null $prebuiltRowWebPaths 行号 => /uploads/products/md5.ext（先批量提取嵌入图）
     *
     * @return array{rec: ?array{row: int, code: string, hot: string, imageTemp: ?string, imageRaw: string, imagePathWeb?: string}, next_line_idx: int}
     */
    public static function readNextSubstantiveRowSingleLoad(
        string $path,
        int $startRow,
        int $highestRow,
        ?string $publicRootForRowMap = null,
        ?array $prebuiltRowWebPaths = null
    ): array {
        if (!\class_exists(IOFactory::class)) {
            return ['rec' => null, 'next_line_idx' => $startRow];
        }
        $startRow = \max(2, $startRow);
        if ($startRow > $highestRow) {
            return ['rec' => null, 'next_line_idx' => $highestRow + 1];
        }
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($path);
        try {
            $sheet = $spreadsheet->getActiveSheet();
            $drawingsByCell = self::indexDrawingsByCell($sheet);

            $highestColumn = $sheet->getHighestColumn();
            $headerMatrix = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false);
            $headerRow = $headerMatrix[0] ?? [];
            $headerCells = \array_map(static function ($v) {
                return \trim((string) ($v ?? ''));
            }, $headerRow);

            $headerMap = ProductStyleImportService::mapHeader($headerCells);
            if ($headerMap === null) {
                $headerMap = ['code' => 0, 'image' => 1, 'hot' => 2];
            }

            $codeCol = $headerMap['code'] + 1;
            $imgCol = $headerMap['image'] + 1;
            $hotCol = isset($headerMap['hot']) ? $headerMap['hot'] + 1 : null;

            for ($r = $startRow; $r <= $highestRow; $r++) {
                $rec = self::extractDataRow($sheet, $drawingsByCell, $codeCol, $imgCol, $hotCol, $r, $path, $publicRootForRowMap, $prebuiltRowWebPaths);
                if ($rec !== null) {
                    return ['rec' => $rec, 'next_line_idx' => $r + 1];
                }
            }

            return ['rec' => null, 'next_line_idx' => $highestRow + 1];
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @return \Generator<int, array{row: int, code: string, hot: string, imageTemp: ?string, imageRaw: string}>
     */
    public static function iterateRows(string $path): \Generator
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($path);
        try {
            $sheet = $spreadsheet->getActiveSheet();

            $drawingsByCell = self::indexDrawingsByCell($sheet);

            $highestColumn = $sheet->getHighestColumn();
            $headerMatrix = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false);
            $headerRow = $headerMatrix[0] ?? [];
            $headerCells = \array_map(static function ($v) {
                return \trim((string) ($v ?? ''));
            }, $headerRow);

            $headerMap = ProductStyleImportService::mapHeader($headerCells);
            if ($headerMap === null) {
                $headerMap = ['code' => 0, 'image' => 1, 'hot' => 2];
            }

            $codeCol = $headerMap['code'] + 1;
            $imgCol = $headerMap['image'] + 1;
            $hotCol = isset($headerMap['hot']) ? $headerMap['hot'] + 1 : null;

            $highestRow = (int) $sheet->getHighestRow();
            for ($r = 2; $r <= $highestRow; $r++) {
                $rec = self::extractDataRow($sheet, $drawingsByCell, $codeCol, $imgCol, $hotCol, $r, $path, null, null);
                if ($rec !== null) {
                    yield $rec;
                }
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @param array<string, SheetDrawing|MemoryDrawing> $drawingsByCell
     * @param array<int|string, string>|null $prebuiltRowWebPaths
     * @return array{row: int, code: string, hot: string, imageTemp: ?string, imageRaw: string, imagePathWeb?: string}|null
     */
    private static function extractDataRow(
        Worksheet $sheet,
        array $drawingsByCell,
        int $codeCol,
        int $imgCol,
        ?int $hotCol,
        int $r,
        ?string $sourceFilePath = null,
        ?string $publicRootForMap = null,
        ?array $prebuiltRowWebPaths = null
    ): ?array {
        $code = \trim((string) $sheet->getCell(self::cellAddr($codeCol, $r))->getFormattedValue());
        $imgCell = $sheet->getCell(self::cellAddr($imgCol, $r));
        $imgRaw = \trim((string) $imgCell->getFormattedValue());
        $imgRawValue = $imgCell->getValue();
        if (($imgRaw === '' || \strtoupper($imgRaw) === '#NAME?') && \is_string($imgRawValue) && \trim($imgRawValue) !== '') {
            $imgRaw = \trim($imgRawValue);
        }
        $hot = '';
        if ($hotCol !== null) {
            $hot = \trim((string) $sheet->getCell(self::cellAddr($hotCol, $r))->getFormattedValue());
        }

        $imagePathWeb = null;
        $imageTemp = null;
        if ($prebuiltRowWebPaths !== null && $publicRootForMap !== null && $publicRootForMap !== '') {
            $pw = $prebuiltRowWebPaths[$r]
                ?? $prebuiltRowWebPaths[(string) $r]
                ?? $prebuiltRowWebPaths[$r - 1]
                ?? $prebuiltRowWebPaths[(string) ($r - 1)]
                ?? $prebuiltRowWebPaths[$r + 1]
                ?? $prebuiltRowWebPaths[(string) ($r + 1)]
                ?? null;
            if (\is_string($pw) && $pw !== '') {
                $root = \rtrim(\str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $publicRootForMap), DIRECTORY_SEPARATOR);
                $rel = \str_replace('/', DIRECTORY_SEPARATOR, $pw);
                $abs = $root . $rel;
                if (\is_file($abs) && \is_readable($abs)) {
                    $imageTemp = $abs;
                    $imagePathWeb = $pw;
                }
            }
        }
        if ($imageTemp === null) {
            $imageTemp = self::resolveRowEmbeddedImage($sheet, $drawingsByCell, $imgCol, $r, $sourceFilePath);
        }

        if ($code === '' && $imageTemp === null && $imgRaw === '') {
            return null;
        }

        $out = [
            'row' => $r,
            'code' => $code,
            'hot' => $hot,
            'imageTemp' => $imageTemp,
            'imageRaw' => $imgRaw,
        ];
        if ($imagePathWeb !== null) {
            $out['imagePathWeb'] = $imagePathWeb;
        }

        return $out;
    }

    /**
     * 解析本行嵌入图：浮动锚点映射 + **单元格内图片**（Office 365「放置在单元格中」时值为 {@see BaseDrawing}）+ 同行左右各 2 列兜底。
     *
     * @param array<string, SheetDrawing|MemoryDrawing> $drawingsByCell
     */
    private static function resolveRowEmbeddedImage(Worksheet $sheet, array $drawingsByCell, int $imgCol, int $r, ?string $sourceFilePath = null): ?string
    {
        $tryCols = [$imgCol];
        for ($d = -2; $d <= 2; $d++) {
            if ($d === 0) {
                continue;
            }
            $c = $imgCol + $d;
            if ($c >= 1) {
                $tryCols[] = $c;
            }
        }
        $tryCols = \array_values(\array_unique($tryCols));

        foreach ($tryCols as $col) {
            $coord = \strtoupper(self::cellAddr($col, $r));
            if (isset($drawingsByCell[$coord])) {
                $tmp = self::exportDrawingToTemp($drawingsByCell[$coord]);
                if ($tmp !== null) {
                    return $tmp;
                }
            }
        }

        foreach ($tryCols as $col) {
            $coord = self::cellAddr($col, $r);
            try {
                $cell = $sheet->getCell($coord);
            } catch (\Throwable $e) {
                continue;
            }
            $val = $cell->getValue();
            if ($val instanceof BaseDrawing) {
                $tmp = self::exportDrawingToTemp($val);
                if ($tmp !== null) {
                    return $tmp;
                }
            }
        }

        if ($sourceFilePath !== null && \is_file($sourceFilePath) && \is_readable($sourceFilePath)
            && \preg_match('/\\.(xlsx|xlsm)$/i', $sourceFilePath) === 1) {
            $real = \realpath($sourceFilePath);
            $openPath = $real !== false ? $real : $sourceFilePath;
            $title = $sheet->getTitle();
            $maxScanCol = \min(26, Coordinate::columnIndexFromString($sheet->getHighestColumn()));
            foreach ($tryCols as $col) {
                foreach ([$r, $r - 1, $r + 1] as $rr) {
                    if ($rr < 2) {
                        continue;
                    }
                    $tmp = ProductStyleXlsxZipEmbeddedImageService::extractImageAtCell($openPath, $title, $col, $rr);
                    if ($tmp !== null) {
                        return $tmp;
                    }
                }
            }
            foreach ([$r, $r - 1, $r + 1] as $rr) {
                if ($rr < 2) {
                    continue;
                }
                $scanCols = $tryCols;
                for ($c = 1; $c <= $maxScanCol; $c++) {
                    if (!\in_array($c, $scanCols, true)) {
                        $scanCols[] = $c;
                    }
                }
                foreach ($scanCols as $col) {
                    try {
                        $cell = $sheet->getCell(self::cellAddr($col, $rr));
                    } catch (\Throwable $e) {
                        continue;
                    }
                    $raw = $cell->getValue();
                    if (!\is_string($raw)) {
                        continue;
                    }
                    $id = ProductStyleXlsxZipEmbeddedImageService::extractDispImgIdFromFormula($raw);
                    if ($id === null) {
                        continue;
                    }
                    $tmp = ProductStyleXlsxZipEmbeddedImageService::extractImageFromDispImgId($openPath, $id);
                    if ($tmp !== null) {
                        return $tmp;
                    }
                }
            }
        }

        return null;
    }

    /**
     * 导出 PhpSpreadsheet 嵌入 Drawing 为临时文件（供行映射批量落盘）。
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing|\PhpOffice\PhpSpreadsheet\Worksheet\Drawing $drawing
     */
    public static function exportEmbeddedDrawingToTemp($drawing): ?string
    {
        return self::exportDrawingToTemp($drawing);
    }

    /** @param positive-int $col 1-based 列号（A=1） */
    private static function cellAddr(int $col, int $row): string
    {
        return Coordinate::stringFromColumnIndex($col) . $row;
    }

    /**
     * 将每个嵌入图映射到其锚点矩形覆盖的所有单元格（含 twoCellAnchor），避免图跨多格时仅顶格可命中。
     *
     * @return array<string, SheetDrawing|MemoryDrawing>
     */
    private static function indexDrawingsByCell(Worksheet $sheet): array
    {
        $map = [];
        $seen = [];
        foreach (self::collectAllDrawings($sheet) as $drawing) {
            $id = \spl_object_id($drawing);
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            self::mapDrawingToCellRange($drawing, $map);
        }

        return $map;
    }

    /**
     * @return list<BaseDrawing>
     */
    private static function collectAllDrawings(Worksheet $sheet): array
    {
        $list = [];
        if (\method_exists($sheet, 'getInCellDrawingCollection')) {
            foreach ($sheet->getInCellDrawingCollection() as $drawing) {
                $list[] = $drawing;
            }
        }
        foreach ($sheet->getDrawingCollection() as $drawing) {
            $list[] = $drawing;
        }

        return $list;
    }

    /**
     * @param array<string, SheetDrawing|MemoryDrawing> $map
     */
    private static function mapDrawingToCellRange(BaseDrawing $drawing, array &$map): void
    {
        $coordStr = \trim($drawing->getCoordinates());
        if ($coordStr === '') {
            return;
        }
        try {
            $from = Coordinate::indexesFromString($coordStr);
        } catch (\Throwable $e) {
            return;
        }
        $fromCol = (int) $from[0];
        $fromRow = (int) $from[1];
        $toCoord = \trim((string) $drawing->getCoordinates2());
        if ($toCoord === '') {
            $toCol = $fromCol;
            $toRow = $fromRow;
        } else {
            try {
                $to = Coordinate::indexesFromString($toCoord);
                $toCol = (int) $to[0];
                $toRow = (int) $to[1];
            } catch (\Throwable $e) {
                $toCol = $fromCol;
                $toRow = $fromRow;
            }
        }
        $minCol = \min($fromCol, $toCol);
        $maxCol = \max($fromCol, $toCol);
        $minRow = \min($fromRow, $toRow);
        $maxRow = \max($fromRow, $toRow);
        /** @var int $maxSpan 防止异常锚点导致巨量循环 */
        $maxSpan = 200;
        if (($maxCol - $minCol + 1) * ($maxRow - $minRow + 1) > 2000) {
            $maxCol = \min($maxCol, $minCol + $maxSpan - 1);
            $maxRow = \min($maxRow, $minRow + $maxSpan - 1);
        }
        for ($rr = $minRow; $rr <= $maxRow; $rr++) {
            for ($cc = $minCol; $cc <= $maxCol; $cc++) {
                $addr = \strtoupper(Coordinate::stringFromColumnIndex($cc) . $rr);
                if (!isset($map[$addr])) {
                    $map[$addr] = $drawing;
                }
            }
        }
    }

    /**
     * 读取 SheetDrawing 路径（含 zip://、data:URL），兼容 Windows 下 fopen 失败的情况。
     */
    private static function readDrawingPathBinary(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        if (\preg_match('#^data:image/#', $path) === 1) {
            $commaPos = \strpos($path, ',');
            if ($commaPos !== false) {
                $meta = \substr($path, 0, $commaPos);
                $payload = \substr($path, $commaPos + 1);
                if (\stripos($meta, ';base64') !== false) {
                    $decoded = \base64_decode($payload, true);
                    if ($decoded !== false && $decoded !== '') {
                        return $decoded;
                    }
                } else {
                    $decoded = \rawurldecode($payload);
                    if ($decoded !== '') {
                        return $decoded;
                    }
                }
            }
            $raw = @\file_get_contents($path);

            return ($raw !== false && $raw !== '') ? $raw : null;
        }
        $in = @\fopen($path, 'rb');
        if ($in !== false) {
            $data = \stream_get_contents($in);
            \fclose($in);
            if ($data !== false && $data !== '') {
                return $data;
            }
        }
        $raw = @\file_get_contents($path);

        return ($raw !== false && $raw !== '') ? $raw : null;
    }

    /**
     * @param BaseDrawing|MemoryDrawing|SheetDrawing $drawing 含 Office「单元格内图片」绑定的 Drawing
     */
    private static function exportDrawingToTemp($drawing): ?string
    {
        if ($drawing instanceof MemoryDrawing) {
            $res = $drawing->getImageResource();
            if ($res === null) {
                return null;
            }
            \ob_start();
            \call_user_func($drawing->getRenderingFunction(), $res);
            $data = \ob_get_clean();
            if ($data === false || $data === '') {
                return null;
            }
            $ext = 'png';
            $mime = $drawing->getMimeType();
            if ($mime === MemoryDrawing::MIMETYPE_JPEG) {
                $ext = 'jpg';
            } elseif ($mime === MemoryDrawing::MIMETYPE_GIF) {
                $ext = 'gif';
            }
            $tmp = ProductStyleImportService::createTempImagePath($ext);
            \file_put_contents($tmp, $data);

            return $tmp;
        }
        if ($drawing instanceof SheetDrawing) {
            $path = $drawing->getPath();
            if ($path === '') {
                return null;
            }
            $data = self::readDrawingPathBinary($path);
            if ($data === null || $data === '') {
                return null;
            }
            $ext = $drawing->getExtension();
            if ($ext === '') {
                $ext = 'png';
            }
            $tmp = ProductStyleImportService::createTempImagePath($ext);
            \file_put_contents($tmp, $data);

            return $tmp;
        }

        return null;
    }
}
