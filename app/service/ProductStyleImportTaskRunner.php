<?php
declare(strict_types=1);

namespace app\service;

use app\model\ProductStyleImportTask;
use SplFileObject;
use think\facade\Config;
use think\facade\Log;

/**
 * 寻款 CSV / Excel 异步导入：按请求推进（前端轮询 tick），避免单次 HTTP 超时。
 * Excel 每行数据拆成两次 tick：先读行+解析图落盘 pending，再向量+豆包+入库。
 */
class ProductStyleImportTaskRunner
{
    private const MAX_SUCCESS_ROWS = 5000;

    private const MAX_LOG_ENTRIES = 200;

    private const MAX_SKIP_EMPTY = 500;

    /** Excel 异步：读行+解析图与「向量+豆包+入库」拆成两次 tick，避免单次 HTTP 长时间无响应、界面卡在 0/N */
    private static function excelPendingPath(int $taskId): string
    {
        $dir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'style_import_tasks';
        if (!\is_dir($dir)) {
            @\mkdir($dir, 0755, true);
        }

        return $dir . DIRECTORY_SEPARATOR . 'pending_' . $taskId . '.json';
    }

    /** Excel 行号 => 嵌入图站内路径（drawing_map_*.json） */
    private static function drawingMapPath(int $taskId): string
    {
        $dir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'style_import_tasks';
        if (!\is_dir($dir)) {
            @\mkdir($dir, 0755, true);
        }

        return $dir . DIRECTORY_SEPARATOR . 'drawing_map_' . $taskId . '.json';
    }

    private static function unlinkDrawingMap(int $taskId): void
    {
        $p = self::drawingMapPath($taskId);
        if (\is_file($p)) {
            @\unlink($p);
        }
    }

    /**
     * @return array<int, string>|null 行号 => /uploads/products/...
     */
    private static function loadDrawingRowMap(ProductStyleImportTask $task): ?array
    {
        $meta = \json_decode((string) $task->header_json, true);
        if (!\is_array($meta) || empty($meta['drawing_map_rel'])) {
            return null;
        }
        $rel = (string) $meta['drawing_map_rel'];
        $path = root_path() . 'runtime' . DIRECTORY_SEPARATOR . \str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
        if (!\is_file($path) || !\is_readable($path)) {
            return null;
        }
        $raw = @\file_get_contents($path);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = \json_decode($raw, true);
        if (!\is_array($data)) {
            return null;
        }
        $out = [];
        foreach ($data as $k => $v) {
            if (!\is_string($v) || $v === '') {
                continue;
            }
            $out[(int) $k] = $v;
        }

        return $out;
    }

    private static function unlinkExcelPending(int $taskId): void
    {
        $p = self::excelPendingPath($taskId);
        if (\is_file($p)) {
            @\unlink($p);
        }
    }

    /**
     * @param array{row:int, code:string, hot:string, resolved_ref:string, resolved_temp:string, excel_embed_source:?string, image_path_web?:?string} $payload
     */
    private static function writeExcelPending(int $taskId, array $payload): void
    {
        $p = self::excelPendingPath($taskId);
        $json = \json_encode(['v' => 1, 'payload' => $payload], JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('无法序列化 Excel 待处理行');
        }
        if (@\file_put_contents($p, $json) === false) {
            throw new \RuntimeException('无法写入 Excel 待处理文件');
        }
    }

