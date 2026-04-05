<?php
declare(strict_types=1);

namespace app\service;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
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

            return self::extractDataRow($sheet, $drawingsByCell, $codeCol, $imgCol, $hotCol, $excelRow);
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
                $rec = self::extractDataRow($sheet, $drawingsByCell, $codeCol, $imgCol, $hotCol, $r);
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
     * @return array{row: int, code: string, hot: string, imageTemp: ?string, imageRaw: string}|null
     */
    private static function extractDataRow(
        Worksheet $sheet,
        array $drawingsByCell,
        int $codeCol,
        int $imgCol,
        ?int $hotCol,
        int $r
    ): ?array {
        $code = \trim((string) $sheet->getCell(self::cellAddr($codeCol, $r))->getFormattedValue());
        $imgRaw = \trim((string) $sheet->getCell(self::cellAddr($imgCol, $r))->getFormattedValue());
        $hot = '';
        if ($hotCol !== null) {
            $hot = \trim((string) $sheet->getCell(self::cellAddr($hotCol, $r))->getFormattedValue());
        }

        $imgCoord = \strtoupper(self::cellAddr($imgCol, $r));
        $imageTemp = null;
        if (isset($drawingsByCell[$imgCoord])) {
            $imageTemp = self::exportDrawingToTemp($drawingsByCell[$imgCoord]);
        }

        if ($code === '' && $imageTemp === null && $imgRaw === '') {
            return null;
        }

        return [
            'row' => $r,
            'code' => $code,
            'hot' => $hot,
            'imageTemp' => $imageTemp,
            'imageRaw' => $imgRaw,
        ];
    }

    /** @param positive-int $col 1-based 列号（A=1） */
    private static function cellAddr(int $col, int $row): string
    {
        return Coordinate::stringFromColumnIndex($col) . $row;
    }

    /**
     * @return array<string, SheetDrawing|MemoryDrawing>
     */
    private static function indexDrawingsByCell(Worksheet $sheet): array
    {
        $map = [];
        $add = static function ($drawing) use (&$map): void {
            $coord = self::normalizeCellAddress($drawing->getCoordinates());
            if ($coord === '' || isset($map[$coord])) {
                return;
            }
            $map[$coord] = $drawing;
        };
        if (\method_exists($sheet, 'getInCellDrawingCollection')) {
            foreach ($sheet->getInCellDrawingCollection() as $drawing) {
                $add($drawing);
            }
        }
        foreach ($sheet->getDrawingCollection() as $drawing) {
            $add($drawing);
        }

        return $map;
    }

    private static function normalizeCellAddress(string $coord): string
    {
        $coord = \str_replace('$', '', \trim($coord));

        return \strtoupper($coord);
    }

    /**
     * @param SheetDrawing|MemoryDrawing $drawing
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
            $in = @\fopen($path, 'rb');
            if ($in === false) {
                return null;
            }
            $data = \stream_get_contents($in);
            \fclose($in);
            if ($data === false || $data === '') {
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
