<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\MobileDevice as MobileDeviceModel;

class MobileDevice extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return $this->apiJsonOk($data, $msg);
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
}
