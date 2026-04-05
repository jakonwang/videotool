<?php
declare(strict_types=1);

namespace app\service;

use app\model\ProductStyleImportTask;
use SplFileObject;
use think\facade\Config;
use think\facade\Log;

/**
 * 寻款 CSV / Excel 异步导入：按请求逐行处理（前端轮询 tick），避免单次 HTTP 超时。
 */
class ProductStyleImportTaskRunner
{
    private const MAX_SUCCESS_ROWS = 5000;

    private const MAX_LOG_ENTRIES = 200;

    private const MAX_SKIP_EMPTY = 500;

    public static function bumpMemoryAndTime(): void
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
     * @throws \Throwable
     */
    public static function createFromUploadedFile(string $tmpPath, string $ext): int
    {
        $ext = strtolower($ext);
        if (!\in_array($ext, ['csv', 'txt', 'xlsx', 'xls', 'xlsm'], true)) {
            throw new \InvalidArgumentException('异步导入仅支持 .csv / .txt / .xlsx / .xls / .xlsm');
        }
        if (!\is_readable($tmpPath)) {
            throw new \RuntimeException('无法读取上传文件');
        }

        $dir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'style_import_tasks';
        if (!\is_dir($dir) && !@\mkdir($dir, 0755, true) && !\is_dir($dir)) {
            throw new \RuntimeException('无法创建任务目录');
        }

        $id = (int) \think\facade\Db::name('product_style_import_tasks')->insertGetId([
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
            'vision_described_count' => 0,
            'google_synced_count' => 0,
            'google_failed_count' => 0,
            'logs_json' => '[]',
            'error_message' => '',
        ]);

        $rel = 'style_import_tasks' . '/' . $id . '.' . $ext;
        $dest = root_path() . 'runtime' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (!@\copy($tmpPath, $dest)) {
            \think\facade\Db::name('product_style_import_tasks')->where('id', $id)->delete();
            throw new \RuntimeException('无法保存任务文件');
        }

        \think\facade\Db::name('product_style_import_tasks')->where('id', $id)->update([
            'file_path' => $rel,
        ]);

        return $id;
    }

    public static function snapshot(int $taskId): ?array
    {
        $task = ProductStyleImportTask::find($taskId);
        if (!$task) {
            return null;
        }

        return self::formatSnapshot($task);
    }

