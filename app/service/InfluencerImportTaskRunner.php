<?php
declare(strict_types=1);

namespace app\service;

use app\model\InfluencerImportTask;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use SplFileObject;
use think\facade\Db;
use think\facade\Log;

/**
 * 达人名录 CSV / Excel 异步导入（tiktok_id 为 @handle）
 */
class InfluencerImportTaskRunner
{
    private const MAX_SUCCESS_ROWS = 10000;

    private const MAX_LOG_ENTRIES = 200;

    private const MAX_SKIP_EMPTY = 500;

    private static function taskDir(): string
    {
        $dir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'influencer_import_tasks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public static function bumpMemoryAndTime(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        @ini_set('max_execution_time', '0');
        $cur = (string) ini_get('memory_limit');
        if ($cur !== '-1' && $cur !== '') {
            @ini_set('memory_limit', '512M');
        }
    }

    /**
     * @throws \Throwable
     */
    public static function createFromUploadedFile(string $tmpPath, string $ext): int
    {
        $ext = strtolower($ext);
        if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls', 'xlsm'], true)) {
            throw new \InvalidArgumentException('仅支持 .csv / .txt / .xlsx / .xls / .xlsm');
        }
        if (!is_readable($tmpPath)) {
            throw new \RuntimeException('无法读取上传文件');
        }

        self::taskDir();

        $id = (int) Db::name('influencer_import_tasks')->insertGetId([
            'status' => 'pending',
            'file_path' => '',
            'file_ext' => $ext,
            'total_rows' => 0,
            'processed_rows' => 0,
            'line_idx' => 0,
            'header_resolved' => 0,
            'header_json' => '',
            'use_default_header' => 0,
            'inserted_count' => 0,
            'updated_count' => 0,
            'failed_count' => 0,
            'logs_json' => '[]',
            'error_message' => '',
        ]);

