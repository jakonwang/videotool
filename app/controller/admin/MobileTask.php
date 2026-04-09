<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Influencer as InfluencerModel;
use app\model\MobileActionLog as MobileActionLogModel;
use app\model\MobileActionTask as MobileActionTaskModel;
use app\service\AdminAuthService;
use app\service\InfluencerService;
use app\service\InfluencerStatusFlowService;
use app\service\MobileOutreachService;
use think\facade\Db;

class MobileTask extends BaseController
{
    /**
     * @var array<string, bool>
     */
    private static array $COLUMN_CACHE = [];

    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return $this->apiJsonOk($data, $msg);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        return $this->apiJsonErr($msg, $code, $data, $errorKey);
    }

    public function listJson()
    {
        $page = max(1, (int) $this->request->param('page', 1));
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }

        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = trim((string) $this->request->param('task_status', ''));
        $taskType = trim((string) $this->request->param('task_type', ''));
        $deviceId = (int) $this->request->param('device_id', 0);

        $fields = [
            't.*',
            'i.tiktok_id',
            'i.nickname',
            'i.region',
            'i.last_contacted_at',
            'i.last_commented_at',
            'd.device_name',
            'd.device_code',
        ];
        if ($this->columnExists('influencers', 'category_name')) {
            $fields[] = 'i.category_name';
        } else {
            $fields[] = "'' as category_name";
        }
        if ($this->columnExists('influencers', 'avatar_url')) {
            $fields[] = 'i.avatar_url';
        } else {
            $fields[] = "'' as avatar_url";
        }
        if ($this->columnExists('influencers', 'status')) {
            $fields[] = 'i.status as influencer_status';
        } else {
            $fields[] = '0 as influencer_status';
        }
        if ($this->columnExists('influencers', 'quality_score')) {
            $fields[] = 'i.quality_score';
        } else {
            $fields[] = '0 as quality_score';
        }

        $query = MobileActionTaskModel::alias('t')
            ->leftJoin('influencers i', 'i.id = t.influencer_id')
            ->leftJoin('mobile_devices d', 'd.id = t.device_id')
            ->field(implode(',', $fields))
            ->order('t.priority', 'desc')
            ->order('t.id', 'desc');
        if ($this->tableHasTenantId('mobile_action_tasks')) {
            $query->where('t.tenant_id', $this->currentTenantId());
        }
        if ($this->tableHasTenantId('influencers')) {
            $query->where('i.tenant_id', $this->currentTenantId());
        }
        if ($status !== '') {
            $query->where('t.task_status', (int) $status);
        }
        if ($taskType !== '') {
            $query->where('t.task_type', MobileOutreachService::normalizeTaskType($taskType));
        }
        if ($deviceId > 0) {
            $query->where('t.device_id', $deviceId);
        }
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword): void {
                $sub->whereLike('i.tiktok_id', '%' . $keyword . '%')
                    ->whereOr('i.nickname', 'like', '%' . $keyword . '%')
                    ->whereOr('t.last_error_message', 'like', '%' . $keyword . '%');
            });
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $items[] = $this->mapTaskRow(is_array($row) ? $row : $row->toArray());
        }

        $summary = $this->buildDashboardSummary();

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
            'summary' => $summary,
        ]);
    }

    public function updateStatus()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $taskId = (int) ($payload['task_id'] ?? 0);
        if ($taskId <= 0) {
            return $this->jsonErr('invalid_task_id', 1, null, 'common.invalidParams');
        }

        $taskQuery = MobileActionTaskModel::where('id', $taskId);
        $taskQuery = $this->scopeTenant($taskQuery, 'mobile_action_tasks');
        $task = $taskQuery->find();
        if (!$task) {
            return $this->jsonErr('task_not_found', 404, null, 'common.notFound');
        }

        $eventRaw = trim((string) ($payload['event'] ?? $payload['action'] ?? ''));
        if ($eventRaw === '') {
            $eventRaw = 'prepared';
        }
        $event = MobileOutreachService::normalizeActionEvent($eventRaw, (string) ($task->task_type ?? ''));
        $nextStatus = MobileOutreachService::mapReportEventToStatus($eventRaw);
        $renderedText = trim((string) ($payload['rendered_text'] ?? $payload['text'] ?? ''));
        $errorCode = trim((string) ($payload['error_code'] ?? ''));
        $errorMessage = trim((string) ($payload['error_message'] ?? ''));
        $now = date('Y-m-d H:i:s');
        $tenantId = $this->currentTenantId();

        Db::transaction(function () use (
            $task,
            $event,
            $nextStatus,
            $renderedText,
            $errorCode,
            $errorMessage,
            $now,
            $tenantId,
            $payload
        ): void {
            $task->task_status = $nextStatus;
            if ($renderedText !== '') {
                $task->rendered_text = $renderedText;
            }
            if ($nextStatus === MobileOutreachService::STATUS_PREPARED) {
                $task->prepared_at = $now;
            }
            if (in_array($nextStatus, [
                MobileOutreachService::STATUS_DONE,
                MobileOutreachService::STATUS_FAILED,
                MobileOutreachService::STATUS_SKIPPED,
                MobileOutreachService::STATUS_CANCELED,
            ], true)) {
                $task->completed_at = $now;
            }
            if ($nextStatus === MobileOutreachService::STATUS_FAILED) {
                $task->last_error_code = $errorCode !== '' ? mb_substr($errorCode, 0, 64) : null;
                $task->last_error_message = $errorMessage !== '' ? mb_substr($errorMessage, 0, 255) : null;
                $task->last_error_at = $now;
            } else {
                $task->last_error_code = null;
                $task->last_error_message = null;
                $task->last_error_at = null;
            }
            $task->save();

            $this->appendTaskLog($tenantId, [
                'task_id' => (int) $task->id,
                'influencer_id' => (int) ($task->influencer_id ?? 0),
                'event_type' => $event !== '' ? $event : 'report',
                'event_status' => $nextStatus === MobileOutreachService::STATUS_FAILED ? 2 : 1,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            if (MobileOutreachService::shouldTouchLastCommentedAt($event, (string) ($task->task_type ?? ''))) {
                $query = InfluencerModel::where('id', (int) ($task->influencer_id ?? 0));
                if ($this->tableHasTenantId('influencers')) {
                    $query->where('tenant_id', $tenantId);
                }
                $query->update(['last_commented_at' => $now]);
            }
            if (MobileOutreachService::shouldTouchLastContactedAt($event, (string) ($task->task_type ?? ''))) {
                $query = InfluencerModel::where('id', (int) ($task->influencer_id ?? 0));
                if ($this->tableHasTenantId('influencers')) {
                    $query->where('tenant_id', $tenantId);
                }
                $query->update(['last_contacted_at' => $now]);
                InfluencerStatusFlowService::transition(
                    (int) ($task->influencer_id ?? 0),
                    1,
                    'mobile_console',
                    '',
                    ['event' => $event, 'task_id' => (int) $task->id],
                    true
                );
            }
        });

        return $this->jsonOk([
            'task_id' => $taskId,
            'task_status' => $nextStatus,
            'event' => $event,
        ], 'updated');
    }

    public function createBatch()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $taskType = MobileOutreachService::normalizeTaskType((string) ($payload['task_type'] ?? 'tiktok_dm'));
        $preferredChannel = trim((string) ($payload['preferred_channel'] ?? 'auto'));
        $templateId = (int) ($payload['template_id'] ?? 0);
        $productId = (int) ($payload['product_id'] ?? 0);
        $remark = trim((string) ($payload['remark'] ?? ''));

        $forcePriority = null;
        if (isset($payload['priority']) && $payload['priority'] !== '') {
            $forcePriority = (int) $payload['priority'];
        }
        $limit = (int) ($payload['limit'] ?? 100);
        if ($limit <= 0) {
            $limit = 100;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }
        $influencerIds = $this->normalizeIdList($payload['influencer_ids'] ?? []);

        $query = InfluencerModel::order('id', 'asc');
        $query = $this->scopeTenant($query, 'influencers');
        if ($influencerIds !== []) {
            $query->whereIn('id', $influencerIds);
        } else {
            $keyword = trim((string) ($payload['keyword'] ?? ''));
            $status = (string) ($payload['status'] ?? '');
            $categoryId = (int) ($payload['category_id'] ?? 0);
            $region = trim((string) ($payload['region'] ?? ''));
            $qualityGrade = strtoupper(trim((string) ($payload['quality_grade'] ?? '')));

            if ($keyword !== '') {
                $query->where(function ($sub) use ($keyword): void {
                    $sub->whereLike('tiktok_id', '%' . $keyword . '%')
                        ->whereOr('nickname', 'like', '%' . $keyword . '%');
                });
            }
            if ($status !== '') {
                $query->where('status', (int) $status);
            } else {
                $query->where('status', '<>', 6);
            }
            if ($categoryId > 0) {
                $query->where('category_id', $categoryId);
            }
            if ($region !== '') {
                $query->where('region', $region);
            }
            if ($qualityGrade !== '') {
                $query->where('quality_grade', $qualityGrade);
            }
            $query->limit($limit);
        }

        $rows = $query->field('id,tiktok_id,nickname,region,contact_info,last_commented_at,quality_score,quality_grade')
            ->select()
            ->toArray();
        if ($rows === []) {
            return $this->jsonOk([
                'created' => 0,
                'skipped_existing' => 0,
                'blocked_24h' => 0,
                'total_candidates' => 0,
            ]);
        }

        $ids = array_values(array_unique(array_map(static function ($row): int {
            return (int) ($row['id'] ?? 0);
        }, $rows)));
        $ids = array_values(array_filter($ids, static fn($v) => $v > 0));
        if ($ids === []) {
            return $this->jsonOk([
                'created' => 0,
                'skipped_existing' => 0,
                'blocked_24h' => 0,
                'total_candidates' => 0,
            ]);
        }

        $openMap = [];
        $openRowsQuery = MobileActionTaskModel::whereIn('influencer_id', $ids)
            ->whereIn('task_status', [
                MobileOutreachService::STATUS_PENDING,
                MobileOutreachService::STATUS_ASSIGNED,
                MobileOutreachService::STATUS_PREPARED,
            ])
            ->field('influencer_id,task_type');
        $openRows = $this->scopeTenant($openRowsQuery, 'mobile_action_tasks')
            ->select()
            ->toArray();
        foreach ($openRows as $openRow) {
            $k = (int) ($openRow['influencer_id'] ?? 0) . '|' . MobileOutreachService::normalizeTaskType((string) ($openRow['task_type'] ?? ''));
            $openMap[$k] = 1;
        }

        $created = 0;
        $skippedExisting = 0;
        $blocked24h = 0;
        foreach ($rows as $row) {
            $influencerId = (int) ($row['id'] ?? 0);
            if ($influencerId <= 0) {
                continue;
            }
            $taskKey = $influencerId . '|' . $taskType;
            if (isset($openMap[$taskKey])) {
                ++$skippedExisting;
                continue;
            }
            if (MobileOutreachService::isCommentTask($taskType)
                && MobileOutreachService::isCommentLocked((string) ($row['last_commented_at'] ?? ''), 24)
            ) {
                ++$blocked24h;
                continue;
            }

            $channels = InfluencerService::contactChannelsFromStored((string) ($row['contact_info'] ?? ''));
            $priority = $forcePriority !== null
                ? $forcePriority
                : MobileOutreachService::inferPriority(
                    (int) round((float) ($row['quality_score'] ?? 0)),
                    (string) ($row['quality_grade'] ?? '')
                );

            $taskPayload = MobileOutreachService::buildTaskPayload(
                $row,
                $taskType,
                $channels,
                [
                    'preferred_channel' => $preferredChannel,
                    'template_id' => $templateId,
                    'product_id' => $productId,
                    'remark' => $remark,
                    'comment_text' => (string) ($payload['comment_text'] ?? ''),
                ]
            );
            $targetChannel = (string) ($taskPayload['target_channel'] ?? 'auto');
            $record = $this->withTenantPayload([
                'influencer_id' => $influencerId,
                'task_type' => $taskType,
                'target_channel' => mb_substr($targetChannel, 0, 24),
                'priority' => $priority,
                'task_status' => MobileOutreachService::STATUS_PENDING,
                'payload_json' => json_encode($taskPayload, JSON_UNESCAPED_UNICODE),
                'retry_count' => 0,
                'max_retries' => 2,
                'created_by' => AdminAuthService::userId() > 0 ? AdminAuthService::userId() : null,
            ], 'mobile_action_tasks');
            MobileActionTaskModel::create($record);
            $openMap[$taskKey] = 1;
            ++$created;
        }

        return $this->jsonOk([
            'created' => $created,
            'skipped_existing' => $skippedExisting,
            'blocked_24h' => $blocked24h,
            'total_candidates' => count($rows),
        ], 'created');
    }

    public function retry()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $ids = $this->normalizeIdList($payload['task_ids'] ?? []);
        if ($ids === [] && isset($payload['task_id'])) {
            $singleId = (int) $payload['task_id'];
            if ($singleId > 0) {
                $ids = [$singleId];
            }
        }
        if ($ids === []) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $force = (int) ($payload['force'] ?? 0) === 1;

        $rowsQuery = MobileActionTaskModel::whereIn('id', $ids);
        $rowsQuery = $this->scopeTenant($rowsQuery, 'mobile_action_tasks');
        $rows = $rowsQuery->select();
        if ($rows->isEmpty()) {
            return $this->jsonErr('not_found', 1, null, 'common.notFound');
        }

        $retried = 0;
        $skipped = 0;
        foreach ($rows as $row) {
            $status = (int) ($row->task_status ?? 0);
            $retryCount = (int) ($row->retry_count ?? 0);
            $maxRetries = max(0, (int) ($row->max_retries ?? 0));
            $canRetryStatus = in_array($status, [
                MobileOutreachService::STATUS_FAILED,
                MobileOutreachService::STATUS_SKIPPED,
                MobileOutreachService::STATUS_CANCELED,
            ], true);
            if (!$canRetryStatus && !$force) {
                ++$skipped;
                continue;
            }
            if (!$force && $retryCount >= $maxRetries) {
                ++$skipped;
                continue;
            }

            $row->task_status = MobileOutreachService::STATUS_PENDING;
            $row->device_id = null;
            $row->assigned_at = null;
            $row->prepared_at = null;
            $row->completed_at = null;
            $row->last_error_code = null;
            $row->last_error_message = null;
            $row->last_error_at = null;
            $row->retry_count = $retryCount + 1;
            $row->save();
            ++$retried;
        }

        return $this->jsonOk([
            'retried' => $retried,
            'skipped' => $skipped,
        ], 'retried');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapTaskRow(array $row): array
    {
        $payload = [];
        $payloadRaw = trim((string) ($row['payload_json'] ?? ''));
        if ($payloadRaw !== '') {
            $decoded = json_decode($payloadRaw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'influencer_id' => (int) ($row['influencer_id'] ?? 0),
            'task_type' => (string) ($row['task_type'] ?? ''),
            'target_channel' => (string) ($row['target_channel'] ?? 'auto'),
            'priority' => (int) ($row['priority'] ?? 0),
            'task_status' => (int) ($row['task_status'] ?? 0),
            'device_id' => (int) ($row['device_id'] ?? 0),
            'device_code' => (string) ($row['device_code'] ?? ''),
            'device_name' => (string) ($row['device_name'] ?? ''),
            'retry_count' => (int) ($row['retry_count'] ?? 0),
            'max_retries' => (int) ($row['max_retries'] ?? 0),
            'last_error_code' => (string) ($row['last_error_code'] ?? ''),
            'last_error_message' => (string) ($row['last_error_message'] ?? ''),
            'scheduled_at' => (string) ($row['scheduled_at'] ?? ''),
            'assigned_at' => (string) ($row['assigned_at'] ?? ''),
            'prepared_at' => (string) ($row['prepared_at'] ?? ''),
            'completed_at' => (string) ($row['completed_at'] ?? ''),
            'tiktok_id' => (string) ($row['tiktok_id'] ?? ''),
            'nickname' => (string) ($row['nickname'] ?? ''),
            'category_name' => (string) ($row['category_name'] ?? ''),
            'avatar_url' => (string) ($row['avatar_url'] ?? ''),
            'region' => (string) ($row['region'] ?? ''),
            'influencer_status' => (int) ($row['influencer_status'] ?? 0),
            'quality_score' => isset($row['quality_score']) ? (float) $row['quality_score'] : 0.0,
            'last_contacted_at' => (string) ($row['last_contacted_at'] ?? ''),
            'last_commented_at' => (string) ($row['last_commented_at'] ?? ''),
            'payload' => $payload,
            'rendered_text' => (string) ($row['rendered_text'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function buildDashboardSummary(): array
    {
        $tenantId = $this->currentTenantId();
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $todayTaskQuery = Db::name('mobile_action_tasks')->whereBetween('created_at', [$todayStart, $todayEnd]);
        if ($this->tableHasTenantId('mobile_action_tasks')) {
            $todayTaskQuery->where('tenant_id', $tenantId);
        }
        $todayTotalTasks = (int) $todayTaskQuery->count();

        $contactedQuery = Db::name('influencers')
            ->whereBetween('last_contacted_at', [$todayStart, $todayEnd]);
        if ($this->tableHasTenantId('influencers')) {
            $contactedQuery->where('tenant_id', $tenantId);
        }
        $contactedToday = (int) $contactedQuery->count();

        $repliedQuery = Db::name('influencers')->where('status', 2);
        if ($this->tableHasTenantId('influencers')) {
            $repliedQuery->where('tenant_id', $tenantId);
        }
        $repliedCount = (int) $repliedQuery->count();

        $pendingReplyQuery = Db::name('influencers')->where('status', 1);
        if ($this->tableHasTenantId('influencers')) {
            $pendingReplyQuery->where('tenant_id', $tenantId);
        }
        $pendingReply = (int) $pendingReplyQuery->count();

        $sampledQuery = Db::name('influencers')->where('status', 4);
        if ($this->tableHasTenantId('influencers')) {
            $sampledQuery->where('tenant_id', $tenantId);
        }
        $sampleShipped = (int) $sampledQuery->count();

        $waitSampleQuery = Db::name('influencers')->where('status', 3);
        if ($this->tableHasTenantId('influencers')) {
            $waitSampleQuery->where('tenant_id', $tenantId);
        }
        $waitSampleCount = (int) $waitSampleQuery->count();

        $creatorTotalQuery = Db::name('influencers');
        if ($this->tableHasTenantId('influencers')) {
            $creatorTotalQuery->where('tenant_id', $tenantId);
        }
        $creatorTotal = (int) $creatorTotalQuery->count();

        return [
            'today_total_tasks' => $todayTotalTasks,
            'today_contacted' => $contactedToday,
            'reached_count' => $contactedToday,
            'replied_count' => $repliedCount,
            'pending_reply' => $pendingReply,
            'sample_shipped' => $sampleShipped,
            'wait_sample_count' => $waitSampleCount,
            'influencer_total' => $creatorTotal,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonOrPost(): array
    {
        $raw = (string) $this->request->getContent();
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return $this->request->post();
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function normalizeIdList($raw): array
    {
        $ids = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $id = (int) $item;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
            foreach ($parts as $part) {
                $id = (int) $part;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) > 2000) {
            $ids = array_slice($ids, 0, 2000);
        }

        return $ids;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function appendTaskLog(int $tenantId, array $payload): void
    {
        $insert = $this->withTenantPayload([
            'task_id' => (int) ($payload['task_id'] ?? 0),
            'device_id' => (int) ($payload['device_id'] ?? 0) > 0 ? (int) $payload['device_id'] : null,
            'influencer_id' => (int) ($payload['influencer_id'] ?? 0) > 0 ? (int) $payload['influencer_id'] : null,
            'event_type' => mb_substr((string) ($payload['event_type'] ?? 'report'), 0, 32),
            'event_status' => (int) ($payload['event_status'] ?? 0),
            'error_code' => isset($payload['error_code']) && $payload['error_code'] !== '' ? mb_substr((string) $payload['error_code'], 0, 64) : null,
            'error_message' => isset($payload['error_message']) && $payload['error_message'] !== '' ? mb_substr((string) $payload['error_message'], 0, 255) : null,
            'duration_ms' => max(0, (int) ($payload['duration_ms'] ?? 0)),
            'screenshot_path' => isset($payload['screenshot_path']) && $payload['screenshot_path'] !== '' ? mb_substr((string) $payload['screenshot_path'], 0, 255) : null,
            'payload_json' => isset($payload['payload_json']) ? (string) $payload['payload_json'] : null,
        ], 'mobile_action_logs');
        if ($this->tableHasTenantId('mobile_action_logs')) {
            $insert['tenant_id'] = $tenantId;
        }
        MobileActionLogModel::create($insert);
    }

    private function columnExists(string $table, string $column): bool
    {
        $table = strtolower(trim($table));
        $column = strtolower(trim($column));
        if ($table === '' || $column === '') {
            return false;
        }
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$COLUMN_CACHE)) {
            return self::$COLUMN_CACHE[$key];
        }
        try {
            $fields = Db::name($table)->getFields();
            $exists = is_array($fields) && array_key_exists($column, $fields);
        } catch (\Throwable $e) {
            $exists = false;
        }
        self::$COLUMN_CACHE[$key] = $exists;
        return $exists;
    }
}