    /**
     * @return array{row:int, code:string, hot:string, resolved_ref:string, resolved_temp:string, excel_embed_source:?string, image_path_web:?string}|null
     */
    private static function readExcelPending(int $taskId): ?array
    {
        $p = self::excelPendingPath($taskId);
        if (!\is_file($p) || !\is_readable($p)) {
            return null;
        }
        $raw = @\file_get_contents($p);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = \json_decode($raw, true);
        if (!\is_array($data) || ($data['v'] ?? 0) !== 1 || !isset($data['payload']) || !\is_array($data['payload'])) {
            return null;
        }
        $pl = $data['payload'];
        $row = (int) ($pl['row'] ?? 0);
        $code = (string) ($pl['code'] ?? '');
        $hot = (string) ($pl['hot'] ?? '');
        $resolvedRef = (string) ($pl['resolved_ref'] ?? '');
        $resolvedTemp = (string) ($pl['resolved_temp'] ?? '');
        $excelEmbed = isset($pl['excel_embed_source']) ? (string) $pl['excel_embed_source'] : null;
        if ($excelEmbed === '') {
            $excelEmbed = null;
        }
        $imagePathWeb = isset($pl['image_path_web']) ? (string) $pl['image_path_web'] : null;
        if ($imagePathWeb === '') {
            $imagePathWeb = null;
        }
        if ($row < 1 || $resolvedTemp === '') {
            return null;
        }

        return [
            'row' => $row,
            'code' => $code,
            'hot' => $hot,
            'resolved_ref' => $resolvedRef,
            'resolved_temp' => $resolvedTemp,
            'excel_embed_source' => $excelEmbed,
            'image_path_web' => $imagePathWeb,
        ];
    }

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
            self::unlinkExcelPending((int) $task->id);
            self::unlinkDrawingMap((int) $task->id);
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
            self::unlinkExcelPending((int) $task->id);
            self::unlinkDrawingMap((int) $task->id);
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

            return self::formatSnapshot($task);
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

        $visionOn = ProductStyleVisionDescribeService::shouldDescribeOnImport();
        $usleepMicro = (int) (Config::get('product_search.import_ai_usleep_microseconds') ?? 0);
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
            $rowLabel = $lineIdx + 1;

            if ($code === '' && \trim($imgRaw) === '') {
                $task->line_idx = $lineIdx + 1;
                $task->save();
                $skipped++;

                continue;
            }

            if ($code !== '' && \trim($imgRaw) === '') {
                $task->failed_count = (int) $task->failed_count + 1;
                $task->processed_rows = (int) $task->processed_rows + 1;
                $task->line_idx = $lineIdx + 1;
                $task->save();
                self::appendLog($task, '第' . $rowLabel . '行：有编号但图片列为空。**CSV/TXT 无法保存 Excel 单元格嵌入图**，请改用 **.xlsx/.xls/.xlsm** 上传，或在「图片」列填写 **http(s) 链接**、**站内路径**或 **Base64**。');

                return self::formatSnapshot($task);
            }