        $rel = 'influencer_import_tasks' . '/' . $id . '.' . $ext;
        $dest = root_path() . 'runtime' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!@copy($tmpPath, $dest)) {
            Db::name('influencer_import_tasks')->where('id', $id)->delete();
            throw new \RuntimeException('无法保存任务文件');
        }

        Db::name('influencer_import_tasks')->where('id', $id)->update([
            'file_path' => $rel,
        ]);

        return $id;
    }

    public static function snapshot(int $taskId): ?array
    {
        $task = InfluencerImportTask::find($taskId);
        if (!$task) {
            return null;
        }

        return self::formatSnapshot($task);
    }

    /**
     * @return array<string, mixed>
     */
    public static function tick(int $taskId): array
    {
        self::bumpMemoryAndTime();
        $task = InfluencerImportTask::find($taskId);
        if (!$task) {
            return ['_error' => '任务不存在'];
        }

        if (in_array($task->status, ['completed', 'failed'], true)) {
            return self::formatSnapshot($task);
        }

        $abs = self::absolutePath($task);
        if ($abs === null || !is_file($abs)) {
            $task->status = 'failed';
            $task->error_message = '任务文件丢失';
            $task->save();
            self::appendLog($task, '任务文件丢失');

            return self::formatSnapshot($task);
        }

        try {
            $ext = strtolower((string) $task->file_ext);
            if (in_array($ext, ['xlsx', 'xls', 'xlsm'], true) && !(int) $task->header_resolved) {
                return self::convertExcelAndResolveHeader($task, $abs);
            }
            $task = InfluencerImportTask::find((int) $task->id) ?? $task;
            $abs2 = self::absolutePath($task);
            if ($abs2 === null || !is_file($abs2)) {
                $task->status = 'failed';
                $task->error_message = '任务文件丢失';
                $task->save();

                return self::formatSnapshot($task);
            }

            return self::doTickCsv($task, $abs2);
        } catch (\Throwable $e) {
            Log::error('InfluencerImportTaskRunner tick: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $task->status = 'failed';
            $task->error_message = $e->getMessage();
            $task->save();
            $loc = $e->getFile() . ':' . $e->getLine();
            self::appendLog($task, '异常：' . mb_substr($e->getMessage(), 0, 200) . ' @ ' . $loc);

            return self::formatSnapshot($task);
        }
    }

    /**
     * Excel 首遍：展平为 UTF-8 CSV 再按 CSV 逻辑导入
     *
     * @throws \Throwable
     */
    private static function convertExcelAndResolveHeader(InfluencerImportTask $task, string $absPath): array
    {
        if ($task->status === 'pending') {
            $task->status = 'running';
            $task->save();
        }

        $spreadsheet = IOFactory::load($absPath);
        $sheet = $spreadsheet->getActiveSheet();
        $maxRow = (int) $sheet->getHighestDataRow();
        if ($maxRow < 1) {
            $task->status = 'failed';
            $task->error_message = 'Excel 无数据';
            $task->save();
            self::appendLog($task, 'Excel 无数据');

            return self::formatSnapshot($task);
        }

        $highestCol = $sheet->getHighestDataColumn();
        $maxColIndex = Coordinate::columnIndexFromString($highestCol);

        $firstRow = [];
        for ($c = 1; $c <= $maxColIndex; $c++) {
            $addr = Coordinate::stringFromColumnIndex($c) . '1';
            $firstRow[] = trim((string) $sheet->getCell($addr)->getValue());
        }
        $detected = InfluencerService::mapHeader($firstRow);
        $useDefaultHeader = false;
        if ($detected === null) {
            if (count($firstRow) < 1) {
                $task->status = 'failed';
                $task->error_message = '无法解析表头';
                $task->save();
                self::appendLog($task, 'Excel 无有效首行');

                return self::formatSnapshot($task);
            }
            $detected = [
                'tiktok' => 0,
                'nickname' => isset($firstRow[1]) ? 1 : null,
                'avatar' => null,
                'followers' => null,
                'contact' => null,
                'whatsapp' => null,
                'zalo' => null,
                'region' => null,
                'status' => null,
            ];
            $useDefaultHeader = true;
        }
        $task->use_default_header = $useDefaultHeader ? 1 : 0;

        $csvRel = 'influencer_import_tasks/' . (int) $task->id . '_flat.csv';
        $csvAbs = root_path() . 'runtime' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $csvRel);
        $fp = fopen($csvAbs, 'wb');
        if ($fp === false) {
            throw new \RuntimeException('无法创建临时 CSV');
        }
        fwrite($fp, "\xEF\xBB\xBF");

        $dataStart = $useDefaultHeader ? 1 : 2;
        $n = 0;
        if (!$useDefaultHeader) {
            fputcsv($fp, $firstRow);
        }
        for ($r = $dataStart; $r <= $maxRow; $r++) {
            $line = [];
            for ($c = 1; $c <= $maxColIndex; $c++) {
                $addr = Coordinate::stringFromColumnIndex($c) . (string) $r;
                $line[] = trim((string) $sheet->getCell($addr)->getValue());
            }
            $ti = $detected['tiktok'];
            if (!isset($line[$ti]) || InfluencerService::normalizeTiktokId((string) $line[$ti]) === null) {
                continue;
            }
            fputcsv($fp, $line);
            $n++;
        }
        fclose($fp);

        $task->file_path = $csvRel;
        $task->file_ext = 'csv';
        $task->header_json = json_encode($detected, JSON_UNESCAPED_UNICODE);
        $task->header_resolved = 1;
        $task->line_idx = $useDefaultHeader ? 0 : 1;
        $task->total_rows = $n;
        $task->save();
        self::appendLog($task, 'Excel 已转为 CSV，有效数据行约 ' . $n . ' 行');

        return self::formatSnapshot($task);
    }

    private static function doTickCsv(InfluencerImportTask $task, string $absPath): array
    {
        if ($task->status === 'pending') {
            $task->status = 'running';
            $task->save();
        }

        $file = new SplFileObject($absPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV);

        if (!(int) $task->header_resolved) {
            self::resolveHeaderAndTotal($task, $file);
            $task = InfluencerImportTask::find((int) $task->id) ?? $task;
            if ($task->status === 'failed') {
                return self::formatSnapshot($task);
            }
            if ((int) $task->total_rows === 0) {
                self::finalizeCompleted($task);

                return self::formatSnapshot($task);
            }

            return self::formatSnapshot($task);
        }

        if ($task->status === 'completed') {
            return self::formatSnapshot($task);
        }

        $headerMap = json_decode((string) $task->header_json, true);
        if (!is_array($headerMap) || !isset($headerMap['tiktok'])) {
            $task->status = 'failed';
            $task->error_message = '表头状态损坏';
            $task->save();

            return self::formatSnapshot($task);
        }

        $skipped = 0;
        while ($skipped < self::MAX_SKIP_EMPTY) {
            if ((int) $task->inserted_count + (int) $task->updated_count >= self::MAX_SUCCESS_ROWS) {
                self::appendLog($task, '已达单任务成功条数上限 ' . self::MAX_SUCCESS_ROWS . '，已结束');
                self::finalizeCompleted($task);

                return self::formatSnapshot($task);
            }

            $lineIdx = (int) $task->line_idx;
            $file->seek($lineIdx);
            if ($file->eof()) {
                self::finalizeCompleted($task);

                return self::formatSnapshot($task);
            }

            $row = $file->fgetcsv();
            if ($row === false) {
                $task->line_idx = $lineIdx + 1;
                $task->save();
                $skipped++;

                continue;
            }

            if ($lineIdx === 0) {
                $row = self::stripBomRow($row);
            }

            $row = array_map(static function ($c) {
                return trim((string) $c);
            }, $row);
            if ($row === [null] || (count($row) === 1 && $row[0] === '')) {
                $task->line_idx = $lineIdx + 1;
                $task->save();
                $skipped++;

                continue;
            }

            $ti = (int) $headerMap['tiktok'];
            if (!isset($row[$ti])) {
                $task->failed_count = (int) $task->failed_count + 1;
                $task->processed_rows = (int) $task->processed_rows + 1;
                $task->line_idx = $lineIdx + 1;
                $task->save();
                self::appendLog($task, '第' . ($lineIdx + 1) . '行：列不完整');

                return self::formatSnapshot($task);
            }

            if (InfluencerService::normalizeTiktokId((string) $row[$ti]) === null) {
                $task->line_idx = $lineIdx + 1;
                $task->save();
                $skipped++;

                continue;
            }

            $r = InfluencerService::upsertFromRow($headerMap, $row);
            $task->processed_rows = (int) $task->processed_rows + 1;
            $task->line_idx = $lineIdx + 1;
            if (!empty($r['inserted'])) {
                $task->inserted_count = (int) $task->inserted_count + 1;
            } elseif (!empty($r['updated'])) {
                $task->updated_count = (int) $task->updated_count + 1;
            } else {
                $task->failed_count = (int) $task->failed_count + 1;
            }
            $task->save();

            return self::formatSnapshot($task);
        }

        self::finalizeCompleted($task);

        return self::formatSnapshot($task);
    }

    /**
     * @param SplFileObject $file
     */
    private static function resolveHeaderAndTotal(InfluencerImportTask $task, $file): void
    {
        $file->seek(0);
        $first = $file->fgetcsv();
        if ($first === false || $first === [null]) {
            $task->status = 'failed';
            $task->error_message = '文件为空';
            $task->save();
            self::appendLog($task, '文件为空');

            return;
        }
        $first = self::stripBomRow($first);
        $first = array_map(static function ($c) {
            return trim((string) $c);
        }, $first);

        $detected = InfluencerService::mapHeader($first);
        if ($detected !== null) {
            $task->header_json = json_encode($detected, JSON_UNESCAPED_UNICODE);
            $task->header_resolved = 1;
            $task->use_default_header = 0;
            $task->line_idx = 1;
        } else {
            if (!isset($first[0])) {
                $task->status = 'failed';
                $task->error_message = 'CSV 首行需含 TikTok 用户名列，或首列为 @handle';
                $task->save();
                self::appendLog($task, '无法解析表头');

                return;
            }
            $task->header_json = json_encode([
                'tiktok' => 0,
                'nickname' => isset($first[1]) ? 1 : null,
                'avatar' => null,
                'followers' => null,
                'contact' => null,
                'whatsapp' => null,
                'zalo' => null,
                'region' => null,
                'status' => null,
            ], JSON_UNESCAPED_UNICODE);
            $task->header_resolved = 1;
            $task->use_default_header = 1;
            $task->line_idx = 0;
        }

        $headerMap = json_decode((string) $task->header_json, true);
        if (!is_array($headerMap)) {
            $task->status = 'failed';
            $task->error_message = '表头映射失败';
            $task->save();

            return;
        }

        $total = self::countSubstantiveRows($file, (int) $task->line_idx, $headerMap);
        $task->total_rows = $total;
        $task->save();
        self::appendLog($task, '已解析表头，数据行约 ' . $total . ' 行');
    }

    /**
     * @param array{tiktok:int, nickname?:?int, ...} $headerMap
     */
    private static function countSubstantiveRows(SplFileObject $file, int $startLine, array $headerMap): int
    {
        $ti = (int) $headerMap['tiktok'];
        $n = 0;
        $line = $startLine;
        while (true) {
            $file->seek($line);
            if ($file->eof()) {
                break;
            }
            $row = $file->fgetcsv();
            if ($row === false) {
                $line++;

                continue;
            }
            if ($line === $startLine && $startLine === 0) {
                $row = self::stripBomRow($row);
            }
            $row = array_map(static function ($c) {
                return trim((string) $c);
            }, $row);
            if ($row === [null] || (count($row) === 1 && $row[0] === '')) {
                $line++;

                continue;
            }
            if (!isset($row[$ti])) {
                $line++;

                continue;
            }
            if (InfluencerService::normalizeTiktokId((string) $row[$ti]) !== null) {
                $n++;
            }
            $line++;
        }

        return $n;
    }

    private static function finalizeCompleted(InfluencerImportTask $task): void
    {
        if ($task->status === 'completed') {
            return;
        }
        $task->status = 'completed';
        $task->save();
    }

    private static function absolutePath(InfluencerImportTask $task): ?string
    {
        $rel = trim((string) $task->file_path);
        if ($rel === '') {
            return null;
        }
        $rel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);

        return root_path() . 'runtime' . DIRECTORY_SEPARATOR . $rel;
    }

    /**
     * @param list<string|null>|false $row
     * @return list<string|null>
     */
    private static function stripBomRow($row): array
    {
        if ($row === false || !is_array($row)) {
            return [];
        }
        if (isset($row[0])) {
            $row[0] = (string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
        }

        return $row;
    }

    private static function appendLog(InfluencerImportTask $task, string $message): void
    {
        $arr = [];
        if ($task->logs_json !== null && $task->logs_json !== '') {
            $arr = json_decode((string) $task->logs_json, true) ?: [];
        }
        $arr[] = ['t' => date('H:i:s'), 'm' => $message];
        if (count($arr) > self::MAX_LOG_ENTRIES) {
            $arr = array_slice($arr, -self::MAX_LOG_ENTRIES);
        }
        $task->logs_json = json_encode($arr, JSON_UNESCAPED_UNICODE);
        $task->save();
    }

    private static function formatSnapshot(InfluencerImportTask $task): array
    {
        $logs = [];
        if ($task->logs_json !== null && $task->logs_json !== '') {
            $logs = json_decode((string) $task->logs_json, true) ?: [];
        }
        $total = (int) $task->total_rows;
        $processed = (int) $task->processed_rows;
        $pct = 0;
        if ($total > 0) {
            $pct = (int) min(100, round(100 * $processed / $total));
        } elseif ($task->status === 'completed') {
            $pct = 100;
        }

        return [
            'task_id' => (int) $task->id,
            'status' => (string) $task->status,
            'total_rows' => $total,
            'processed_rows' => $processed,
            'percent' => $pct,
            'inserted' => (int) $task->inserted_count,
            'updated' => (int) $task->updated_count,
            'failed' => (int) $task->failed_count,
            'logs' => $logs,
            'error_message' => (string) ($task->error_message ?? ''),
            'done' => in_array($task->status, ['completed', 'failed'], true),
        ];
    }
}