    /**
     * 处理下一行（或完成初始化），返回当前快照。
     *
     * @return array<string, mixed>
     */
    public static function tick(int $taskId): array
    {
        self::bumpMemoryAndTime();
        $task = ProductStyleImportTask::find($taskId);
        if (!$task) {
            return ['_error' => '任务不存在'];
        }

        if (\in_array($task->status, ['completed', 'failed'], true)) {
            return self::formatSnapshot($task);
        }

        $abs = self::absolutePath($task);
        if ($abs === null || !\is_file($abs)) {
            $task->status = 'failed';
            $task->error_message = '任务文件丢失';
            $task->save();
            self::appendLog($task, '任务文件丢失');

            return self::formatSnapshot($task);
        }

        try {
            return self::doTick($task, $abs);
        } catch (\Throwable $e) {
            Log::error('ProductStyleImportTaskRunner tick: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            $task->status = 'failed';
            $task->error_message = $e->getMessage();
            $task->save();
            self::appendLog($task, '异常：' . mb_substr($e->getMessage(), 0, 200));

            return self::formatSnapshot($task);
        }
    }

    private static function doTick(ProductStyleImportTask $task, string $absPath): array
    {
        $ext = \strtolower((string) $task->file_ext);
        if (\in_array($ext, ['xlsx', 'xls', 'xlsm'], true)) {
            return self::doTickExcel($task, $absPath);
        }

        return self::doTickCsv($task, $absPath);
    }

    private static function doTickCsv(ProductStyleImportTask $task, string $absPath): array
    {
        $publicRoot = root_path() . 'public';
        if ($task->status === 'pending') {
            $task->status = 'running';
            $task->save();
        }

        $file = new SplFileObject($absPath, 'r');
        $file->setFlags(SplFileObject::READ_CSV);

        if (!(int) $task->header_resolved) {
            self::resolveHeaderAndTotal($task, $file);
            $task = ProductStyleImportTask::find((int) $task->id) ?? $task;
            if ($task->status === 'failed') {
                return self::formatSnapshot($task);
            }
            if ((int) $task->total_rows === 0) {
                self::finalizeCompleted($task);

                return self::formatSnapshot($task);
            }
        }

        if ($task->status === 'completed') {
            return self::formatSnapshot($task);
        }

        $headerMap = \json_decode((string) $task->header_json, true);
        if (!\is_array($headerMap)) {
            $task->status = 'failed';
            $task->error_message = '表头状态损坏';
            $task->save();

            return self::formatSnapshot($task);
        }

        $ci = $headerMap['code'];
        $ii = $headerMap['image'];
        $hi = $headerMap['hot'] ?? null;

        $visionOn = VisionOpenAIConfig::get()['describe_on_import'];
        $usleepMicro = (int) (Config::get('product_search.import_ai_usleep_microseconds') ?? 200000);
        if ($usleepMicro < 0) {
            $usleepMicro = 0;
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

            $row = \array_map(static function ($c) {
                return \trim((string) $c);
            }, $row);
            if ($row === [null] || (\count($row) === 1 && $row[0] === '')) {
                $task->line_idx = $lineIdx + 1;
                $task->save();
                $skipped++;

                continue;
            }

            if (!isset($row[$ci], $row[$ii])) {
                $task->failed_count = (int) $task->failed_count + 1;
                $task->processed_rows = (int) $task->processed_rows + 1;
                $task->line_idx = $lineIdx + 1;
                $task->save();
                self::appendLog($task, '第' . ($lineIdx + 1) . '行：列不完整，已跳过');

                return self::formatSnapshot($task);
            }

            $code = \trim((string) $row[$ci]);
            $imgRaw = (string) $row[$ii];
            $hot = $hi !== null && isset($row[$hi]) ? \trim((string) $row[$hi]) : '';
            if ($code === '' || $imgRaw === '') {
                $task->line_idx = $lineIdx + 1;
                $task->save();
                $skipped++;

                continue;
            }

            $rowLabel = $lineIdx + 1;
            $resolved = ProductStyleImportService::resolveImage($imgRaw, $publicRoot);
            if (!$resolved['ok'] || $resolved['temp'] === '') {
                $task->failed_count = (int) $task->failed_count + 1;
                $task->processed_rows = (int) $task->processed_rows + 1;
                $task->line_idx = $lineIdx + 1;
                $task->save();
                self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . '（第' . $rowLabel . '行）图片无效，已跳过');

                return self::formatSnapshot($task);
            }

            $vec = ProductStyleEmbeddingService::embedFile($resolved['temp']);
            if (!\is_array($vec)) {
                if (\strpos($resolved['temp'], 'style_import') !== false && \is_file($resolved['temp'])) {
                    @\unlink($resolved['temp']);
                }
                $task->failed_count = (int) $task->failed_count + 1;
                $task->processed_rows = (int) $task->processed_rows + 1;
                $task->line_idx = $lineIdx + 1;
                $task->save();
                self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . '（第' . $rowLabel . '行）特征提取失败');

                return self::formatSnapshot($task);
            }

            $aiDesc = null;
            if ($visionOn) {
                try {
                    $aiDesc = ProductStyleVisionDescribeService::describeForImport($resolved['temp']);
                    if ($aiDesc !== null && $aiDesc !== '') {
                        $task->vision_described_count = (int) $task->vision_described_count + 1;
                    }
                } catch (\Throwable $e) {
                    Log::warning('import AI describe: ' . $e->getMessage());
                    self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . ' AI 识别异常（已跳过描述）：' . mb_substr($e->getMessage(), 0, 80));
                }
                if ($usleepMicro > 0) {
                    \usleep($usleepMicro);
                }
            }

            $u = ProductStyleIndexRowService::upsertStyleItem($code, $resolved['ref'], $hot, $vec, $aiDesc);
            ProductStyleIndexRowService::syncProductAiDescription($code, $aiDesc);
            $g = ProductStyleIndexRowService::syncGoogleProductSearchIndex($code, $resolved['temp']);
            if ($g['action'] === 'synced') {
                $task->google_synced_count = (int) $task->google_synced_count + 1;
            } elseif ($g['action'] === 'failed') {
                $task->google_failed_count = (int) $task->google_failed_count + 1;
            }

            $picName = ProductStyleAliyunQueueService::makePicName($resolved['ref'], $resolved['temp']);
            ProductStyleAliyunQueueService::enqueue($code, $resolved['temp'], $picName, $hot);
            if (\strpos($resolved['temp'], 'style_import') !== false && \is_file($resolved['temp'])) {
                @\unlink($resolved['temp']);
            }

