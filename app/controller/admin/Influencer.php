<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Category as CategoryModel;
use app\model\Influencer as InfluencerModel;
use app\model\OutreachLog as OutreachLogModel;
use app\service\AdminAuditService;
use app\service\AdminAuthService;
use app\service\AutoDmService;
use app\service\InfluencerImportTaskRunner;
use app\service\InfluencerService;
use app\service\InfluencerSourceImportService;
use app\service\InfluencerStatusFlowService;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;

/**
 * 达人名录（tiktok_id = TikTok @handle）
 */
class Influencer extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return $this->apiJsonOk($data, $msg);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        return $this->apiJsonErr($msg, $code, $data, $errorKey);
    }

    public function index()
    {
        return View::fetch('admin/influencer/index', []);
    }

    /**
     * 达人下拉（达人链关联）
     */
    public function searchJson()
    {
        $q = trim((string) $this->request->param('q', ''));
        $items = InfluencerService::searchOptions($q, 30);

        return $this->jsonOk(['items' => $items]);
    }

    public function listJson()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = $this->request->param('status', null);
        $category = trim((string) $this->request->param('category', ''));
        $categoryId = (int) $this->request->param('category_id', 0);
        $tag = trim((string) $this->request->param('tag', ''));
        $tagFilters = $this->normalizeTags($this->request->param('tags', []));
        if ($tagFilters === [] && $tag !== '') {
            $tagFilters = $this->normalizeTags($tag);
        }
        $sortByContact = (int) $this->request->param('sort_by_contact', 0);
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) {
            $pageSize = 10;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        $query = InfluencerModel::order('id', 'desc');
        if ($sortByContact === 1) {
            $query = InfluencerModel::orderRaw('last_contacted_at IS NULL ASC')
                ->order('last_contacted_at', 'desc')
                ->order('id', 'desc');
        }
        $query = $this->scopeTenant($query, 'influencers');
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('tiktok_id', '%' . $keyword . '%')
                    ->whereOr('nickname', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        if ($category !== '') {
            $query->where(function ($sub) use ($category) {
                $sub->where('category_name', $category)->whereOr('category_id', (int) $category);
            });
        }
        if ($tagFilters !== []) {
            foreach ($tagFilters as $tagItem) {
                $like = '%"' . addcslashes($tagItem, '\\"_%') . '"%';
                $query->whereLike('tags_json', $like);
            }
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $contactRaw = (string) ($row->contact_info ?? '');
            $channels = InfluencerService::contactChannelsFromStored($contactRaw !== '' ? $contactRaw : null);
            $contactDisplay = InfluencerService::contactDisplayLine($channels);
            if ($contactDisplay === '' && $contactRaw !== '') {
                $contactDisplay = $contactRaw;
            }
            $items[] = [
                'id' => (int) $row->id,
                'tiktok_id' => (string) ($row->tiktok_id ?? ''),
                'category_name' => (string) ($row->category_name ?? ''),
                'category_id' => (int) ($row->category_id ?? 0),
                'nickname' => (string) ($row->nickname ?? ''),
                'avatar_url' => (string) ($row->avatar_url ?? ''),
                'follower_count' => (int) ($row->follower_count ?? 0),
                'contact_info' => $contactRaw,
                'contact_display' => $contactDisplay,
                'contact_channels' => $channels,
                'region' => (string) ($row->region ?? ''),
                'profile_url' => (string) ($row->profile_url ?? ''),
                'data_source' => (string) ($row->data_source ?? ''),
                'source_system' => (string) ($row->source_system ?? ''),
                'source_influencer_id' => (string) ($row->source_influencer_id ?? ''),
                'source_sync_at' => (string) ($row->source_sync_at ?? ''),
                'last_crawled_at' => (string) ($row->last_crawled_at ?? ''),
                'source_batch_id' => (int) ($row->source_batch_id ?? 0),
                'status' => (int) ($row->status ?? 0),
                'sample_tracking_no' => (string) ($row->sample_tracking_no ?? ''),
                'sample_status' => (int) ($row->sample_status ?? 0),
                'tags' => $this->parseTags((string) ($row->tags_json ?? '')),
                'last_contacted_at' => (string) ($row->last_contacted_at ?? ''),
                'created_at' => (string) ($row->created_at ?? ''),
                'updated_at' => (string) ($row->updated_at ?? ''),
            ];
        }

        $categoryQuery = CategoryModel::where('type', 'influencer')
            ->where('status', 1);
        $categoryQuery = $this->scopeTenant($categoryQuery, 'categories');
        $categories = $categoryQuery
            ->order('sort_order', 'asc')
            ->order('id', 'desc')
            ->field('id,name')
            ->select()
            ->toArray();

        return $this->jsonOk([
            'items' => $items,
            'categories' => $categories,
            'tag_options' => $this->collectTagOptions(),
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ]);
    }

    /**
     * 达秘导入预览：上传文件 + 映射，返回 insert/update 差异。
     */
    public function sourceImportPreview()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
            }
            $file = $this->request->file('file');
            if (!$file) {
                return $this->jsonErr('file_required', 1, null, 'common.pickFile');
            }
            $ext = strtolower((string) $file->extension());
            if ($ext === '') {
                $ext = strtolower(pathinfo((string) $file->getOriginalName(), PATHINFO_EXTENSION));
            }
            if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls', 'xlsm'], true)) {
                return $this->jsonErr('csv_only', 1, null, 'page.dataImport.csvOnly');
            }
            $sourceSystem = trim((string) $this->request->post('source_system', InfluencerSourceImportService::SOURCE_DAMI));
            $mappingRaw = trim((string) $this->request->post('mapping_json', ''));
            $mapping = [];
            if ($mappingRaw !== '') {
                $decoded = json_decode($mappingRaw, true);
                if (is_array($decoded)) {
                    $mapping = $decoded;
                }
            }

            $parsed = InfluencerSourceImportService::readFile(
                (string) $file->getPathname(),
                $ext,
                $sourceSystem,
                $mapping,
                500
            );
            $preview = InfluencerSourceImportService::previewRows(
                (array) ($parsed['headers'] ?? []),
                (array) ($parsed['rows'] ?? []),
                (array) ($parsed['mapping'] ?? []),
                $this->currentTenantId(),
                120
            );

            return $this->jsonOk([
                'source_system' => $sourceSystem,
                'headers' => (array) ($parsed['headers'] ?? []),
                'mapping' => (array) ($parsed['mapping'] ?? []),
                'field_keys' => InfluencerSourceImportService::fieldKeys(),
                'summary' => [
                    'total' => (int) ($preview['total'] ?? 0),
                    'inserted' => (int) ($preview['inserted'] ?? 0),
                    'updated' => (int) ($preview['updated'] ?? 0),
                    'failed' => (int) ($preview['failed'] ?? 0),
                ],
                'preview' => (array) ($preview['preview'] ?? []),
                'failed_rows' => (array) ($preview['failed_rows'] ?? []),
            ]);
        } catch (\Throwable $e) {
            Log::error('influencer sourceImportPreview: ' . $e->getMessage());
            return $this->jsonErr('loading_failed', 1, ['message' => $e->getMessage()], 'common.loadingFailed');
        }
    }

    /**
     * 达秘导入执行：上传文件 + 映射，执行 upsert 并生成批次审计。
     */
    public function sourceImportCommit()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
            }
            $file = $this->request->file('file');
            if (!$file) {
                return $this->jsonErr('file_required', 1, null, 'common.pickFile');
            }
            $ext = strtolower((string) $file->extension());
            if ($ext === '') {
                $ext = strtolower(pathinfo((string) $file->getOriginalName(), PATHINFO_EXTENSION));
            }
            if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls', 'xlsm'], true)) {
                return $this->jsonErr('csv_only', 1, null, 'page.dataImport.csvOnly');
            }
            $sourceSystem = trim((string) $this->request->post('source_system', InfluencerSourceImportService::SOURCE_DAMI));
            $mappingRaw = trim((string) $this->request->post('mapping_json', ''));
            $mapping = [];
            if ($mappingRaw !== '') {
                $decoded = json_decode($mappingRaw, true);
                if (is_array($decoded)) {
                    $mapping = $decoded;
                }
            }

            $parsed = InfluencerSourceImportService::readFile(
                (string) $file->getPathname(),
                $ext,
                $sourceSystem,
                $mapping,
                0
            );
            $result = InfluencerSourceImportService::commitRows(
                (array) ($parsed['headers'] ?? []),
                (array) ($parsed['rows'] ?? []),
                (array) ($parsed['mapping'] ?? []),
                $this->currentTenantId(),
                AdminAuthService::userId(),
                (string) $file->getOriginalName(),
                $sourceSystem
            );

            return $this->jsonOk($result, 'updated');
        } catch (\Throwable $e) {
            Log::error('influencer sourceImportCommit: ' . $e->getMessage());
            return $this->jsonErr('save_failed', 1, ['message' => $e->getMessage()], 'common.saveFailed');
        }
    }

    /**
     * 达秘导入批次 + 外联漏斗统计。
     */
    public function sourceImportBatches()
    {
        try {
            $page = max(1, (int) $this->request->param('page', 1));
            $pageSize = (int) $this->request->param('page_size', 10);
            if ($pageSize <= 0) {
                $pageSize = 10;
            }
            if ($pageSize > 100) {
                $pageSize = 100;
            }

            $query = Db::name('influencer_source_import_batches')
                ->where('source_system', InfluencerSourceImportService::SOURCE_DAMI)
                ->order('id', 'desc');
            if ($this->tableHasTenantId('influencer_source_import_batches')) {
                $query->where('tenant_id', $this->currentTenantId());
            }
            $list = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page,
                'query' => $this->request->param(),
            ]);

            $items = [];
            foreach ($list as $row) {
                $arr = is_array($row) ? $row : $row->toArray();
                $batchId = (int) ($arr['id'] ?? 0);

                $infQuery = Db::name('influencers')->where('source_batch_id', $batchId);
                if ($this->tableHasTenantId('influencers')) {
                    $infQuery->where('tenant_id', $this->currentTenantId());
                }
                $influencerCount = (int) $infQuery->count();

                $taskQuery = Db::name('auto_dm_tasks')
                    ->alias('t')
                    ->join('influencers i', 'i.id=t.influencer_id')
                    ->where('i.source_batch_id', $batchId);
                if ($this->tableHasTenantId('auto_dm_tasks')) {
                    $taskQuery->where('t.tenant_id', $this->currentTenantId());
                }
                if ($this->tableHasTenantId('influencers')) {
                    $taskQuery->where('i.tenant_id', $this->currentTenantId());
                }
                $taskRows = $taskQuery->field('t.id,t.task_status,t.reply_state')->select()->toArray();
                $taskCount = count($taskRows);
                $sentCount = 0;
                $replyCount = 0;
                foreach ($taskRows as $taskRow) {
                    if ((int) ($taskRow['task_status'] ?? 0) === AutoDmService::TASK_STATUS_SENT) {
                        $sentCount++;
                    }
                    if ((int) ($taskRow['reply_state'] ?? 0) > AutoDmService::REPLY_STATE_NONE) {
                        $replyCount++;
                    }
                }

                $reviewQuery = Db::name('auto_dm_reply_reviews')
                    ->alias('rr')
                    ->join('influencers i', 'i.id=rr.influencer_id')
                    ->where('i.source_batch_id', $batchId);
                if ($this->tableHasTenantId('auto_dm_reply_reviews')) {
                    $reviewQuery->where('rr.tenant_id', $this->currentTenantId());
                }
                if ($this->tableHasTenantId('influencers')) {
                    $reviewQuery->where('i.tenant_id', $this->currentTenantId());
                }
                $reviewRows = $reviewQuery->field('rr.confirm_category')->select()->toArray();
                $converted = 0;
                foreach ($reviewRows as $reviewRow) {
                    $cat = strtolower(trim((string) ($reviewRow['confirm_category'] ?? '')));
                    if (in_array($cat, ['intent', 'inquiry'], true)) {
                        $converted++;
                    }
                }

                $items[] = [
                    'id' => $batchId,
                    'batch_no' => (string) ($arr['batch_no'] ?? ''),
                    'source_system' => (string) ($arr['source_system'] ?? ''),
                    'file_name' => (string) ($arr['file_name'] ?? ''),
                    'total_rows' => (int) ($arr['total_rows'] ?? 0),
                    'inserted_rows' => (int) ($arr['inserted_rows'] ?? 0),
                    'updated_rows' => (int) ($arr['updated_rows'] ?? 0),
                    'skipped_rows' => (int) ($arr['skipped_rows'] ?? 0),
                    'failed_rows' => (int) ($arr['failed_rows'] ?? 0),
                    'created_at' => (string) ($arr['created_at'] ?? ''),
                    'influencer_count' => $influencerCount,
                    'task_count' => $taskCount,
                    'sent_count' => $sentCount,
                    'reply_count' => $replyCount,
                    'converted_count' => $converted,
                    'touch_rate' => $influencerCount > 0 ? round($taskCount * 100 / $influencerCount, 2) : 0.0,
                    'reply_rate' => $taskCount > 0 ? round($replyCount * 100 / $taskCount, 2) : 0.0,
                    'convert_rate' => $taskCount > 0 ? round($converted * 100 / $taskCount, 2) : 0.0,
                ];
            }

            return $this->jsonOk([
                'items' => $items,
                'total' => (int) $list->total(),
                'page' => (int) $list->currentPage(),
                'page_size' => (int) $list->listRows(),
            ]);
        } catch (\Throwable $e) {
            Log::error('influencer sourceImportBatches: ' . $e->getMessage());
            return $this->jsonErr('loading_failed', 1, ['message' => $e->getMessage()], 'common.loadingFailed');
        }
    }

    public function importCsv()
    {
        try {
            return $this->importCsvInner();
        } catch (\Throwable $e) {
            Log::error('influencer importCsv: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return $this->jsonErr('导入失败：' . $e->getMessage());
        }
    }

    public function importTaskStatus()
    {
        try {
            $id = (int) $this->request->param('task_id', 0);
            if ($id <= 0) {
                return $this->jsonErr('无效 task_id');
            }
            InfluencerImportTaskRunner::bumpMemoryAndTime();
            $snap = InfluencerImportTaskRunner::snapshot($id);
            if ($snap === null) {
                return $this->jsonErr('任务不存在');
            }

            return $this->jsonOk($snap);
        } catch (\Throwable $e) {
            Log::error('influencer importTaskStatus: ' . $e->getMessage());

            return $this->jsonErr('查询失败：' . $e->getMessage());
        }
    }

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
            $r = InfluencerImportTaskRunner::tick($id);
            if (isset($r['_error'])) {
                return $this->jsonErr((string) $r['_error']);
            }

            return $this->jsonOk($r);
        } catch (\Throwable $e) {
            Log::error('influencer importTaskTick: ' . $e->getMessage());

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

        InfluencerImportTaskRunner::bumpMemoryAndTime();
        try {
            $taskId = InfluencerImportTaskRunner::createFromUploadedFile($tmp, $ext);
        } catch (\Throwable $e) {
            Log::warning('influencer create import task: ' . $e->getMessage());

            return $this->jsonErr('创建导入任务失败：' . $e->getMessage() . '（请先执行 database/run_migration_influencers_crm.php）');
        }

        return $this->jsonOk([
            'mode' => 'async',
            'task_id' => $taskId,
        ], '任务已创建');
    }

    /**
     * 下载导入示例 CSV（表头 + 一行样例）
     */
    public function sampleCsv()
    {
        $csv = "\xEF\xBB\xBFtiktok_id,category_name,nickname,follower_count,region,whatsapp,zalo,contact,status\n@demo_creator,美妆达人,Demo昵称,12000,VN,84912345678,84912345678,,1\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sample_influencers.csv"',
        ], 'html');
    }

    /**
     * 导出全量达人 CSV（UTF-8 BOM，与导入列兼容：含 contact 原始文本）
     */
    public function exportCsv()
    {
        try {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $filename = 'influencers_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return $this->jsonErr('无法写入响应');
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'tiktok_id', 'category_name', 'nickname', 'avatar_url', 'follower_count', 'contact', 'region', 'status', 'created_at', 'updated_at']);
            AdminAuditService::log(
                $this->request,
                'influencer.export_contacts',
                'influencers',
                0,
                ['includes' => ['contact', 'whatsapp', 'zalo']]
            );

            $batch = 2000;
            $lastId = 0;
            while (true) {
                $rowsQuery = Db::name('influencers')
                    ->where('id', '>', $lastId)
                    ->order('id', 'asc')
                    ->limit($batch);
                $rowsQuery = $this->scopeTenant($rowsQuery, 'influencers');
                $rows = $rowsQuery->select();
                if ($rows === null || count($rows) === 0) {
                    break;
                }
                foreach ($rows as $row) {
                    $r = is_array($row) ? $row : $row->toArray();
                    $lastId = (int) ($r['id'] ?? 0);
                    $contact = (string) ($r['contact_info'] ?? '');
                    fputcsv($out, [
                        $r['id'] ?? '',
                        $r['tiktok_id'] ?? '',
                        $r['category_name'] ?? '',
                        $r['nickname'] ?? '',
                        $r['avatar_url'] ?? '',
                        (int) ($r['follower_count'] ?? 0),
                        $contact,
                        $r['region'] ?? '',
                        (int) ($r['status'] ?? 0),
                        (string) ($r['created_at'] ?? ''),
                        (string) ($r['updated_at'] ?? ''),
                    ]);
                }
            }
            fclose($out);
        } catch (\Throwable $e) {
            Log::error('influencer exportCsv: ' . $e->getMessage());

            if (!headers_sent()) {
                return $this->jsonErr('导出失败：' . $e->getMessage());
            }
        }

        exit;
    }

    /**
     * POST：编辑达人（不可改 tiktok_id）
     */
    public function update()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->jsonErr('仅支持 POST');
            }
            $payload = $this->parseJsonOrPost();
            $id = (int) ($payload['id'] ?? 0);
            if ($id <= 0) {
                return $this->jsonErr('无效 id');
            }
            $rowQuery = InfluencerModel::where('id', $id);
            $rowQuery = $this->scopeTenant($rowQuery, 'influencers');
            $row = $rowQuery->find();
            if (!$row) {
                return $this->jsonErr('记录不存在');
            }

            if (isset($payload['nickname'])) {
                $row->nickname = trim((string) $payload['nickname']);
            }
            if (array_key_exists('category_id', $payload) || array_key_exists('category_name', $payload)) {
                $categoryId = (int) ($payload['category_id'] ?? 0);
                $categoryName = trim((string) ($payload['category_name'] ?? ''));
                if ($categoryId > 0) {
                    $cat = CategoryModel::where('id', $categoryId)->where('type', 'influencer')->find();
                    if ($cat) {
                        $categoryName = (string) ($cat->name ?? '');
                    }
                }
                $row->category_id = $categoryId > 0 ? $categoryId : null;
                $row->category_name = $categoryName !== '' ? mb_substr($categoryName, 0, 64) : null;
            }
            if (array_key_exists('avatar_url', $payload)) {
                $a = trim((string) $payload['avatar_url']);
                $row->avatar_url = $a !== '' ? mb_substr($a, 0, 1024) : null;
            }
            if (isset($payload['follower_count'])) {
                $row->follower_count = max(0, (int) $payload['follower_count']);
            }
            if (array_key_exists('contact_whatsapp', $payload)
                || array_key_exists('contact_zalo', $payload)
                || array_key_exists('contact_note', $payload)
                || array_key_exists('contact_text', $payload)) {
                $row->contact_info = InfluencerService::mergeContactFromUpdatePayload(
                    (string) ($row->contact_info ?? ''),
                    $payload
                );
            }
            if (array_key_exists('region', $payload)) {
                $r = trim((string) $payload['region']);
                $row->region = $r !== '' ? mb_substr($r, 0, 64) : null;
            }
            $statusTarget = null;
            if (isset($payload['status'])) {
                $st = (int) $payload['status'];
                if ($st >= 0 && $st <= 6) {
                    $statusTarget = $st;
                }
            }
            if (array_key_exists('sample_tracking_no', $payload)) {
                $s = trim((string) $payload['sample_tracking_no']);
                $row->sample_tracking_no = $s !== '' ? mb_substr($s, 0, 64) : null;
            }
            if (array_key_exists('sample_status', $payload)) {
                $ss = (int) $payload['sample_status'];
                if ($ss < 0) {
                    $ss = 0;
                }
                if ($ss > 2) {
                    $ss = 2;
                }
                $row->sample_status = $ss;
            }
            if (array_key_exists('tags', $payload)) {
                $tags = $this->normalizeTags($payload['tags']);
                $row->tags_json = $tags !== [] ? json_encode($tags, JSON_UNESCAPED_UNICODE) : null;
            }
            $row->save();

            if ($statusTarget !== null) {
                $flow = InfluencerStatusFlowService::transition(
                    (int) $row->id,
                    $statusTarget,
                    'influencer_edit',
                    '',
                    ['api' => 'influencer/update'],
                    true
                );
                if (!($flow['ok'] ?? false)) {
                    return $this->jsonErr((string) ($flow['message'] ?? 'status_transition_failed'));
                }
            }

            return $this->jsonOk([], '已保存');
        } catch (\Throwable $e) {
            Log::error('influencer update: ' . $e->getMessage());

            return $this->jsonErr('保存失败：' . $e->getMessage());
        }
    }

    /**
     * POST：删除达人（先解除达人链上的关联）
     */
    public function delete()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->jsonErr('仅支持 POST');
            }
            $payload = $this->parseJsonOrPost();
            $id = (int) ($payload['id'] ?? 0);
            if ($id <= 0) {
                $id = (int) $this->request->param('id', 0);
            }
            if ($id <= 0) {
                return $this->jsonErr('无效 id');
            }
            $rowQuery = InfluencerModel::where('id', $id);
            $rowQuery = $this->scopeTenant($rowQuery, 'influencers');
            $row = $rowQuery->find();
            if (!$row) {
                return $this->jsonErr('记录不存在');
            }
            $linksQuery = Db::name('product_links')->where('influencer_id', $id);
            $linksQuery = $this->scopeTenant($linksQuery, 'product_links');
            $linksQuery->update(['influencer_id' => null]);
            $row->delete();

            return $this->jsonOk([], '已删除');
        } catch (\Throwable $e) {
            Log::error('influencer delete: ' . $e->getMessage());

            return $this->jsonErr('删除失败：' . $e->getMessage());
        }
    }

    public function updateStatus()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $status = (int) ($payload['status'] ?? -1);
        if ($id <= 0 || $status < 0 || $status > 6) {
            return $this->jsonErr('参数错误');
        }
        $flow = InfluencerStatusFlowService::transition(
            $id,
            $status,
            'manual_status',
            '',
            ['api' => 'influencer/updateStatus'],
            true
        );
        if (!($flow['ok'] ?? false)) {
            return $this->jsonErr((string) ($flow['message'] ?? 'status_transition_failed'));
        }

        return $this->jsonOk([], '已更新');
    }

    /**
     * 快捷寄样：写入快递单号并将状态置为「已寄样(4)」
     */
    public function markSampleShipped()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $trackingNo = trim((string) ($payload['sample_tracking_no'] ?? ''));
        $courier = trim((string) ($payload['courier'] ?? ''));
        if ($id <= 0) {
            return $this->jsonErr('无效 id');
        }
        if ($trackingNo === '') {
            return $this->jsonErr('请填写快递单号');
        }
        $rowQuery = InfluencerModel::where('id', $id);
        $rowQuery = $this->scopeTenant($rowQuery, 'influencers');
        $row = $rowQuery->find();
        if (!$row) {
            return $this->jsonErr('记录不存在');
        }

        try {
            Db::transaction(function () use ($row, $trackingNo, $courier): void {
                $now = date('Y-m-d H:i:s');
                $tracking = mb_substr($trackingNo, 0, 64);
                $row->sample_tracking_no = $tracking;
                $row->sample_status = 1;
                $row->save();

                try {
                    Db::name('sample_shipments')->where('id', 0)->find();
                    $shipment = Db::name('sample_shipments')
                        ->where('influencer_id', (int) $row->id)
                        ->where('tracking_no', $tracking)
                        ->find();
                    if ($shipment) {
                        Db::name('sample_shipments')->where('id', (int) $shipment['id'])->update([
                            'courier' => $courier !== '' ? mb_substr($courier, 0, 64) : null,
                            'shipment_status' => 1,
                            'shipped_at' => $now,
                            'updated_at' => $now,
                        ]);
                    } else {
                        Db::name('sample_shipments')->insert([
                            'influencer_id' => (int) $row->id,
                            'tracking_no' => $tracking,
                            'courier' => $courier !== '' ? mb_substr($courier, 0, 64) : null,
                            'shipment_status' => 1,
                            'receipt_status' => 0,
                            'shipped_at' => $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                } catch (\Throwable $e) {
                    // sample_shipments table is optional before migration.
                }

                $flow = InfluencerStatusFlowService::transition(
                    (int) $row->id,
                    4,
                    'quick_ship',
                    '',
                    ['api' => 'influencer/markSampleShipped', 'tracking_no' => $tracking],
                    true
                );
                if (!($flow['ok'] ?? false)) {
                    throw new \RuntimeException((string) ($flow['message'] ?? 'status_transition_failed'));
                }
            });
        } catch (\Throwable $e) {
            return $this->jsonErr('更新失败：' . $e->getMessage());
        }

        return $this->jsonOk([], '已更新');
    }

    /**
     * 记录外联动作日志（复制 / 跳转），并刷新最后联系时间
     */
    public function logOutreachAction()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $influencerId = (int) ($payload['influencer_id'] ?? 0);
        $templateId = (int) ($payload['template_id'] ?? 0);
        $productId = (int) ($payload['product_id'] ?? 0);
        $action = trim((string) ($payload['action'] ?? ''));
        $renderedBody = (string) ($payload['rendered_body'] ?? '');
        if ($influencerId <= 0 || $templateId <= 0) {
            return $this->jsonErr('参数错误');
        }
        $inf = InfluencerModel::find($influencerId);
        if (!$inf) {
            return $this->jsonErr('达人不存在');
        }
        $tpl = Db::name('message_templates')->where('id', $templateId)->find();
        if (!$tpl) {
            return $this->jsonErr('模板不存在');
        }
        $productName = '';
        if ($productId > 0) {
            $p = Db::name('products')->where('id', $productId)->find();
            if (is_array($p)) {
                $productName = (string) ($p['name'] ?? '');
            }
        }
        $channel = 'action_copy';
        if ($action !== '') {
            $channel = 'action_' . preg_replace('/[^a-z0-9_]+/i', '_', strtolower($action));
            $channel = mb_substr($channel, 0, 32);
        }
        $logPayload = $this->withTenantPayload([
            'influencer_id' => $influencerId,
            'template_id' => (int) ($tpl['id'] ?? 0),
            'template_name' => (string) ($tpl['name'] ?? ''),
            'template_lang' => (string) ($tpl['lang'] ?? 'zh'),
            'product_id' => $productId > 0 ? $productId : null,
            'product_name' => $productName !== '' ? $productName : null,
            'channel' => $channel,
            'rendered_body' => $renderedBody,
        ], 'outreach_logs');
        OutreachLogModel::create($logPayload);
        $touchQuery = InfluencerModel::where('id', $influencerId);
        $touchQuery = $this->scopeTenant($touchQuery, 'influencers');
        $touchQuery->update(['last_contacted_at' => date('Y-m-d H:i:s')]);
        if (str_contains($channel, 'copy') || str_contains($channel, 'jump')) {
            InfluencerStatusFlowService::transition(
                $influencerId,
                1,
                'outreach_action',
                '',
                ['channel' => $channel, 'api' => 'influencer/logOutreachAction'],
                true
            );
        }

        return $this->jsonOk([], '已记录');
    }

    public function outreachHistory()
    {
        $id = (int) $this->request->param('influencer_id', 0);
        if ($id <= 0) {
            return $this->jsonErr('无效 influencer_id');
        }
        $rowsQuery = OutreachLogModel::where('influencer_id', $id);
        $rowsQuery = $this->scopeTenant($rowsQuery, 'outreach_logs');
        $rows = $rowsQuery
            ->order('id', 'desc')
            ->limit(100)
            ->select();
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'template_id' => (int) ($row->template_id ?? 0),
                'template_name' => (string) ($row->template_name ?? ''),
                'template_lang' => (string) ($row->template_lang ?? 'zh'),
                'product_id' => (int) ($row->product_id ?? 0),
                'product_name' => (string) ($row->product_name ?? ''),
                'channel' => (string) ($row->channel ?? 'render'),
                'rendered_body' => (string) ($row->rendered_body ?? ''),
                'created_at' => (string) ($row->created_at ?? ''),
            ];
        }

        return $this->jsonOk(['items' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonOrPost(): array
    {
        $raw = (string) $this->request->getContent();
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                return $j;
            }
        }

        return $this->request->post();
    }

    /**
     * @return list<string>
     */
    private function parseTags(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $j = json_decode($raw, true);
        if (!is_array($j)) {
            return [];
        }

        return $this->normalizeTags($j);
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizeTags($raw): array
    {
        $arr = [];
        if (is_string($raw)) {
            $str = trim($raw);
            if ($str !== '' && ($str[0] === '[' || $str[0] === '{')) {
                $decoded = json_decode($str, true);
                if (is_array($decoded)) {
                    $raw = $decoded;
                }
            }
        }
        if (is_string($raw)) {
            $parts = preg_split('/[,，\n]/u', $raw) ?: [];
            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part !== '') {
                    $arr[] = mb_substr($part, 0, 24);
                }
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $item) {
                $part = trim((string) $item);
                if ($part !== '') {
                    $arr[] = mb_substr($part, 0, 24);
                }
            }
        }
        $arr = array_values(array_unique($arr));
        if (count($arr) > 20) {
            $arr = array_slice($arr, 0, 20);
        }

        return $arr;
    }

    /**
     * @return list<string>
     */
    private function collectTagOptions(): array
    {
        $rowsQuery = Db::name('influencers')
            ->whereNotNull('tags_json')
            ->where('tags_json', '<>', '')
            ->field('tags_json')
            ->order('id', 'desc')
            ->limit(500);
        $rowsQuery = $this->scopeTenant($rowsQuery, 'influencers');
        $rows = $rowsQuery->select()->toArray();
        $all = [];
        foreach ($rows as $row) {
            $raw = (string) ($row['tags_json'] ?? '');
            if ($raw === '') {
                continue;
            }
            foreach ($this->parseTags($raw) as $tag) {
                $all[$tag] = $tag;
            }
        }
        $items = array_values($all);
        sort($items);

        return array_slice($items, 0, 200);
    }
}
