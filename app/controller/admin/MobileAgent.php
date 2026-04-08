<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Influencer as InfluencerModel;
use app\model\MobileActionLog as MobileActionLogModel;
use app\model\MobileActionTask as MobileActionTaskModel;
use app\model\OutreachLog as OutreachLogModel;
use app\service\InfluencerStatusFlowService;
use app\service\MobileOutreachService;
use think\facade\Db;

class MobileAgent extends BaseController
{
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
