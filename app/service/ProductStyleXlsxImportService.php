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
     * @return \Generator<int, array{row: int, code: string, hot: string, imageTemp: ?string, imageRaw: string}>
     */
    public static function iterateRows(string $path): \Generator
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $drawingsByCell = self::indexDrawingsByCell($sheet);

        $highestColumn = $sheet->getHighestColumn();
        $headerMatrix = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false);
        $headerRow = $headerMatrix[0] ?? [];
        $headerCells = array_map(static function ($v) {
            return trim((string) ($v ?? ''));
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
            $code = trim((string) $sheet->getCell(self::cellAddr($codeCol, $r))->getFormattedValue());
            $imgRaw = trim((string) $sheet->getCell(self::cellAddr($imgCol, $r))->getFormattedValue());
            $hot = '';
            if ($hotCol !== null) {
                $hot = trim((string) $sheet->getCell(self::cellAddr($hotCol, $r))->getFormattedValue());
            }

            $imgCoord = strtoupper(self::cellAddr($imgCol, $r));
            $imageTemp = null;
            if (isset($drawingsByCell[$imgCoord])) {
                $imageTemp = self::exportDrawingToTemp($drawingsByCell[$imgCoord]);
            }

            if ($code === '' && $imageTemp === null && $imgRaw === '') {
                continue;
            }

            yield [
                'row' => $r,
                'code' => $code,
                'hot' => $hot,
                'imageTemp' => $imageTemp,
                'imageRaw' => $imgRaw,
            ];
        }

        $spreadsheet->disconnectWorksheets();
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
        // Office「图片在单元格内」走 inCell 集合；经典浮动图在 drawingCollection（inCell 优先）
        if (method_exists($sheet, 'getInCellDrawingCollection')) {
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
        $coord = str_replace('$', '', trim($coord));

        return strtoupper($coord);
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
            ob_start();
            call_user_func($drawing->getRenderingFunction(), $res);
            $data = ob_get_clean();
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
            file_put_contents($tmp, $data);

            return $tmp;
        }
        if ($drawing instanceof SheetDrawing) {
            $path = $drawing->getPath();
            if ($path === '') {
                return null;
            }
            $in = @fopen($path, 'rb');
            if ($in === false) {
                return null;
            }
            $data = stream_get_contents($in);
            fclose($in);
            if ($data === false || $data === '') {
                return null;
            }
            $ext = $drawing->getExtension();
            if ($ext === '') {
                $ext = 'png';
            }
            $tmp = ProductStyleImportService::createTempImagePath($ext);
            file_put_contents($tmp, $data);

            return $tmp;
        }

        return null;
    }
}
