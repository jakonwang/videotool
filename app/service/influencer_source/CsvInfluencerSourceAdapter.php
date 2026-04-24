<?php
declare(strict_types=1);

namespace app\service\influencer_source;

use PhpOffice\PhpSpreadsheet\IOFactory;

class CsvInfluencerSourceAdapter implements InfluencerSourceAdapterInterface
{
    /**
     * @param array<string, mixed> $options
     * @return array{headers:list<string>,rows:list<list<string>>}
     */
    public function parseRows(string $absPath, array $options = []): array
    {
        $ext = strtolower((string) ($options['ext'] ?? pathinfo($absPath, PATHINFO_EXTENSION)));
        if (in_array($ext, ['xlsx', 'xls', 'xlsm'], true)) {
            return $this->parseExcel($absPath, $options);
        }

        return $this->parseCsv($absPath, $options);
    }

    /**
     * @param array<string, mixed> $options
     * @return array{headers:list<string>,rows:list<list<string>>}
     */
    private function parseCsv(string $absPath, array $options): array
    {
        $maxRows = max(0, (int) ($options['max_rows'] ?? 0));
        $rows = [];
        $headers = [];

        $fh = fopen($absPath, 'rb');
        if ($fh === false) {
            throw new \RuntimeException('open_file_failed');
        }
        try {
            $first = fgets($fh);
            if ($first === false) {
                return ['headers' => [], 'rows' => []];
            }
            $delimiter = $this->detectDelimiter($first);
            rewind($fh);

            $lineNo = 0;
            while (($line = fgetcsv($fh, 0, $delimiter)) !== false) {
                $lineNo++;
                $line = $this->normalizeLine($line);
                if ($lineNo === 1) {
                    $headers = $line;
                    continue;
                }
                if ($this->isAllEmpty($line)) {
                    continue;
                }
                $rows[] = $line;
                if ($maxRows > 0 && count($rows) >= $maxRows) {
                    break;
                }
            }
        } finally {
            fclose($fh);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param array<string, mixed> $options
     * @return array{headers:list<string>,rows:list<list<string>>}
     */
    private function parseExcel(string $absPath, array $options): array
    {
        if (!class_exists(IOFactory::class)) {
            throw new \RuntimeException('phpspreadsheet_not_installed');
        }
        $maxRows = max(0, (int) ($options['max_rows'] ?? 0));
        $spreadsheet = IOFactory::load($absPath);
        try {
            $sheet = $spreadsheet->getSheet(0);
            $highestRow = (int) $sheet->getHighestDataRow();
            $highestCol = $sheet->getHighestDataColumn();
            $matrix = $sheet->rangeToArray('A1:' . $highestCol . $highestRow, null, true, false, false);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
        if (!is_array($matrix) || $matrix === []) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = $this->normalizeLine((array) ($matrix[0] ?? []));
        $rows = [];
        $count = count($matrix);
        for ($i = 1; $i < $count; $i++) {
            $line = $this->normalizeLine((array) $matrix[$i]);
            if ($this->isAllEmpty($line)) {
                continue;
            }
            $rows[] = $line;
            if ($maxRows > 0 && count($rows) >= $maxRows) {
                break;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function detectDelimiter(string $line): string
    {
        $line = (string) preg_replace('/^\xEF\xBB\xBF/', '', $line);
        $candidates = [',', ';', "\t", '|'];
        $best = ',';
        $max = -1;
        foreach ($candidates as $ch) {
            $n = substr_count($line, $ch);
            if ($n > $max) {
                $max = $n;
                $best = $ch;
            }
        }

        return $best;
    }

    /**
     * @param list<mixed> $line
     * @return list<string>
     */
    private function normalizeLine(array $line): array
    {
        $out = [];
        foreach ($line as $cell) {
            if ($cell === null) {
                $out[] = '';
                continue;
            }
            $v = trim((string) $cell);
            $v = (string) preg_replace('/^\xEF\xBB\xBF/', '', $v);
            $out[] = $v;
        }

        return $out;
    }

    /**
     * @param list<string> $line
     */
    private function isAllEmpty(array $line): bool
    {
        foreach ($line as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }
}