            if ($code === '') {
                $task->line_idx = $lineIdx + 1;
                $task->save();
                $skipped++;

                continue;
            }

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
                    } else {
                        self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . ' 未生成 AI 描述：请确认豆包已启用且已保存；查看 runtime/log 中 [volc_ark] 的 ark_error/body_snip；或命令行执行 php scripts/volc_ark_ping.php 做文本连通性测试');
                    }
                } catch (\Throwable $e) {
                    Log::warning('import AI describe: ' . $e->getMessage());
                    self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . ' AI 识别异常（已跳过描述）：' . mb_substr($e->getMessage(), 0, 80));
                }
                if ($usleepMicro > 0) {
                    \usleep($usleepMicro);
                }
            }

            $pathDbCsv = ProductStyleImportService::saveImportImageToProductsDir($resolved['temp'], $publicRoot);
            $u = ProductStyleIndexRowService::upsertStyleItem($code, $resolved['ref'], $hot, $vec, $aiDesc, $pathDbCsv);
            ProductStyleIndexRowService::syncProductAiDescription($code, $aiDesc);
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
            $aiSt = ($visionOn && $aiDesc !== null && $aiDesc !== '') ? '，AI 描述已写入' : '';
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

    /**
     * Excel 第二次 tick：向量 + 豆包 + 入库（与读行拆分，避免单次 HTTP 长时间无响应）。
     *
     * @return array<string, mixed>
     */
    private static function doTickExcelProcessPending(
        ProductStyleImportTask $task,
        string $publicRoot,
        bool $visionOn,
        int $usleepMicro
    ): array {
        $taskId = (int) $task->id;
        $pl = self::readExcelPending($taskId);
        if ($pl === null) {
            self::unlinkExcelPending($taskId);
            self::appendLog($task, 'Excel 待处理行数据无效，已清理');

            return self::formatSnapshot($task);
        }

        $rowIndex = $pl['row'];
        $code = $pl['code'];
        $hot = $pl['hot'];
        $resolvedTemp = $pl['resolved_temp'];
        $excelEmbedSource = $pl['excel_embed_source'];
        $imagePathWebPl = $pl['image_path_web'] ?? null;

        if (!\is_file($resolvedTemp) || !\is_readable($resolvedTemp)) {
            $task->failed_count = (int) $task->failed_count + 1;
            $task->save();
            self::appendLog($task, '[' . \date('H:i:s') . '] 第' . $rowIndex . '行：临时图片已失效');
            self::unlinkExcelPending($taskId);
            self::bumpExcelProcessedRows($task);

            return self::formatSnapshot($task);
        }

        self::appendLog($task, '[' . \date('H:i:s') . '] 第 ' . $rowIndex . ' 行：开始抽取向量' . ($visionOn ? '并调用豆包' : '') . '（首行 Python 冷启动可能较慢）');

        $vec = ProductStyleEmbeddingService::embedFile($resolvedTemp);
        if (!\is_array($vec)) {
            if (\strpos($resolvedTemp, 'style_import') !== false && \is_file($resolvedTemp)) {
                @\unlink($resolvedTemp);
            }
            $task->failed_count = (int) $task->failed_count + 1;
            $task->save();
            self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . '（第' . $rowIndex . '行）特征提取失败');
            self::unlinkExcelPending($taskId);
            self::bumpExcelProcessedRows($task);

            return self::formatSnapshot($task);
        }

        $imageRef = $pl['resolved_ref'];
        if ($excelEmbedSource !== null && \is_file($excelEmbedSource)) {
            $saved = ProductStyleImportService::persistStyleImageToPublic($excelEmbedSource, $publicRoot);
            if ($saved !== null) {
                $imageRef = $saved;
            }
        }

        $imagePathDb = ($imagePathWebPl !== null && $imagePathWebPl !== '') ? $imagePathWebPl : null;
        if ($imagePathDb === null) {
            $imagePathDb = ProductStyleImportService::saveImportImageToProductsDir($resolvedTemp, $publicRoot);
        }

        $aiDesc = null;
        if ($visionOn) {
            try {
                $aiDesc = ProductStyleVisionDescribeService::describeForImport($resolvedTemp);
                if ($aiDesc !== null && $aiDesc !== '') {
                    $task->vision_described_count = (int) $task->vision_described_count + 1;
                } else {
                    self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . ' 未生成 AI 描述：请确认豆包已启用且已保存；查看 runtime/log 中 [volc_ark] 的 ark_error/body_snip；或命令行执行 php scripts/volc_ark_ping.php 做文本连通性测试');
                }
            } catch (\Throwable $e) {
                Log::warning('import AI describe: ' . $e->getMessage());
                self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . ' AI 异常（已跳过描述）：' . \mb_substr($e->getMessage(), 0, 80));
            }
            if ($usleepMicro > 0) {
                \usleep($usleepMicro);
            }
        }

        $u = ProductStyleIndexRowService::upsertStyleItem($code, $imageRef, $hot, $vec, $aiDesc, $imagePathDb);
        ProductStyleIndexRowService::syncProductAiDescription($code, $aiDesc);
        if (\strpos($resolvedTemp, 'style_import') !== false && \is_file($resolvedTemp)) {
            @\unlink($resolvedTemp);
        }

        self::unlinkExcelPending($taskId);

        if ($u['inserted']) {
            $task->inserted_count = (int) $task->inserted_count + 1;
        } else {
            $task->updated_count = (int) $task->updated_count + 1;
        }
        $task->save();

        $st = $u['inserted'] ? '新增' : '更新';
        $aiSt = ($visionOn && $aiDesc !== null && $aiDesc !== '') ? '，AI 描述已写入' : '';
        self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . '（第' . $rowIndex . '行）' . $st . '成功' . $aiSt);

        if (((int) $task->inserted_count + (int) $task->updated_count) % 5 === 0) {
            \gc_collect_cycles();
        }

        self::bumpExcelProcessedRows($task);

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

            return self::formatSnapshot($task);
        }

        if ($task->status === 'completed') {
            return self::formatSnapshot($task);
        }

        $meta = \json_decode((string) $task->header_json, true);
        if (!\is_array($meta) || empty($meta['excel'])) {
            self::unlinkExcelPending((int) $task->id);
            self::unlinkDrawingMap((int) $task->id);
            $task->status = 'failed';
            $task->error_message = 'Excel 任务状态损坏';
            $task->save();

            return self::formatSnapshot($task);
        }
        $highestRow = (int) ($meta['highest_row'] ?? 1);

        $visionOn = ProductStyleVisionDescribeService::shouldDescribeOnImport();
        $usleepMicro = (int) (Config::get('product_search.import_ai_usleep_microseconds') ?? 0);
        if ($usleepMicro < 0) {
            $usleepMicro = 0;
        }

        if ((int) $task->inserted_count + (int) $task->updated_count >= self::MAX_SUCCESS_ROWS) {
            self::appendLog($task, '已达单任务成功条数上限 ' . self::MAX_SUCCESS_ROWS . '，已结束');
            self::finalizeCompleted($task);

            return self::formatSnapshot($task);
        }

        $pendingPath = self::excelPendingPath((int) $task->id);
        if (\is_file($pendingPath)) {
            return self::doTickExcelProcessPending($task, $publicRoot, $visionOn, $usleepMicro);
        }

        $lineStart = (int) $task->line_idx;
        if ($lineStart > $highestRow) {
            self::finalizeCompleted($task);

            return self::formatSnapshot($task);
        }

        $rowMap = self::loadDrawingRowMap($task);
        $pack = ProductStyleXlsxImportService::readNextSubstantiveRowSingleLoad(
            $absPath,
            $lineStart,
            $highestRow,
            $publicRoot,
            $rowMap
        );
        $task->line_idx = $pack['next_line_idx'];
        $task->save();

        if ($pack['rec'] === null) {
            self::finalizeCompleted($task);

            return self::formatSnapshot($task);
        }

        $rec = $pack['rec'];
        $rowIndex = (int) $rec['row'];
        $code = $rec['code'];
        $imgTemp = $rec['imageTemp'];
        $imgRaw = $rec['imageRaw'];
        $hot = $rec['hot'];
        $imagePathWebRec = isset($rec['imagePathWeb']) ? (string) $rec['imagePathWeb'] : '';
        if ($imagePathWebRec === '') {
            $imagePathWebRec = null;
        }

        if ($code === '') {
            self::bumpExcelProcessedRows($task);

            return self::formatSnapshot($task);
        }

        if (($imgTemp === null || !\is_file($imgTemp) || !\is_readable($imgTemp)) && \trim($imgRaw) === '') {
            $task->failed_count = (int) $task->failed_count + 1;
            $task->save();
            $msg = '[第' . $rowIndex . '行] 缺失图片对象（有编号但图片列无文本且无可用嵌入图）';
            Log::info('style_import: ' . $msg);
            self::appendLog($task, $msg);
            self::bumpExcelProcessedRows($task);

            return self::formatSnapshot($task);
        }

        $excelEmbedSource = null;
        if ($imgTemp !== null && \is_file($imgTemp) && \is_readable($imgTemp)) {
            if ($imagePathWebRec !== null) {
                $resolved = ['ref' => $imagePathWebRec, 'temp' => $imgTemp, 'ok' => true];
            } else {
                $resolved = ['ref' => '(Excel嵌入图)', 'temp' => $imgTemp, 'ok' => true];
                $excelEmbedSource = $imgTemp;
            }
        } else {
            $resolved = ProductStyleImportService::resolveImage($imgRaw, $publicRoot);
        }
        if (!$resolved['ok'] || $resolved['temp'] === '') {
            $task->failed_count = (int) $task->failed_count + 1;
            $task->save();
            self::appendLog($task, '[' . \date('H:i:s') . '] 款式 ' . $code . '（第' . $rowIndex . '行）图片无法解析');
            self::bumpExcelProcessedRows($task);

            return self::formatSnapshot($task);
        }

        $pendingPayload = [
            'row' => $rowIndex,
            'code' => $code,
            'hot' => $hot,
            'resolved_ref' => (string) $resolved['ref'],
            'resolved_temp' => (string) $resolved['temp'],
            'excel_embed_source' => $excelEmbedSource,
        ];
        if ($imagePathWebRec !== null) {
            $pendingPayload['image_path_web'] = $imagePathWebRec;
        }
        self::writeExcelPending((int) $task->id, $pendingPayload);
        self::appendLog($task, '[' . \date('H:i:s') . '] 第 ' . $rowIndex . ' 行：图片已解析，下一轮将抽取向量' . ($visionOn ? '与豆包特征' : '') . '（若长时间停在 0/N，请查看下一条「开始抽取向量」日志）');

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
        $analysis = ProductStyleXlsxImportService::analyzeSheet($absPath);
        if (!$analysis['ok']) {
            $task->status = 'failed';
            $task->error_message = (string) ($analysis['error'] ?? 'Excel 无法读取');
            $task->save();
            self::appendLog($task, $task->error_message);

            return;
        }
        $hr = (int) $analysis['highest_row'];
        $substantive = (int) ($analysis['substantive_rows'] ?? 0);
        $task->header_resolved = 1;
        $extLower = \strtolower((string) $task->file_ext);
        $headerPayload = ['excel' => 1, 'highest_row' => $hr];
        if (\in_array($extLower, ['xlsx', 'xlsm'], true)) {
            $publicRoot = root_path() . 'public';
            $built = ProductStyleXlsxDrawingRowMapBuilder::build($absPath, $publicRoot);
            if (!($built['ok'] ?? false)) {
                Log::warning('Excel 嵌入图行映射失败（将回退按格解析）：' . (string) ($built['error'] ?? ''));
                self::appendLog($task, '嵌入图批量提取未完全成功，已回退单元格解析：' . \mb_substr((string) ($built['error'] ?? ''), 0, 120));
            }
            $map = $built['map'] ?? [];
            $mapJson = \json_encode($map, JSON_UNESCAPED_UNICODE);
            if ($mapJson !== false) {
                $dm = self::drawingMapPath((int) $task->id);
                if (@\file_put_contents($dm, $mapJson) !== false) {
                    $headerPayload['drawing_map_rel'] = 'style_import_tasks/drawing_map_' . (int) $task->id . '.json';
                    self::appendLog($task, '已提取嵌入图行映射 ' . \count($map) . ' 行（见 runtime/style_import_tasks/drawing_map_' . (int) $task->id . '.json）');
                }
            }
        }
        $task->header_json = \json_encode($headerPayload, JSON_UNESCAPED_UNICODE);
        $task->total_rows = \max(0, $substantive);
        $task->line_idx = 2;
        $task->processed_rows = 0;
        $task->use_default_header = 0;
        $task->save();
        self::appendLog($task, 'Excel 已就绪：物理行 2～' . $hr . '，有效数据行 ' . $task->total_rows . '（空行已排除）');
        self::appendVisionImportHint($task);
    }

    /** 任务开始时说明为何会有/无 AI 特征列，便于对照设置与 runtime/log */
    private static function appendVisionImportHint(ProductStyleImportTask $task): void
    {
        if (!ProductStyleVisionDescribeService::shouldDescribeOnImport()) {
            self::appendLog($task, '导入 AI 特征：未开启——请在「设置 → 豆包视觉」勾选「导入时生成描述」并保存（仅保存其它项不会自动保持勾选）');

            return;
        }
        if (!VolcArkVisionConfig::get()['enabled']) {
            self::appendLog($task, '导入 AI 特征：豆包未就绪——请勾选「启用豆包」并填写 Endpoint ID 与 API Key（Access 或 Secret 任一栏填 Key 均可）');

            return;
        }
        self::appendLog($task, '导入 AI 特征：豆包已就绪；每行处理时会请求方舟（详见 runtime/log 中 [volc_ark] 豆包）');
    }

    /** Excel 异步：每处理完一条有效数据行（成功或失败）+1，与 total_rows（有效行总数）对齐 */
    private static function bumpExcelProcessedRows(ProductStyleImportTask $task): void
    {
        $task->processed_rows = (int) $task->processed_rows + 1;
        $task->save();
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
        self::appendVisionImportHint($task);
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
        self::unlinkExcelPending((int) $task->id);
        self::unlinkDrawingMap((int) $task->id);
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
            'aliyun_pending' => 0,
            'done' => \in_array($task->status, ['completed', 'failed'], true),
        ];
    }
}
