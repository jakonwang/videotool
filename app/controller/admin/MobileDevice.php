<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\MobileDevice as MobileDeviceModel;
use think\facade\View;

class MobileDevice extends BaseController
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
        return View::fetch('admin/mobile_device/index');
    }

    public function listJson()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = trim((string) $this->request->param('status', ''));

        $query = MobileDeviceModel::order('id', 'desc');
        $query = $this->scopeTenant($query, 'mobile_devices');
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword): void {
                $sub->whereLike('device_code', '%' . $keyword . '%')
                    ->whereOr('device_name', 'like', '%' . $keyword . '%')
                    ->whereOr('device_serial', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== '') {
            $query->where('status', (int) $status);
        }

        $rows = $query->select();
        $items = [];
        foreach ($rows as $row) {
            $heartbeatAt = (string) ($row->heartbeat_at ?? '');
            $isOnline = (int) ($row->is_online ?? 0) === 1;
            if ($heartbeatAt !== '') {
                $ts = strtotime($heartbeatAt);
                if ($ts !== false && $ts < time() - 120) {
                    $isOnline = false;
                }
            }
            $cooldownUntil = (string) ($row->cooldown_until ?? '');
            $isCooling = false;
            if ($cooldownUntil !== '') {
                $coolTs = strtotime($cooldownUntil);
                if ($coolTs !== false && $coolTs > time()) {
                    $isCooling = true;
                }
            }

            $dailyQuota = max(0, (int) ($row->daily_quota ?? 0));
            $dailyUsed = max(0, (int) ($row->daily_used ?? 0));
            $quotaLeft = max(0, $dailyQuota - $dailyUsed);
            $items[] = [
                'id' => (int) $row->id,
                'device_code' => (string) ($row->device_code ?? ''),
                'device_name' => (string) ($row->device_name ?? ''),
                'device_serial' => (string) ($row->device_serial ?? ''),
                'platform' => (string) ($row->platform ?? 'android'),
                'agent_token' => (string) ($row->agent_token ?? ''),
                'status' => (int) ($row->status ?? 1),
                'is_online' => $isOnline ? 1 : 0,
                'heartbeat_at' => $heartbeatAt,
                'last_seen_at' => (string) ($row->last_seen_at ?? ''),
                'daily_quota' => $dailyQuota,
                'daily_used' => $dailyUsed,
                'quota_left' => $quotaLeft,
                'fail_streak' => (int) ($row->fail_streak ?? 0),
                'cooldown_until' => $cooldownUntil,
                'is_cooling' => $isCooling ? 1 : 0,
                'capability_json' => (string) ($row->capability_json ?? ''),
                'remark' => (string) ($row->remark ?? ''),
                'created_at' => (string) ($row->created_at ?? ''),
                'updated_at' => (string) ($row->updated_at ?? ''),
            ];
        }

        return $this->jsonOk(['items' => $items]);
    }

    public function save()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = max(0, (int) ($payload['id'] ?? 0));
        $deviceCode = trim((string) ($payload['device_code'] ?? ''));
        if ($deviceCode === '') {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $deviceCode = mb_substr($deviceCode, 0, 64);

        $platform = strtolower(trim((string) ($payload['platform'] ?? 'android')));
        if (!in_array($platform, ['android', 'ios'], true)) {
            $platform = 'android';
        }

        $dailyQuota = max(0, min(200000, (int) ($payload['daily_quota'] ?? 120)));
        $status = ((int) ($payload['status'] ?? 1) === 1) ? 1 : 0;
        $agentToken = trim((string) ($payload['agent_token'] ?? ''));
        if ($agentToken !== '') {
            $agentToken = mb_substr($agentToken, 0, 128);
        }

        $data = [
            'device_code' => $deviceCode,
            'device_name' => mb_substr(trim((string) ($payload['device_name'] ?? '')), 0, 128),
            'device_serial' => mb_substr(trim((string) ($payload['device_serial'] ?? '')), 0, 128),
            'platform' => $platform,
            'status' => $status,
            'daily_quota' => $dailyQuota,
            'remark' => mb_substr(trim((string) ($payload['remark'] ?? '')), 0, 255),
            'capability_json' => $this->normalizeCapabilityJson((string) ($payload['capability_json'] ?? '')),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            $query = MobileDeviceModel::where('id', $id);
            $query = $this->scopeTenant($query, 'mobile_devices');
            $device = $query->find();
            if (!$device) {
                return $this->jsonErr('not_found', 404, null, 'common.notFound');
            }
            if ($this->existsDuplicatedDeviceCode($deviceCode, $id)) {
                return $this->jsonErr('device_code_exists', 1, null, 'common.invalidParams');
            }
            if ($agentToken !== '') {
                $data['agent_token'] = $agentToken;
            }
            if ($status === 0) {
                $data['is_online'] = 0;
            }
            $device->save($data);

            return $this->jsonOk([
                'id' => (int) $device->id,
                'agent_token' => (string) ($device->agent_token ?? ''),
            ], 'updated');
        }

        if ($this->existsDuplicatedDeviceCode($deviceCode, 0)) {
            return $this->jsonErr('device_code_exists', 1, null, 'common.invalidParams');
        }
        $data['agent_token'] = $agentToken !== '' ? $agentToken : $this->generateAgentToken();
        $insert = $this->withTenantPayload($data, 'mobile_devices');
        $created = MobileDeviceModel::create($insert);

        return $this->jsonOk([
            'id' => (int) ($created->id ?? 0),
            'agent_token' => (string) ($created->agent_token ?? ''),
        ], 'saved');
    }

    public function delete()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = max(0, (int) ($payload['id'] ?? 0));
        if ($id <= 0) {
            return $this->jsonErr('invalid_id', 1, null, 'common.invalidId');
        }

        $query = MobileDeviceModel::where('id', $id);
        $query = $this->scopeTenant($query, 'mobile_devices');
        $device = $query->find();
        if (!$device) {
            return $this->jsonErr('not_found', 404, null, 'common.notFound');
        }

        if ($this->hasRunningTask($id)) {
            return $this->jsonErr('device_has_running_task', 1, null, 'common.operationFailed');
        }

        $device->delete();
        return $this->jsonOk([], 'deleted');
    }

    public function regenerateToken()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = max(0, (int) ($payload['id'] ?? 0));
        if ($id <= 0) {
            return $this->jsonErr('invalid_id', 1, null, 'common.invalidId');
        }
        $query = MobileDeviceModel::where('id', $id);
        $query = $this->scopeTenant($query, 'mobile_devices');
        $device = $query->find();
        if (!$device) {
            return $this->jsonErr('not_found', 404, null, 'common.notFound');
        }

        $token = $this->generateAgentToken();
        $device->save([
            'agent_token' => $token,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->jsonOk([
            'id' => (int) $device->id,
            'agent_token' => $token,
        ], 'updated');
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

        $post = $this->request->post();
        return is_array($post) ? $post : [];
    }

    private function existsDuplicatedDeviceCode(string $deviceCode, int $excludeId): bool
    {
        $query = MobileDeviceModel::where('device_code', $deviceCode);
        $query = $this->scopeTenant($query, 'mobile_devices');
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        return $query->count() > 0;
    }

    private function hasRunningTask(int $deviceId): bool
    {
        try {
            $query = \think\facade\Db::name('mobile_action_tasks')
                ->where('device_id', $deviceId)
                ->whereIn('task_status', [1, 2]);
            if ($this->tableHasTenantId('mobile_action_tasks')) {
                $query->where('tenant_id', $this->currentTenantId());
            }

            return (int) $query->count() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function normalizeCapabilityJson(string $raw): string
    {
        $txt = trim($raw);
        if ($txt === '') {
            return '';
        }
        $decoded = json_decode($txt, true);
        if (!is_array($decoded)) {
            return '';
        }
        $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '';
    }

    private function generateAgentToken(): string
    {
        try {
            return 'agt_' . bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            return 'agt_' . md5(uniqid('mobile', true) . microtime(true));
        }
    }
}
