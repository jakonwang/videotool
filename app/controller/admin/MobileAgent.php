<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\model\AutoDmCampaign as AutoDmCampaignModel;
use app\model\AutoDmEvent as AutoDmEventModel;
use app\model\AutoDmTask as AutoDmTaskModel;
use app\BaseController;
use app\model\Influencer as InfluencerModel;
use app\model\MobileActionLog as MobileActionLogModel;
use app\model\MobileActionTask as MobileActionTaskModel;
use app\model\OutreachLog as OutreachLogModel;
use app\service\AutoDmService;
use app\service\InfluencerService;
use app\service\InfluencerStatusFlowService;
use app\service\MessageOutreachService;
use app\service\MobileOutreachService;
use think\facade\Db;

class MobileAgent extends BaseController
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

    public function pull()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        [$device, $payload, $error] = $this->resolveDeviceFromRequest();
        if ($error !== null) {
            return $error;
        }
        if (!$device) {
            return $this->jsonErr('device_not_found', 404, null, 'common.notFound');
        }
        $deviceId = (int) ($device['id'] ?? 0);
        $tenantId = max(1, (int) ($device['tenant_id'] ?? 1));
        if ($deviceId <= 0) {
            return $this->jsonErr('device_not_found', 404, null, 'common.notFound');
        }
        if ((int) ($device['status'] ?? 1) !== 1) {
            return $this->jsonErr('device_disabled', 403, null, 'common.forbidden');
        }

        $this->resetDailyUsageIfNeeded($device);
        $this->touchDeviceHeartbeat($deviceId);
        $device = Db::name('mobile_devices')->where('id', $deviceId)->find();
        if (!$device) {
            return $this->jsonErr('device_not_found', 404, null, 'common.notFound');
        }

        $cooldownUntil = trim((string) ($device['cooldown_until'] ?? ''));
        if ($cooldownUntil !== '') {
            $coolTs = strtotime($cooldownUntil);
            if ($coolTs !== false && $coolTs > time()) {
                return $this->jsonOk([
                    'task' => null,
                    'reason' => 'device_cooling',
                    'cooldown_until' => $cooldownUntil,
                ]);
            }
        }

        $dailyQuota = max(0, (int) ($device['daily_quota'] ?? 0));
        $dailyUsed = max(0, (int) ($device['daily_used'] ?? 0));
        if ($dailyQuota > 0 && $dailyUsed >= $dailyQuota) {
            return $this->jsonOk([
                'task' => null,
                'reason' => 'quota_exceeded',
                'daily_quota' => $dailyQuota,
                'daily_used' => $dailyUsed,
            ]);
        }

        $requestedTypes = $this->normalizeTaskTypeFilters($payload['task_types'] ?? []);
        $task = null;
        Db::transaction(function () use ($tenantId, $deviceId, $requestedTypes, &$task): void {
            $query = Db::name('mobile_action_tasks')
                ->where('task_status', MobileOutreachService::STATUS_PENDING)
                ->where(function ($sub): void {
                    $sub->whereNull('scheduled_at')->whereOr('scheduled_at', '<=', date('Y-m-d H:i:s'));
                })
                ->order('priority', 'desc')
                ->order('id', 'asc');
            if ($this->tableHasTenantId('mobile_action_tasks')) {
                $query->where('tenant_id', $tenantId);
            }
            if ($requestedTypes !== []) {
                $query->whereIn('task_type', $requestedTypes);
            }
            $row = $query->find();
            if (!$row) {
                return;
            }
            $affected = Db::name('mobile_action_tasks')
                ->where('id', (int) $row['id'])
                ->where('task_status', MobileOutreachService::STATUS_PENDING)
                ->update([
                    'task_status' => MobileOutreachService::STATUS_ASSIGNED,
                    'device_id' => $deviceId,
                    'assigned_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            if ((int) $affected <= 0) {
                return;
            }
            $task = Db::name('mobile_action_tasks')->where('id', (int) $row['id'])->find();
        });

        if (!$task) {
            return $this->jsonOk(['task' => null, 'reason' => 'empty_queue']);
        }

        $taskPayload = $this->decodeJsonObject((string) ($task['payload_json'] ?? ''));
        $influencerQuery = Db::name('influencers')
            ->where('id', (int) ($task['influencer_id'] ?? 0))
            ->field('id,tiktok_id,nickname,region,last_contacted_at,last_commented_at');
        if ($this->tableHasTenantId('influencers')) {
            $influencerQuery->where('tenant_id', $tenantId);
        }
        $influencer = $influencerQuery->find();
        $this->appendTaskLog([
            'tenant_id' => $tenantId,
            'task_id' => (int) ($task['id'] ?? 0),
            'device_id' => $deviceId,
            'influencer_id' => (int) ($task['influencer_id'] ?? 0),
            'event_type' => 'assigned',
            'event_status' => 1,
            'payload_json' => json_encode([
                'requested_types' => $requestedTypes,
                'agent_version' => (string) ($payload['agent_version'] ?? ''),
            ], JSON_UNESCAPED_UNICODE),
        ]);

        return $this->jsonOk([
            'task' => [
                'id' => (int) ($task['id'] ?? 0),
                'task_type' => (string) ($task['task_type'] ?? ''),
                'target_channel' => (string) ($task['target_channel'] ?? 'auto'),
                'priority' => (int) ($task['priority'] ?? 0),
                'payload' => $taskPayload,
                'rendered_text' => (string) ($task['rendered_text'] ?? ''),
                'execution_hint' => 'auto_fill_then_wait_manual_send',
                'influencer' => [
                    'id' => (int) ($influencer['id'] ?? 0),
                    'tiktok_id' => (string) ($influencer['tiktok_id'] ?? ''),
                    'nickname' => (string) ($influencer['nickname'] ?? ''),
                    'region' => (string) ($influencer['region'] ?? ''),
                    'last_contacted_at' => (string) ($influencer['last_contacted_at'] ?? ''),
                    'last_commented_at' => (string) ($influencer['last_commented_at'] ?? ''),
                ],
            ],
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    public function report()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        [$device, $payload, $error] = $this->resolveDeviceFromRequest();
        if ($error !== null) {
            return $error;
        }
        if (!$device) {
            return $this->jsonErr('device_not_found', 404, null, 'common.notFound');
        }
        $deviceId = (int) ($device['id'] ?? 0);
        $tenantId = max(1, (int) ($device['tenant_id'] ?? 1));
        if ($deviceId <= 0) {
            return $this->jsonErr('device_not_found', 404, null, 'common.notFound');
        }
        $this->touchDeviceHeartbeat($deviceId);

        $taskId = (int) ($payload['task_id'] ?? 0);
        $eventRaw = trim((string) ($payload['event'] ?? ''));
        if ($taskId <= 0) {
            $this->appendTaskLog([
                'tenant_id' => $tenantId,
                'task_id' => 0,
                'device_id' => $deviceId,
                'influencer_id' => 0,
                'event_type' => $eventRaw !== '' ? $eventRaw : 'heartbeat',
                'event_status' => 1,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            return $this->jsonOk(['accepted' => true], 'heartbeat');
        }

        $taskQuery = MobileActionTaskModel::where('id', $taskId);
        if ($this->tableHasTenantId('mobile_action_tasks')) {
            $taskQuery->where('tenant_id', $tenantId);
        }
        $task = $taskQuery->find();
        if (!$task) {
            return $this->jsonErr('task_not_found', 404, null, 'common.notFound');
        }
        if ((int) ($task->device_id ?? 0) > 0 && (int) $task->device_id !== $deviceId) {
            return $this->jsonErr('task_device_mismatch', 403, null, 'common.forbidden');
        }

        $event = MobileOutreachService::normalizeActionEvent($eventRaw, (string) ($task->task_type ?? ''));
        $nextStatus = MobileOutreachService::mapReportEventToStatus($eventRaw);
        $now = date('Y-m-d H:i:s');
        $renderedText = trim((string) ($payload['rendered_text'] ?? $payload['text'] ?? ''));
        $errorCode = trim((string) ($payload['error_code'] ?? ''));
        $errorMessage = trim((string) ($payload['error_message'] ?? ''));
        $durationMs = max(0, (int) ($payload['duration_ms'] ?? 0));
        $screenshotPath = trim((string) ($payload['screenshot_path'] ?? ''));

        Db::transaction(function () use (
            $task,
            $event,
            $eventRaw,
            $nextStatus,
            $now,
            $renderedText,
            $errorCode,
            $errorMessage,
            $durationMs,
            $screenshotPath,
            $payload,
            $deviceId,
            $tenantId
        ): void {
            $task->device_id = $deviceId;
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

            $eventStatus = $nextStatus === MobileOutreachService::STATUS_FAILED ? 2 : 1;
            $this->appendTaskLog([
                'tenant_id' => $tenantId,
                'task_id' => (int) $task->id,
                'device_id' => $deviceId,
                'influencer_id' => (int) ($task->influencer_id ?? 0),
                'event_type' => $event !== '' ? $event : ($eventRaw !== '' ? $eventRaw : 'report'),
                'event_status' => $eventStatus,
                'error_code' => $errorCode !== '' ? mb_substr($errorCode, 0, 64) : null,
                'error_message' => $errorMessage !== '' ? mb_substr($errorMessage, 0, 255) : null,
                'duration_ms' => $durationMs,
                'screenshot_path' => $screenshotPath !== '' ? mb_substr($screenshotPath, 0, 255) : null,
                'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);

            if (MobileOutreachService::shouldTouchLastCommentedAt($eventRaw, (string) ($task->task_type ?? ''))) {
                $query = InfluencerModel::where('id', (int) ($task->influencer_id ?? 0));
                if ($this->tableHasTenantId('influencers')) {
                    $query->where('tenant_id', $tenantId);
                }
                $query->update(['last_commented_at' => $now]);
            }
            if (MobileOutreachService::shouldTouchLastContactedAt($eventRaw, (string) ($task->task_type ?? ''))) {
                $query = InfluencerModel::where('id', (int) ($task->influencer_id ?? 0));
                if ($this->tableHasTenantId('influencers')) {
                    $query->where('tenant_id', $tenantId);
                }
                $query->update(['last_contacted_at' => $now]);
                InfluencerStatusFlowService::transition(
                    (int) ($task->influencer_id ?? 0),
                    1,
                    'mobile_agent',
                    '',
                    ['event' => $event, 'task_id' => (int) $task->id],
                    true
                );
            }

            if (in_array($event, [
                MobileOutreachService::EVENT_COMMENT_PREPARED,
                MobileOutreachService::EVENT_COMMENT_SENT,
                MobileOutreachService::EVENT_DM_PREPARED,
                MobileOutreachService::EVENT_IM_PREPARED,
            ], true)) {
                $taskPayload = $this->decodeJsonObject((string) ($task->payload_json ?? ''));
                $templateId = (int) ($taskPayload['template_id'] ?? 0);
                $productId = (int) ($taskPayload['product_id'] ?? 0);
                $templateName = '';
                $templateLang = 'zh';
                if ($templateId > 0) {
                    $tplQuery = Db::name('message_templates')
                        ->where('id', $templateId)
                        ->field('id,name,lang');
                    if ($this->tableHasTenantId('message_templates')) {
                        $tplQuery->where('tenant_id', $tenantId);
                    }
                    $tpl = $tplQuery->find();
                    if ($tpl) {
                        $templateName = (string) ($tpl['name'] ?? '');
                        $templateLang = (string) ($tpl['lang'] ?? 'zh');
                    }
                }
                $productName = '';
                if ($productId > 0) {
                    $prodQuery = Db::name('products')
                        ->where('id', $productId)
                        ->field('id,name');
                    if ($this->tableHasTenantId('products')) {
                        $prodQuery->where('tenant_id', $tenantId);
                    }
                    $prod = $prodQuery->find();
                    if ($prod) {
                        $productName = (string) ($prod['name'] ?? '');
                    }
                }
                $logPayload = $this->withTenantPayload([
                    'influencer_id' => (int) ($task->influencer_id ?? 0),
                    'template_id' => $templateId,
                    'template_name' => $templateName,
                    'template_lang' => $templateLang,
                    'product_id' => $productId > 0 ? $productId : null,
                    'product_name' => $productName !== '' ? $productName : null,
                    'channel' => mb_substr($event, 0, 32),
                    'rendered_body' => $renderedText !== '' ? $renderedText : (string) ($task->rendered_text ?? ''),
                ], 'outreach_logs');
                OutreachLogModel::create($logPayload);
            }

            $this->applyDeviceRiskWindow($deviceId, $nextStatus, $now);
        });

        return $this->jsonOk([
            'accepted' => true,
            'task_status' => $nextStatus,
            'event' => $event,
        ]);
    }

    public function pullAuto()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        [$device, $payload, $error] = $this->resolveDeviceFromRequest();
        if ($error !== null) {
            return $error;
        }
        if (!$device) {
            return $this->jsonErr('device_not_found', 404, null, 'common.notFound');
        }
        $deviceId = (int) ($device['id'] ?? 0);
        $tenantId = max(1, (int) ($device['tenant_id'] ?? 1));
        if ($deviceId <= 0) {
            return $this->jsonErr('device_not_found', 404, null, 'common.notFound');
        }
        if ((int) ($device['status'] ?? 1) !== 1) {
            return $this->jsonErr('device_disabled', 403, null, 'common.forbidden');
        }

        $this->resetDailyUsageIfNeeded($device);
        $this->touchDeviceHeartbeat($deviceId);
        $device = Db::name('mobile_devices')->where('id', $deviceId)->find();
        if (!$device) {
            return $this->jsonErr('device_not_found', 404, null, 'common.notFound');
        }

        $cooldownUntil = trim((string) ($device['cooldown_until'] ?? ''));
        if ($cooldownUntil !== '') {
            $coolTs = strtotime($cooldownUntil);
            if ($coolTs !== false && $coolTs > time()) {
                return $this->jsonOk([
                    'task' => null,
                    'reason' => 'device_cooling',
                    'cooldown_until' => $cooldownUntil,
                ]);
            }
        }

        $dailyQuota = max(0, (int) ($device['daily_quota'] ?? 0));
        $dailyUsed = max(0, (int) ($device['daily_used'] ?? 0));
        if ($dailyQuota > 0 && $dailyUsed >= $dailyQuota) {
            return $this->jsonOk([
                'task' => null,
                'reason' => 'quota_exceeded',
                'daily_quota' => $dailyQuota,
                'daily_used' => $dailyUsed,
            ]);
        }

        $requestedTypes = $this->normalizeAutoTaskTypeFilters($payload['task_types'] ?? []);
        $picked = null;
        $scanReason = '';
        $scanTimes = 0;
        while ($scanTimes < 30 && $picked === null) {
            ++$scanTimes;
            $candidate = null;
            Db::transaction(function () use ($tenantId, $deviceId, $requestedTypes, &$candidate): void {
                $query = Db::name('auto_dm_tasks')->alias('t')
                    ->join('auto_dm_campaigns c', 'c.id = t.campaign_id')
                    ->where('t.task_status', AutoDmService::TASK_STATUS_PENDING)
                    ->where('c.campaign_status', AutoDmService::CAMPAIGN_STATUS_RUNNING)
                    ->where(function ($sub): void {
                        $sub->whereNull('t.scheduled_at')->whereOr('t.scheduled_at', '<=', date('Y-m-d H:i:s'));
                    })
                    ->field([
                        't.*',
                        'c.id as c_id',
                        'c.campaign_name',
                        'c.template_id as c_template_id',
                        'c.product_id as c_product_id',
                        'c.campaign_status',
                        'c.daily_limit',
                        'c.time_window_start',
                        'c.time_window_end',
                        'c.min_interval_sec',
                        'c.fail_fuse_threshold',
                        'c.preferred_channel as c_preferred_channel',
                    ])
                    ->order('t.priority', 'desc')
                    ->order('t.id', 'asc');
                if ($this->tableHasTenantId('auto_dm_tasks')) {
                    $query->where('t.tenant_id', $tenantId);
                }
                if ($this->tableHasTenantId('auto_dm_campaigns')) {
                    $query->where('c.tenant_id', $tenantId);
                }
                if ($requestedTypes !== []) {
                    $query->whereIn('t.task_type', $requestedTypes);
                }
                $row = $query->find();
                if (!$row) {
                    return;
                }
                $affected = Db::name('auto_dm_tasks')
                    ->where('id', (int) $row['id'])
                    ->where('task_status', AutoDmService::TASK_STATUS_PENDING)
                    ->update([
                        'task_status' => AutoDmService::TASK_STATUS_ASSIGNED,
                        'device_id' => $deviceId,
                        'assigned_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                if ((int) $affected <= 0) {
                    return;
                }
                $candidate = Db::name('auto_dm_tasks')->alias('t')
                    ->join('auto_dm_campaigns c', 'c.id = t.campaign_id')
                    ->where('t.id', (int) $row['id'])
                    ->field([
                        't.*',
                        'c.id as c_id',
                        'c.campaign_name',
                        'c.template_id as c_template_id',
                        'c.product_id as c_product_id',
                        'c.campaign_status',
                        'c.daily_limit',
                        'c.time_window_start',
                        'c.time_window_end',
                        'c.min_interval_sec',
                        'c.fail_fuse_threshold',
                        'c.preferred_channel as c_preferred_channel',
                    ])
                    ->find();
            });

            if (!$candidate) {
                break;
            }
            $decision = $this->validateAutoDispatchCandidate($candidate, $tenantId);
            if (!(bool) ($decision['ok'] ?? false)) {
                $status = (int) ($decision['task_status'] ?? AutoDmService::TASK_STATUS_BLOCKED);
                $code = (string) ($decision['error_code'] ?? 'filtered');
                $message = (string) ($decision['error_message'] ?? $code);
                $scanReason = $code;
                $this->settleAutoTaskBeforeDispatch($candidate, $status, $code, $message, $tenantId);
                continue;
            }
            $picked = [
                'task' => $candidate,
                'campaign' => (array) ($decision['campaign'] ?? []),
                'influencer' => (array) ($decision['influencer'] ?? []),
            ];
        }

        if ($picked === null) {
            return $this->jsonOk([
                'task' => null,
                'reason' => $scanReason !== '' ? $scanReason : 'empty_queue',
            ]);
        }

        $task = (array) ($picked['task'] ?? []);
        $campaign = (array) ($picked['campaign'] ?? []);
        $influencer = (array) ($picked['influencer'] ?? []);
        $taskId = (int) ($task['id'] ?? 0);
        $payloadObj = AutoDmService::decodeJsonObject((string) ($task['payload_json'] ?? ''));
        $renderedText = trim((string) ($task['rendered_text'] ?? ''));

        if ($renderedText === '') {
            $templateId = (int) ($payloadObj['template_id'] ?? ($campaign['template_id'] ?? 0));
            $productId = (int) ($payloadObj['product_id'] ?? ($campaign['product_id'] ?? 0));
            $renderMeta = AutoDmService::renderCampaignText($tenantId, (int) ($task['influencer_id'] ?? 0), $templateId, $productId);
            $renderedText = trim((string) ($renderMeta['text'] ?? ''));
            if ($renderedText !== '' && $taskId > 0) {
                Db::name('auto_dm_tasks')->where('id', $taskId)->update([
                    'rendered_text' => $renderedText,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->appendAutoEvent([
            'tenant_id' => $tenantId,
            'campaign_id' => (int) ($task['campaign_id'] ?? 0),
            'task_id' => $taskId,
            'device_id' => $deviceId,
            'influencer_id' => (int) ($task['influencer_id'] ?? 0),
            'event_type' => AutoDmService::EVENT_ASSIGNED,
            'event_status' => 1,
            'payload_json' => AutoDmService::encodeJson([
                'requested_types' => $requestedTypes,
                'agent_version' => (string) ($payload['agent_version'] ?? ''),
            ]),
        ]);

        return $this->jsonOk([
            'task' => [
                'id' => $taskId,
                'task_type' => (string) ($task['task_type'] ?? ''),
                'target_channel' => (string) ($task['target_channel'] ?? ''),
                'priority' => (int) ($task['priority'] ?? 0),
                'payload' => $payloadObj,
                'rendered_text' => $renderedText,
                'execution_hint' => 'auto_send_unattended',
                'campaign' => [
                    'id' => (int) ($campaign['id'] ?? 0),
                    'campaign_name' => (string) ($campaign['campaign_name'] ?? ''),
                    'daily_limit' => (int) ($campaign['daily_limit'] ?? 0),
                    'time_window_start' => (string) ($campaign['time_window_start'] ?? ''),
                    'time_window_end' => (string) ($campaign['time_window_end'] ?? ''),
                    'min_interval_sec' => (int) ($campaign['min_interval_sec'] ?? 0),
                    'fail_fuse_threshold' => (int) ($campaign['fail_fuse_threshold'] ?? 0),
                ],
                'influencer' => [
                    'id' => (int) ($influencer['id'] ?? 0),
                    'tiktok_id' => (string) ($influencer['tiktok_id'] ?? ''),
                    'nickname' => (string) ($influencer['nickname'] ?? ''),
                    'region' => (string) ($influencer['region'] ?? ''),
                    'last_contacted_at' => (string) ($influencer['last_contacted_at'] ?? ''),
                    'last_auto_dm_at' => (string) ($influencer['last_auto_dm_at'] ?? ''),
                ],
            ],
            'server_time' => date('Y-m-d H:i:s'),
        ]);
    }

    public function reportAuto()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        [$device, $payload, $error] = $this->resolveDeviceFromRequest();
        if ($error !== null) {
            return $error;
        }
        if (!$device) {
            return $this->jsonErr('device_not_found', 404, null, 'common.notFound');
        }
        $deviceId = (int) ($device['id'] ?? 0);
        $tenantId = max(1, (int) ($device['tenant_id'] ?? 1));
        if ($deviceId <= 0) {
            return $this->jsonErr('device_not_found', 404, null, 'common.notFound');
        }
        $this->touchDeviceHeartbeat($deviceId);

        $taskId = (int) ($payload['task_id'] ?? 0);
        $eventRaw = trim((string) ($payload['event'] ?? ''));
        if ($taskId <= 0) {
            $this->appendAutoEvent([
                'tenant_id' => $tenantId,
                'campaign_id' => 0,
                'task_id' => null,
                'device_id' => $deviceId,
                'influencer_id' => null,
                'event_type' => $eventRaw !== '' ? $eventRaw : 'heartbeat',
                'event_status' => 1,
                'payload_json' => AutoDmService::encodeJson($payload),
            ]);
            return $this->jsonOk(['accepted' => true], 'heartbeat');
        }

        $taskQuery = AutoDmTaskModel::where('id', $taskId);
        if ($this->tableHasTenantId('auto_dm_tasks')) {
            $taskQuery->where('tenant_id', $tenantId);
        }
        $task = $taskQuery->find();
        if (!$task) {
            return $this->jsonErr('task_not_found', 404, null, 'common.notFound');
        }
        if ((int) ($task->device_id ?? 0) > 0 && (int) $task->device_id !== $deviceId) {
            return $this->jsonErr('task_device_mismatch', 403, null, 'common.forbidden');
        }

        $event = $this->normalizeAutoReportEvent($eventRaw);
        $nextStatus = $this->mapAutoReportEventToStatus($event);
        $now = date('Y-m-d H:i:s');
        $renderedText = trim((string) ($payload['rendered_text'] ?? $payload['text'] ?? ''));
        $errorCode = trim((string) ($payload['error_code'] ?? ''));
        $errorMessage = trim((string) ($payload['error_message'] ?? ''));
        $durationMs = max(0, (int) ($payload['duration_ms'] ?? 0));
        $screenshotPath = trim((string) ($payload['screenshot_path'] ?? ''));
        $replyText = trim((string) ($payload['reply_text'] ?? ''));
        $deliveryStatus = mb_substr(trim((string) ($payload['delivery_status'] ?? '')), 0, 32);
        $replyDetected = (int) ($payload['reply_detected'] ?? 0) === 1 || $replyText !== '';
        $replyTimeRaw = trim((string) ($payload['reply_time'] ?? ''));
        $replyTs = $replyTimeRaw !== '' ? strtotime($replyTimeRaw) : false;
        $replyAt = $replyTs !== false ? date('Y-m-d H:i:s', $replyTs) : $now;
        $conversationSnippet = mb_substr(trim((string) ($payload['conversation_snippet'] ?? '')), 0, 500);

        $campaign = [];
        $campaignQuery = Db::name('auto_dm_campaigns')->where('id', (int) ($task->campaign_id ?? 0));
        if ($this->tableHasTenantId('auto_dm_campaigns')) {
            $campaignQuery->where('tenant_id', $tenantId);
        }
        $campaignRow = $campaignQuery->find();
        if (is_array($campaignRow)) {
            $campaign = $campaignRow;
        }
        $policy = AutoDmService::loadPolicy($tenantId);

        Db::transaction(function () use (
            $task,
            $event,
            $nextStatus,
            $now,
            $renderedText,
            $errorCode,
            $errorMessage,
            $durationMs,
            $screenshotPath,
            $payload,
            $deviceId,
            $tenantId,
            $campaign,
            $policy,
            $replyText,
            $deliveryStatus,
            $replyDetected,
            $replyAt,
            $conversationSnippet
        ): void {
            $task->device_id = $deviceId;
            $task->task_status = $nextStatus;
            if ($renderedText !== '') {
                $task->rendered_text = $renderedText;
            }
            if ($this->columnExists('auto_dm_tasks', 'delivery_status') && $deliveryStatus !== '') {
                $task->delivery_status = $deliveryStatus;
            }
            if ($this->columnExists('auto_dm_tasks', 'conversation_snippet') && $conversationSnippet !== '') {
                $task->conversation_snippet = $conversationSnippet;
            }
            $prevReplyState = (int) ($task->reply_state ?? AutoDmService::REPLY_STATE_NONE);
            if ($replyDetected && $this->columnExists('auto_dm_tasks', 'reply_state')) {
                $task->reply_state = AutoDmService::REPLY_STATE_DETECTED;
                if ($this->columnExists('auto_dm_tasks', 'reply_text') && $replyText !== '') {
                    $task->reply_text = $replyText;
                }
                if ($this->columnExists('auto_dm_tasks', 'reply_at')) {
                    $task->reply_at = $replyAt;
                }
            }
            if ($nextStatus === AutoDmService::TASK_STATUS_SENDING) {
                $task->sending_at = $now;
            }
            if ($nextStatus === AutoDmService::TASK_STATUS_SENT) {
                $task->sent_at = $now;
                $task->completed_at = $now;
            }
            if (in_array($nextStatus, [
                AutoDmService::TASK_STATUS_FAILED,
                AutoDmService::TASK_STATUS_BLOCKED,
                AutoDmService::TASK_STATUS_COOLING,
            ], true)) {
                $task->completed_at = $now;
            }
            if ($nextStatus === AutoDmService::TASK_STATUS_FAILED) {
                $task->last_error_code = $errorCode !== '' ? mb_substr($errorCode, 0, 64) : 'send_failed';
                $task->last_error_message = $errorMessage !== '' ? mb_substr($errorMessage, 0, 255) : 'send_failed';
                $task->last_error_at = $now;
            } elseif (in_array($nextStatus, [AutoDmService::TASK_STATUS_BLOCKED, AutoDmService::TASK_STATUS_COOLING], true)) {
                $task->last_error_code = $errorCode !== '' ? mb_substr($errorCode, 0, 64) : ($nextStatus === AutoDmService::TASK_STATUS_BLOCKED ? 'blocked' : 'cooling');
                $task->last_error_message = $errorMessage !== '' ? mb_substr($errorMessage, 0, 255) : $task->last_error_code;
                $task->last_error_at = $now;
            } else {
                $task->last_error_code = null;
                $task->last_error_message = null;
                $task->last_error_at = null;
            }
            $task->save();

            $eventStatus = $nextStatus === AutoDmService::TASK_STATUS_FAILED ? 2 : 1;
            if (in_array($nextStatus, [AutoDmService::TASK_STATUS_BLOCKED, AutoDmService::TASK_STATUS_COOLING], true)) {
                $eventStatus = 2;
            }
            $this->appendAutoEvent([
                'tenant_id' => $tenantId,
                'campaign_id' => (int) ($task->campaign_id ?? 0),
                'task_id' => (int) $task->id,
                'device_id' => $deviceId,
                'influencer_id' => (int) ($task->influencer_id ?? 0),
                'event_type' => $event,
                'event_status' => $eventStatus,
                'error_code' => $errorCode !== '' ? mb_substr($errorCode, 0, 64) : null,
                'error_message' => $errorMessage !== '' ? mb_substr($errorMessage, 0, 255) : null,
                'duration_ms' => $durationMs,
                'screenshot_path' => $screenshotPath !== '' ? mb_substr($screenshotPath, 0, 255) : null,
                'payload_json' => AutoDmService::encodeJson($payload),
            ]);
            if ($replyDetected) {
                $this->appendAutoEvent([
                    'tenant_id' => $tenantId,
                    'campaign_id' => (int) ($task->campaign_id ?? 0),
                    'task_id' => (int) $task->id,
                    'device_id' => $deviceId,
                    'influencer_id' => (int) ($task->influencer_id ?? 0),
                    'event_type' => AutoDmService::EVENT_REPLY_DETECTED,
                    'event_status' => 1,
                    'payload_json' => AutoDmService::encodeJson([
                        'reply_text' => $replyText,
                        'reply_at' => $replyAt,
                        'delivery_status' => $deliveryStatus,
                        'conversation_snippet' => $conversationSnippet,
                    ]),
                ]);
                $replyLog = $this->withTenantPayload([
                    'influencer_id' => (int) ($task->influencer_id ?? 0),
                    'template_id' => (int) ($task->variant_template_id ?? 0),
                    'channel' => 'auto_dm',
                    'rendered_body' => $replyText !== '' ? $replyText : null,
                ], 'outreach_logs');
                if ($this->columnExists('outreach_logs', 'action_type')) {
                    $replyLog['action_type'] = 'auto_dm_reply_detected';
                }
                Db::name('outreach_logs')->insert($replyLog);
            }

            $influencerId = (int) ($task->influencer_id ?? 0);
            if ($influencerId > 0) {
                $infQuery = InfluencerModel::where('id', $influencerId);
                if ($this->tableHasTenantId('influencers')) {
                    $infQuery->where('tenant_id', $tenantId);
                }
                $inf = $infQuery->find();
                if ($inf) {
                    if ($nextStatus === AutoDmService::TASK_STATUS_SENT) {
                        $inf->last_contacted_at = $now;
                        if ($this->columnExists('influencers', 'last_auto_dm_at')) {
                            $inf->last_auto_dm_at = $now;
                        }
                        if ($this->columnExists('influencers', 'auto_dm_fail_count')) {
                            $inf->auto_dm_fail_count = 0;
                        }
                        if ($this->columnExists('influencers', 'cooldown_until')) {
                            $inf->cooldown_until = null;
                        }
                        $inf->save();
                        if ((int) ($inf->status ?? 0) < 1 && (int) ($inf->status ?? 0) !== 6) {
                            InfluencerStatusFlowService::transition(
                                $influencerId,
                                1,
                                'auto_dm',
                                '',
                                ['event' => $event, 'task_id' => (int) $task->id],
                                true
                            );
                        }
                        $this->appendAutoOutreachLog($tenantId, $task, $renderedText !== '' ? $renderedText : (string) ($task->rendered_text ?? ''));
                    } elseif ($nextStatus === AutoDmService::TASK_STATUS_FAILED) {
                        if ($this->columnExists('influencers', 'auto_dm_fail_count')) {
                            $failCount = (int) ($inf->auto_dm_fail_count ?? 0) + 1;
                            $inf->auto_dm_fail_count = $failCount;
                            $threshold = max(1, (int) ($campaign['fail_fuse_threshold'] ?? 3));
                            if ($failCount >= $threshold && $this->columnExists('influencers', 'cooldown_until')) {
                                $hours = max(1, (int) ($policy['cooldown_hours'] ?? 24));
                                $inf->cooldown_until = date('Y-m-d H:i:s', time() + $hours * 3600);
                            }
                            $inf->save();
                        }
                    } elseif ($nextStatus === AutoDmService::TASK_STATUS_COOLING) {
                        if ($this->columnExists('influencers', 'cooldown_until')) {
                            $hours = max(1, (int) ($policy['cooldown_hours'] ?? 24));
                            $inf->cooldown_until = date('Y-m-d H:i:s', time() + $hours * 3600);
                            $inf->save();
                        }
                    }

                    if ($replyText !== '') {
                        $keywords = is_array($policy['unsubscribe_keywords'] ?? null) ? $policy['unsubscribe_keywords'] : [];
                        if (AutoDmService::hitUnsubscribeKeywords($replyText, $keywords)) {
                            if ($this->columnExists('influencers', 'do_not_contact')) {
                                $inf->do_not_contact = 1;
                            }
                            if ($this->columnExists('influencers', 'cooldown_until')) {
                                $inf->cooldown_until = date('Y-m-d H:i:s', strtotime('+3650 day'));
                            }
                            $inf->save();
                            $this->appendAutoEvent([
                                'tenant_id' => $tenantId,
                                'campaign_id' => (int) ($task->campaign_id ?? 0),
                                'task_id' => (int) $task->id,
                                'device_id' => $deviceId,
                                'influencer_id' => $influencerId,
                                'event_type' => AutoDmService::EVENT_REPLY_STOP,
                                'event_status' => 2,
                                'error_code' => 'unsubscribe',
                                'error_message' => 'unsubscribe_keyword_hit',
                                'payload_json' => AutoDmService::encodeJson(['reply_text' => $replyText]),
                            ]);
                        }
                    }
                }
            }

            $campaignId = (int) ($task->campaign_id ?? 0);
            if ($campaignId > 0) {
                $update = ['last_run_at' => $now];
                if ($replyDetected && $prevReplyState === AutoDmService::REPLY_STATE_NONE) {
                    $update['total_replied'] = Db::raw('total_replied + 1');
                }
                if ($nextStatus === AutoDmService::TASK_STATUS_SENT) {
                    $update['total_sent'] = Db::raw('total_sent + 1');
                } elseif ($nextStatus === AutoDmService::TASK_STATUS_FAILED) {
                    $update['total_failed'] = Db::raw('total_failed + 1');
                } elseif (in_array($nextStatus, [AutoDmService::TASK_STATUS_BLOCKED, AutoDmService::TASK_STATUS_COOLING], true)) {
                    $update['total_blocked'] = Db::raw('total_blocked + 1');
                }
                $query = Db::name('auto_dm_campaigns')->where('id', $campaignId);
                if ($this->tableHasTenantId('auto_dm_campaigns')) {
                    $query->where('tenant_id', $tenantId);
                }
                $query->update($update);

                if ($replyDetected && (int) ($campaign['stop_on_reply'] ?? 1) === 1) {
                    $blockQuery = Db::name('auto_dm_tasks')
                        ->where('campaign_id', $campaignId)
                        ->where('influencer_id', (int) ($task->influencer_id ?? 0))
                        ->where('step_no', '>', (int) ($task->step_no ?? 0))
                        ->whereIn('task_status', [
                            AutoDmService::TASK_STATUS_PENDING,
                            AutoDmService::TASK_STATUS_ASSIGNED,
                            AutoDmService::TASK_STATUS_SENDING,
                        ]);
                    if ($this->tableHasTenantId('auto_dm_tasks')) {
                        $blockQuery->where('tenant_id', $tenantId);
                    }
                    $blockQuery->update([
                        'task_status' => AutoDmService::TASK_STATUS_BLOCKED,
                        'completed_at' => $now,
                        'last_error_code' => 'stop_on_reply',
                        'last_error_message' => 'stopped_by_reply_detected',
                        'last_error_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $this->applyAutoDeviceRiskWindow($deviceId, $nextStatus, $now);
        });

        return $this->jsonOk([
            'accepted' => true,
            'task_status' => $nextStatus,
            'event' => $event,
        ]);
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizeAutoTaskTypeFilters($raw): array
    {
        $allowed = [
            AutoDmService::TASK_TYPE_ZALO_AUTO_DM,
            AutoDmService::TASK_TYPE_WA_AUTO_DM,
        ];
        $types = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $key = strtolower(trim((string) $item));
                if (in_array($key, ['zalo', 'zalo_auto', AutoDmService::TASK_TYPE_ZALO_AUTO_DM], true)) {
                    $types[] = AutoDmService::TASK_TYPE_ZALO_AUTO_DM;
                } elseif (in_array($key, ['wa', 'whatsapp', 'wa_auto', AutoDmService::TASK_TYPE_WA_AUTO_DM], true)) {
                    $types[] = AutoDmService::TASK_TYPE_WA_AUTO_DM;
                }
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
            foreach ($parts as $part) {
                $key = strtolower(trim($part));
                if (in_array($key, ['zalo', 'zalo_auto', AutoDmService::TASK_TYPE_ZALO_AUTO_DM], true)) {
                    $types[] = AutoDmService::TASK_TYPE_ZALO_AUTO_DM;
                } elseif (in_array($key, ['wa', 'whatsapp', 'wa_auto', AutoDmService::TASK_TYPE_WA_AUTO_DM], true)) {
                    $types[] = AutoDmService::TASK_TYPE_WA_AUTO_DM;
                }
            }
        }
        $types = array_values(array_unique($types));
        $types = array_values(array_filter($types, static fn($item) => in_array($item, $allowed, true)));
        if (count($types) > 2) {
            $types = array_slice($types, 0, 2);
        }

        return $types;
    }

    /**
     * @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function validateAutoDispatchCandidate(array $task, int $tenantId): array
    {
        $campaign = [
            'id' => (int) ($task['c_id'] ?? 0),
            'campaign_name' => (string) ($task['campaign_name'] ?? ''),
            'template_id' => (int) ($task['c_template_id'] ?? 0),
            'product_id' => (int) ($task['c_product_id'] ?? 0),
            'campaign_status' => (int) ($task['campaign_status'] ?? 0),
            'daily_limit' => max(0, (int) ($task['daily_limit'] ?? 0)),
            'time_window_start' => (string) ($task['time_window_start'] ?? '09:00'),
            'time_window_end' => (string) ($task['time_window_end'] ?? '21:00'),
            'min_interval_sec' => max(10, (int) ($task['min_interval_sec'] ?? 90)),
            'fail_fuse_threshold' => max(1, (int) ($task['fail_fuse_threshold'] ?? 3)),
            'preferred_channel' => (string) ($task['c_preferred_channel'] ?? 'auto'),
        ];
        if ($campaign['id'] <= 0 || $campaign['campaign_status'] !== AutoDmService::CAMPAIGN_STATUS_RUNNING) {
            return [
                'ok' => false,
                'task_status' => AutoDmService::TASK_STATUS_BLOCKED,
                'error_code' => 'campaign_not_running',
                'error_message' => 'campaign_not_running',
                'campaign' => $campaign,
            ];
        }
        if (!AutoDmService::isWithinTimeWindow($campaign['time_window_start'], $campaign['time_window_end'], time())) {
            return [
                'ok' => false,
                'task_status' => AutoDmService::TASK_STATUS_COOLING,
                'error_code' => 'outside_time_window',
                'error_message' => 'outside_time_window',
                'campaign' => $campaign,
            ];
        }

        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        if ($campaign['daily_limit'] > 0) {
            $sentQuery = Db::name('auto_dm_tasks')
                ->where('campaign_id', $campaign['id'])
                ->where('task_status', AutoDmService::TASK_STATUS_SENT)
                ->whereBetween('sent_at', [$todayStart, $todayEnd]);
            if ($this->tableHasTenantId('auto_dm_tasks')) {
                $sentQuery->where('tenant_id', $tenantId);
            }
            $sentToday = (int) $sentQuery->count();
            if ($sentToday >= $campaign['daily_limit']) {
                return [
                    'ok' => false,
                    'task_status' => AutoDmService::TASK_STATUS_COOLING,
                    'error_code' => 'campaign_daily_limit',
                    'error_message' => 'campaign_daily_limit',
                    'campaign' => $campaign,
                ];
            }
        }

        $failQuery = Db::name('auto_dm_events')
            ->where('campaign_id', $campaign['id'])
            ->where('event_type', AutoDmService::EVENT_FAILED)
            ->whereBetween('created_at', [$todayStart, $todayEnd]);
        if ($this->tableHasTenantId('auto_dm_events')) {
            $failQuery->where('tenant_id', $tenantId);
        }
        $failedToday = (int) $failQuery->count();
        if ($failedToday >= $campaign['fail_fuse_threshold']) {
            return [
                'ok' => false,
                'task_status' => AutoDmService::TASK_STATUS_COOLING,
                'error_code' => 'campaign_fused',
                'error_message' => 'campaign_fused',
                'campaign' => $campaign,
            ];
        }

        $infQuery = Db::name('influencers')
            ->where('id', (int) ($task['influencer_id'] ?? 0))
            ->field('id,tiktok_id,nickname,region,status,contact_info,last_contacted_at,last_auto_dm_at,cooldown_until,do_not_contact');
        if ($this->tableHasTenantId('influencers')) {
            $infQuery->where('tenant_id', $tenantId);
        }
        $influencer = $infQuery->find();
        if (!$influencer) {
            return [
                'ok' => false,
                'task_status' => AutoDmService::TASK_STATUS_BLOCKED,
                'error_code' => 'influencer_not_found',
                'error_message' => 'influencer_not_found',
                'campaign' => $campaign,
            ];
        }
        if ((int) ($influencer['status'] ?? 0) === 6 || (int) ($influencer['do_not_contact'] ?? 0) === 1) {
            return [
                'ok' => false,
                'task_status' => AutoDmService::TASK_STATUS_BLOCKED,
                'error_code' => 'do_not_contact',
                'error_message' => 'do_not_contact',
                'campaign' => $campaign,
                'influencer' => $influencer,
            ];
        }
        if (AutoDmService::isCooldownActive((string) ($influencer['cooldown_until'] ?? ''), time())) {
            return [
                'ok' => false,
                'task_status' => AutoDmService::TASK_STATUS_COOLING,
                'error_code' => 'cooldown_until_active',
                'error_message' => 'cooldown_until_active',
                'campaign' => $campaign,
                'influencer' => $influencer,
            ];
        }
        if (AutoDmService::isMinIntervalBlocked((string) ($influencer['last_auto_dm_at'] ?? ''), $campaign['min_interval_sec'], time())) {
            return [
                'ok' => false,
                'task_status' => AutoDmService::TASK_STATUS_COOLING,
                'error_code' => 'min_interval_blocked',
                'error_message' => 'min_interval_blocked',
                'campaign' => $campaign,
                'influencer' => $influencer,
            ];
        }

        $channels = InfluencerService::contactChannelsFromStored((string) ($influencer['contact_info'] ?? ''));
        $route = AutoDmService::routeChannel($channels, (string) ($task['target_channel'] ?? 'auto'));
        if ($route === '') {
            return [
                'ok' => false,
                'task_status' => AutoDmService::TASK_STATUS_BLOCKED,
                'error_code' => 'no_external_channel',
                'error_message' => 'no_external_channel',
                'campaign' => $campaign,
                'influencer' => $influencer,
            ];
        }

        return [
            'ok' => true,
            'campaign' => $campaign,
            'influencer' => $influencer,
            'route_channel' => $route,
        ];
    }

    /**
     * @param array<string, mixed> $task
     */
    private function settleAutoTaskBeforeDispatch(array $task, int $taskStatus, string $errorCode, string $errorMessage, int $tenantId): void
    {
        $taskId = (int) ($task['id'] ?? 0);
        if ($taskId <= 0) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        Db::name('auto_dm_tasks')->where('id', $taskId)->update([
            'task_status' => $taskStatus,
            'completed_at' => $now,
            'last_error_code' => $errorCode !== '' ? mb_substr($errorCode, 0, 64) : null,
            'last_error_message' => $errorMessage !== '' ? mb_substr($errorMessage, 0, 255) : null,
            'last_error_at' => $now,
            'updated_at' => $now,
        ]);

        $this->appendAutoEvent([
            'tenant_id' => $tenantId,
            'campaign_id' => (int) ($task['campaign_id'] ?? 0),
            'task_id' => $taskId,
            'device_id' => (int) ($task['device_id'] ?? 0),
            'influencer_id' => (int) ($task['influencer_id'] ?? 0),
            'event_type' => $taskStatus === AutoDmService::TASK_STATUS_COOLING ? AutoDmService::EVENT_COOLING : AutoDmService::EVENT_BLOCKED,
            'event_status' => 2,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'payload_json' => AutoDmService::encodeJson(['task_status' => $taskStatus]),
        ]);

        $campaignId = (int) ($task['campaign_id'] ?? 0);
        if ($campaignId > 0) {
            $query = Db::name('auto_dm_campaigns')->where('id', $campaignId);
            if ($this->tableHasTenantId('auto_dm_campaigns')) {
                $query->where('tenant_id', $tenantId);
            }
            $query->update([
                'total_blocked' => Db::raw('total_blocked + 1'),
                'last_run_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function appendAutoEvent(array $payload): void
    {
        $insert = $this->withTenantPayload([
            'campaign_id' => (int) ($payload['campaign_id'] ?? 0),
            'task_id' => isset($payload['task_id']) && (int) $payload['task_id'] > 0 ? (int) $payload['task_id'] : null,
            'device_id' => isset($payload['device_id']) && (int) $payload['device_id'] > 0 ? (int) $payload['device_id'] : null,
            'influencer_id' => isset($payload['influencer_id']) && (int) $payload['influencer_id'] > 0 ? (int) $payload['influencer_id'] : null,
            'event_type' => mb_substr((string) ($payload['event_type'] ?? 'event'), 0, 32),
            'event_status' => (int) ($payload['event_status'] ?? 0),
            'error_code' => isset($payload['error_code']) && $payload['error_code'] !== null ? mb_substr((string) $payload['error_code'], 0, 64) : null,
            'error_message' => isset($payload['error_message']) && $payload['error_message'] !== null ? mb_substr((string) $payload['error_message'], 0, 255) : null,
            'duration_ms' => max(0, (int) ($payload['duration_ms'] ?? 0)),
            'screenshot_path' => isset($payload['screenshot_path']) && $payload['screenshot_path'] !== null ? mb_substr((string) $payload['screenshot_path'], 0, 255) : null,
            'payload_json' => isset($payload['payload_json']) && $payload['payload_json'] !== null ? (string) $payload['payload_json'] : null,
        ], 'auto_dm_events');
        AutoDmEventModel::create($insert);
    }

    private function normalizeAutoReportEvent(string $event): string
    {
        $key = strtolower(trim($event));
        if ($key === '') {
            return AutoDmService::EVENT_FAILED;
        }
        if (in_array($key, [AutoDmService::EVENT_SENDING, 'sending', 'prepared', 'auto_sending'], true)) {
            return AutoDmService::EVENT_SENDING;
        }
        if (in_array($key, [AutoDmService::EVENT_SENT, 'sent', 'done', 'success', 'auto_sent'], true)) {
            return AutoDmService::EVENT_SENT;
        }
        if (in_array($key, [AutoDmService::EVENT_BLOCKED, 'blocked', 'block'], true)) {
            return AutoDmService::EVENT_BLOCKED;
        }
        if (in_array($key, [AutoDmService::EVENT_COOLING, 'cooling'], true)) {
            return AutoDmService::EVENT_COOLING;
        }
        if (in_array($key, [AutoDmService::EVENT_REPLY_STOP, 'reply_stop', 'unsubscribe'], true)) {
            return AutoDmService::EVENT_REPLY_STOP;
        }

        return AutoDmService::EVENT_FAILED;
    }

    private function mapAutoReportEventToStatus(string $event): int
    {
        $evt = $this->normalizeAutoReportEvent($event);
        if ($evt === AutoDmService::EVENT_SENDING) {
            return AutoDmService::TASK_STATUS_SENDING;
        }
        if ($evt === AutoDmService::EVENT_SENT) {
            return AutoDmService::TASK_STATUS_SENT;
        }
        if ($evt === AutoDmService::EVENT_BLOCKED || $evt === AutoDmService::EVENT_REPLY_STOP) {
            return AutoDmService::TASK_STATUS_BLOCKED;
        }
        if ($evt === AutoDmService::EVENT_COOLING) {
            return AutoDmService::TASK_STATUS_COOLING;
        }

        return AutoDmService::TASK_STATUS_FAILED;
    }

    private function applyAutoDeviceRiskWindow(int $deviceId, int $taskStatus, string $now): void
    {
        if ($taskStatus === AutoDmService::TASK_STATUS_SENT) {
            $this->applyDeviceRiskWindow($deviceId, MobileOutreachService::STATUS_DONE, $now);
            return;
        }
        if ($taskStatus === AutoDmService::TASK_STATUS_FAILED) {
            $this->applyDeviceRiskWindow($deviceId, MobileOutreachService::STATUS_FAILED, $now);
            return;
        }
        if ($taskStatus === AutoDmService::TASK_STATUS_BLOCKED) {
            $this->applyDeviceRiskWindow($deviceId, MobileOutreachService::STATUS_SKIPPED, $now);
            return;
        }
        if ($taskStatus === AutoDmService::TASK_STATUS_COOLING) {
            $this->applyDeviceRiskWindow($deviceId, MobileOutreachService::STATUS_CANCELED, $now);
        }
    }

    private function appendAutoOutreachLog(int $tenantId, AutoDmTaskModel $task, string $renderedText): void
    {
        $payload = AutoDmService::decodeJsonObject((string) ($task->payload_json ?? ''));
        $templateId = (int) ($payload['template_id'] ?? 0);
        $productId = (int) ($payload['product_id'] ?? 0);

        $templateName = '';
        $templateLang = 'en';
        if ($templateId > 0) {
            $tplQuery = Db::name('message_templates')
                ->where('id', $templateId)
                ->field('id,name,lang');
            if ($this->tableHasTenantId('message_templates')) {
                $tplQuery->where('tenant_id', $tenantId);
            }
            $tpl = $tplQuery->find();
            if ($tpl) {
                $templateName = (string) ($tpl['name'] ?? '');
                $templateLang = (string) ($tpl['lang'] ?? 'en');
            }
        }

        $productName = '';
        if ($productId > 0) {
            $prodQuery = Db::name('products')
                ->where('id', $productId)
                ->field('id,name');
            if ($this->tableHasTenantId('products')) {
                $prodQuery->where('tenant_id', $tenantId);
            }
            $prod = $prodQuery->find();
            if ($prod) {
                $productName = (string) ($prod['name'] ?? '');
            }
        }

        $logPayload = $this->withTenantPayload([
            'influencer_id' => (int) ($task->influencer_id ?? 0),
            'template_id' => $templateId,
            'template_name' => $templateName,
            'template_lang' => $templateLang,
            'product_id' => $productId > 0 ? $productId : null,
            'product_name' => $productName !== '' ? $productName : null,
            'channel' => 'auto_dm',
            'rendered_body' => $renderedText !== '' ? $renderedText : null,
        ], 'outreach_logs');
        if ($this->columnExists('outreach_logs', 'action_type')) {
            $logPayload['action_type'] = 'auto_dm_sent';
        }
        OutreachLogModel::create($logPayload);
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

    /**
     * @return array{0:?array<string, mixed>,1:array<string, mixed>,2:mixed}
     */
    private function resolveDeviceFromRequest(): array
    {
        $payload = $this->parseJsonOrPost();
        $token = trim((string) ($payload['token'] ?? ''));
        if ($token === '') {
            $token = trim((string) $this->request->header('x-mobile-agent-token', ''));
        }
        if ($token === '') {
            $auth = trim((string) $this->request->header('authorization', ''));
            if (stripos($auth, 'bearer ') === 0) {
                $token = trim(substr($auth, 7));
            }
        }
        if ($token === '') {
            return [null, $payload, $this->jsonErr('token_required', 401, null, 'common.forbidden')];
        }
        $deviceCode = trim((string) ($payload['device_code'] ?? ''));
        if ($deviceCode === '') {
            $deviceCode = trim((string) $this->request->header('x-device-code', ''));
        }

        $query = Db::name('mobile_devices')->where('agent_token', $token);
        if ($deviceCode !== '') {
            $query->where('device_code', $deviceCode);
        }
        $device = $query->order('id', 'desc')->find();

        return [$device, $payload, null];
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
     * @return list<string>
     */
    private function normalizeTaskTypeFilters($raw): array
    {
        $types = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $types[] = MobileOutreachService::normalizeTaskType((string) $item);
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
            foreach ($parts as $part) {
                $types[] = MobileOutreachService::normalizeTaskType($part);
            }
        }
        $types = array_values(array_unique(array_filter($types)));
        if (count($types) > 8) {
            $types = array_slice($types, 0, 8);
        }

        return $types;
    }

    private function touchDeviceHeartbeat(int $deviceId): void
    {
        Db::name('mobile_devices')->where('id', $deviceId)->update([
            'is_online' => 1,
            'heartbeat_at' => date('Y-m-d H:i:s'),
            'last_seen_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $device
     */
    private function resetDailyUsageIfNeeded(array $device): void
    {
        $lastSeenAt = trim((string) ($device['last_seen_at'] ?? ''));
        if ($lastSeenAt === '') {
            return;
        }
        $lastDay = date('Y-m-d', strtotime($lastSeenAt) ?: time());
        if ($lastDay === date('Y-m-d')) {
            return;
        }
        Db::name('mobile_devices')->where('id', (int) ($device['id'] ?? 0))->update([
            'daily_used' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function appendTaskLog(array $payload): void
    {
        $insert = $this->withTenantPayload([
            'task_id' => (int) ($payload['task_id'] ?? 0),
            'device_id' => (int) ($payload['device_id'] ?? 0) > 0 ? (int) $payload['device_id'] : null,
            'influencer_id' => (int) ($payload['influencer_id'] ?? 0) > 0 ? (int) $payload['influencer_id'] : null,
            'event_type' => mb_substr((string) ($payload['event_type'] ?? 'report'), 0, 32),
            'event_status' => (int) ($payload['event_status'] ?? 0),
            'error_code' => isset($payload['error_code']) && $payload['error_code'] !== null ? mb_substr((string) $payload['error_code'], 0, 64) : null,
            'error_message' => isset($payload['error_message']) && $payload['error_message'] !== null ? mb_substr((string) $payload['error_message'], 0, 255) : null,
            'duration_ms' => max(0, (int) ($payload['duration_ms'] ?? 0)),
            'screenshot_path' => isset($payload['screenshot_path']) && $payload['screenshot_path'] !== null ? mb_substr((string) $payload['screenshot_path'], 0, 255) : null,
            'payload_json' => isset($payload['payload_json']) && $payload['payload_json'] !== null ? (string) $payload['payload_json'] : null,
        ], 'mobile_action_logs');
        MobileActionLogModel::create($insert);
    }

    private function applyDeviceRiskWindow(int $deviceId, int $taskStatus, string $now): void
    {
        if (!in_array($taskStatus, [
            MobileOutreachService::STATUS_DONE,
            MobileOutreachService::STATUS_FAILED,
            MobileOutreachService::STATUS_SKIPPED,
            MobileOutreachService::STATUS_CANCELED,
        ], true)) {
            return;
        }

        $device = Db::name('mobile_devices')->where('id', $deviceId)->find();
        if (!$device) {
            return;
        }
        $dailyUsed = max(0, (int) ($device['daily_used'] ?? 0));
        $failStreak = max(0, (int) ($device['fail_streak'] ?? 0));
        if ($taskStatus === MobileOutreachService::STATUS_FAILED) {
            ++$failStreak;
            $cooldownUntil = null;
            if ($failStreak >= 3) {
                $cooldownUntil = date('Y-m-d H:i:s', time() + 15 * 60);
            }
            Db::name('mobile_devices')->where('id', $deviceId)->update([
                'daily_used' => $dailyUsed + 1,
                'fail_streak' => $failStreak,
                'cooldown_until' => $cooldownUntil,
                'updated_at' => $now,
            ]);
            return;
        }

        Db::name('mobile_devices')->where('id', $deviceId)->update([
            'daily_used' => $dailyUsed + 1,
            'fail_streak' => 0,
            'cooldown_until' => null,
            'updated_at' => $now,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $json): array
    {
        $raw = trim($json);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
}