            if ($u['inserted']) {
                $task->inserted_count = (int) $task->inserted_count + 1;
            } else {
                $task->updated_count = (int) $task->updated_count + 1;
            }
            $task->processed_rows = (int) $task->processed_rows + 1;
            $task->line_idx = $lineIdx + 1;
            $task->save();

            $st = $u['inserted'] ? '新增' : '更新';
            $aiSt = ($visionOn && $aiDesc !== null && $aiDesc !== '') ? '，AI 指纹已写入' : '';
            self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . '（第' . $rowLabel . '行）' . $st . '成功' . $aiSt);

            if ((int) $task->processed_rows % 5 === 0) {
                \gc_collect_cycles();
            }

            return self::formatSnapshot($task);
        }

        self::appendLog($task, '连续空行过多，已中止');
        $task->status = 'failed';
        $task->error_message = '文件异常：空行过多';
        $task->save();

        return self::formatSnapshot($task);
    }

    private static function doTickExcel(ProductStyleImportTask $task, string $absPath): array
    {
        $publicRoot = root_path() . 'public';
        if ($task->status === 'pending') {
            $task->status = 'running';
            $task->save();
        }

        if (!(int) $task->header_resolved) {
            self::initExcelTask($task, $absPath);
            $task = ProductStyleImportTask::find((int) $task->id) ?? $task;
            if ($task->status === 'failed') {
                return self::formatSnapshot($task);
            }
            if ((int) $task->total_rows === 0) {
                self::finalizeCompleted($task);

                return self::formatSnapshot($task);
            }
        }

        if ($task->status === 'completed') {
            return self::formatSnapshot($task);
        }

        $meta = \json_decode((string) $task->header_json, true);
        if (!\is_array($meta) || empty($meta['excel'])) {
            $task->status = 'failed';
            $task->error_message = 'Excel 任务状态损坏';
            $task->save();

            return self::formatSnapshot($task);
        }
        $highestRow = (int) ($meta['highest_row'] ?? 1);

        $visionOn = VisionOpenAIConfig::get()['describe_on_import'];
        $usleepMicro = (int) (Config::get('product_search.import_ai_usleep_microseconds') ?? 200000);
        if ($usleepMicro < 0) {
            $usleepMicro = 0;
        }

        $skipped = 0;
        while ($skipped < self::MAX_SKIP_EMPTY) {
            if ((int) $task->inserted_count + (int) $task->updated_count >= self::MAX_SUCCESS_ROWS) {
                self::appendLog($task, '已达单任务成功条数上限 ' . self::MAX_SUCCESS_ROWS . '，已结束');
                self::finalizeCompleted($task);

                return self::formatSnapshot($task);
            }

            $lineIdx = (int) $task->line_idx;
            if ($lineIdx > $highestRow) {
                self::finalizeCompleted($task);

                return self::formatSnapshot($task);
            }

            $rec = ProductStyleXlsxImportService::readDataRowAt($absPath, $lineIdx);
            $task->line_idx = $lineIdx + 1;
            $task->processed_rows = \min((int) $task->total_rows, \max(0, (int) $task->line_idx - 2));
            $task->save();

            if ($rec === null) {
                $skipped++;

                continue;
            }

            $rowIndex = (int) $rec['row'];
            $code = $rec['code'];
            $imgTemp = $rec['imageTemp'];
            $imgRaw = $rec['imageRaw'];
            $hot = $rec['hot'];

            if ($code === '') {
                return self::formatSnapshot($task);
            }

            if (($imgTemp === null || !\is_file($imgTemp) || !\is_readable($imgTemp)) && \trim($imgRaw) === '') {
                $task->failed_count = (int) $task->failed_count + 1;
                $task->save();
                self::appendLog($task, '第' . $rowIndex . '行：图片列为空且无嵌入图');

                return self::formatSnapshot($task);
            }

            $excelEmbedSource = null;
            if ($imgTemp !== null && \is_file($imgTemp) && \is_readable($imgTemp)) {
                $resolved = ['ref' => '(Excel嵌入图)', 'temp' => $imgTemp, 'ok' => true];
                $excelEmbedSource = $imgTemp;
            } else {
                $resolved = ProductStyleImportService::resolveImage($imgRaw, $publicRoot);
            }
            if (!$resolved['ok'] || $resolved['temp'] === '') {
                $task->failed_count = (int) $task->failed_count + 1;
                $task->save();
                self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . '（第' . $rowIndex . '行）图片无法解析');

                return self::formatSnapshot($task);
            }

            $vec = ProductStyleEmbeddingService::embedFile($resolved['temp']);
            if (!\is_array($vec)) {
                if (\strpos($resolved['temp'], 'style_import') !== false && \is_file($resolved['temp'])) {
                    @\unlink($resolved['temp']);
                }
                $task->failed_count = (int) $task->failed_count + 1;
                $task->save();
                self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . '（第' . $rowIndex . '行）特征提取失败');

                return self::formatSnapshot($task);
            }

            $imageRef = $resolved['ref'];
            if ($excelEmbedSource !== null && \is_file($excelEmbedSource)) {
                $saved = ProductStyleImportService::persistStyleImageToPublic($excelEmbedSource, $publicRoot);
                if ($saved !== null) {
                    $imageRef = $saved;
                }
            }

            $aiDesc = null;
            if ($visionOn) {
                try {
                    $aiDesc = ProductStyleVisionDescribeService::describeForImport($resolved['temp']);
                    if ($aiDesc !== null && $aiDesc !== '') {
                        $task->vision_described_count = (int) $task->vision_described_count + 1;
                    }
                } catch (\Throwable $e) {
                    Log::warning('import AI describe: ' . $e->getMessage());
                    self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . ' AI 异常（已跳过描述）：' . \mb_substr($e->getMessage(), 0, 80));
                }
                if ($usleepMicro > 0) {
                    \usleep($usleepMicro);
                }
            }

            $u = ProductStyleIndexRowService::upsertStyleItem($code, $imageRef, $hot, $vec, $aiDesc);
            ProductStyleIndexRowService::syncProductAiDescription($code, $aiDesc);
            $g = ProductStyleIndexRowService::syncGoogleProductSearchIndex($code, $resolved['temp']);
            if ($g['action'] === 'synced') {
                $task->google_synced_count = (int) $task->google_synced_count + 1;
            } elseif ($g['action'] === 'failed') {
                $task->google_failed_count = (int) $task->google_failed_count + 1;
            }

            $picName = ProductStyleAliyunQueueService::makePicName($imageRef, $resolved['temp']);
            ProductStyleAliyunQueueService::enqueue($code, $resolved['temp'], $picName, $hot);
            if (\strpos($resolved['temp'], 'style_import') !== false && \is_file($resolved['temp'])) {
                @\unlink($resolved['temp']);
            }

            if ($u['inserted']) {
                $task->inserted_count = (int) $task->inserted_count + 1;
            } else {
                $task->updated_count = (int) $task->updated_count + 1;
            }
            $task->save();

            $st = $u['inserted'] ? '新增' : '更新';
            $aiSt = ($visionOn && $aiDesc !== null && $aiDesc !== '') ? '，AI 指纹已写入' : '';
            self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . '（第' . $rowIndex . '行）' . $st . '成功' . $aiSt);

            if (((int) $task->inserted_count + (int) $task->updated_count) % 5 === 0) {
                \gc_collect_cycles();
            }

            return self::formatSnapshot($task);
        }

        self::appendLog($task, 'Excel 连续空行过多，已中止');
        $task->status = 'failed';
        $task->error_message = '文件异常：空行过多';
        $task->save();

        return self::formatSnapshot($task);
    }

    private static function initExcelTask(ProductStyleImportTask $task, string $absPath): void
    {
        if (!\class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            $task->status = 'failed';
            $task->error_message = '未安装 PhpSpreadsheet，请执行 composer install';
            $task->save();
            self::appendLog($task, '未安装 Excel 解析依赖');

            return;
        }
        $scan = ProductStyleXlsxImportService::scanSheetMeta($absPath);
        if (!$scan['ok']) {
            $task->status = 'failed';
            $task->error_message = (string) ($scan['error'] ?? 'Excel 无法读取');
            $task->save();
            self::appendLog($task, $task->error_message);

            return;
        }
        $hr = (int) $scan['highest_row'];
        $task->header_resolved = 1;
        $task->header_json = \json_encode(['excel' => 1, 'highest_row' => $hr], JSON_UNESCAPED_UNICODE);
        $task->total_rows = \max(0, $hr - 1);
        $task->line_idx = 2;
        $task->processed_rows = 0;
        $task->use_default_header = 0;
        $task->save();
        self::appendLog($task, 'Excel 已就绪，数据行 2～' . $hr . '（共 ' . $task->total_rows . ' 行）');
    }

    /**
     * @return array{code:int, image:int, hot:?int}|null
     */
    private static function detectHeaderRow(array $row): ?array
    {
        return ProductStyleImportService::mapHeader($row);
    }

    /**
     * @param SplFileObject $file
     */
    private static function resolveHeaderAndTotal(ProductStyleImportTask $task, $file): void
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
        $first = \array_map(static function ($c) {
            return \trim((string) $c);
        }, $first);

        $detected = self::detectHeaderRow($first);
        if ($detected !== null) {
            $task->header_json = \json_encode($detected, JSON_UNESCAPED_UNICODE);
            $task->header_resolved = 1;
            $task->use_default_header = 0;
            $task->line_idx = 1;
        } else {
            if (!isset($first[0], $first[1])) {
                $task->status = 'failed';
                $task->error_message = 'CSV 首行需为表头（含「产品编号」「图片」列），或前两列为编号+图片';
                $task->save();
                self::appendLog($task, '无法解析表头');

                return;
            }
            $task->header_json = \json_encode(['code' => 0, 'image' => 1, 'hot' => 2], JSON_UNESCAPED_UNICODE);
            $task->header_resolved = 1;
            $task->use_default_header = 1;
            $task->line_idx = 0;
        }

        $headerMap = \json_decode((string) $task->header_json, true);
        if (!\is_array($headerMap)) {
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
     * @param array{code:int, image:int, hot:?int} $headerMap
     */
    private static function countSubstantiveRows(SplFileObject $file, int $startLine, array $headerMap): int
    {
        $ci = $headerMap['code'];
        $ii = $headerMap['image'];
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
            $row = \array_map(static function ($c) {
                return \trim((string) $c);
            }, $row);
            if ($row === [null] || (\count($row) === 1 && $row[0] === '')) {
                $line++;

                continue;
            }
            if (!isset($row[$ci], $row[$ii])) {
                $line++;

                continue;
            }
            $code = \trim((string) $row[$ci]);
            $img = \trim((string) $row[$ii]);
            if ($code !== '' && $img !== '') {
                $n++;
            }
            $line++;
        }

        return $n;
    }

    private static function finalizeCompleted(ProductStyleImportTask $task): void
    {
        if ($task->status === 'completed') {
            return;
        }
        try {
            $aliyunSync = ProductStyleAliyunQueueService::drain(800, 300);
            self::appendLog($task, '本批阿里云同步：成功 ' . ($aliyunSync['ok'] ?? 0) . ' 失败 ' . ($aliyunSync['fail'] ?? 0));
        } catch (\Throwable $e) {
            Log::warning('import finalize drain: ' . $e->getMessage());
            self::appendLog($task, '阿里云队列收尾：' . mb_substr($e->getMessage(), 0, 120));
        }
        $task->status = 'completed';
        $task->save();
    }

    private static function absolutePath(ProductStyleImportTask $task): ?string
    {
        $rel = \trim((string) $task->file_path);
        if ($rel === '') {
            return null;
        }
        $rel = \str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);

        return root_path() . 'runtime' . DIRECTORY_SEPARATOR . $rel;
    }

    /**
     * @param list<string|null>|false $row
     * @return list<string|null>
     */
    private static function stripBomRow($row): array
    {
        if ($row === false || !\is_array($row)) {
            return [];
        }
        if (isset($row[0])) {
            $row[0] = (string) \preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
        }

        return $row;
    }

    private static function appendLog(ProductStyleImportTask $task, string $message): void
    {
        $arr = [];
        if ($task->logs_json !== null && $task->logs_json !== '') {
            $arr = \json_decode((string) $task->logs_json, true) ?: [];
        }
        $arr[] = ['t' => \date('H:i:s'), 'm' => $message];
        if (\count($arr) > self::MAX_LOG_ENTRIES) {
            $arr = \array_slice($arr, -self::MAX_LOG_ENTRIES);
        }
        $task->logs_json = \json_encode($arr, JSON_UNESCAPED_UNICODE);
        $task->save();
    }

    private static function formatSnapshot(ProductStyleImportTask $task): array
    {
        $logs = [];
        if ($task->logs_json !== null && $task->logs_json !== '') {
            $logs = \json_decode((string) $task->logs_json, true) ?: [];
        }
        $total = (int) $task->total_rows;
        $processed = (int) $task->processed_rows;
        $pct = 0;
        if ($total > 0) {
            $pct = (int) \min(100, \round(100 * $processed / $total));
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
            'vision_described' => (int) $task->vision_described_count,
            'google_synced' => (int) $task->google_synced_count,
            'google_failed' => (int) $task->google_failed_count,
            'logs' => $logs,
            'error_message' => (string) ($task->error_message ?? ''),
            'aliyun_pending' => ProductStyleAliyunQueueService::pendingCount(),
            'done' => \in_array($task->status, ['completed', 'failed'], true),
        ];
    }
}
