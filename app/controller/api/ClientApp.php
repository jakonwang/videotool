<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\model\AppLicense as AppLicenseModel;
use app\model\AppVersion as AppVersionModel;

/**
 * 桌面端客户端开放接口（无需登录）
 */
class ClientApp extends BaseController
{
    private function jsonOut(array $payload, int $httpCode = 200)
    {
        return json($payload, $httpCode, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
    }

    /**
     * 校验授权码并首次绑定机器
     * POST：license_key, machine_id
     */
    public function verifyLicense()
    {
        $licenseKey = trim((string) $this->request->param('license_key', ''));
        $machineId = trim((string) $this->request->param('machine_id', ''));

        if ($licenseKey === '' || $machineId === '') {
            return $this->jsonOut(['code' => 1, 'msg' => '缺少 license_key 或 machine_id', 'data' => null]);
        }

        $row = AppLicenseModel::where('license_key', $licenseKey)->find();
        if (!$row) {
            return $this->jsonOut(['code' => 1, 'msg' => '授权码无效', 'data' => null]);
        }
        if ((int) $row->status !== 1) {
            return $this->jsonOut(['code' => 1, 'msg' => '授权码已禁用', 'data' => null]);
        }

        $exp = $row->expire_time;
        if ($exp) {
            $ts = is_string($exp) ? strtotime($exp) : strtotime((string) $exp);
            if ($ts !== false && $ts < time()) {
                return $this->jsonOut(['code' => 1, 'msg' => '授权已过期', 'data' => null]);
            }
        }

        $bound = $row->machine_id;
        if ($bound !== null && $bound !== '') {
            if ((string) $bound !== $machineId) {
                return $this->jsonOut(['code' => 1, 'msg' => '授权码已绑定其他设备', 'data' => null]);
            }
        } else {
            $row->machine_id = $machineId;
            $row->save();
        }

        $expireStr = '';
        if ($exp) {
            $expireStr = is_string($exp) ? $exp : (string) $exp;
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'valid' => true,
                'expire_time' => $expireStr,
            ],
        ]);
    }

    /**
     * 检查更新：返回大于当前版本的最新已发布版本
     * GET/POST：current_version
     */
    public function checkUpdate()
    {
        $current = trim((string) $this->request->param('current_version', ''));
        if ($current === '') {
            return $this->jsonOut(['code' => 1, 'msg' => '缺少 current_version', 'data' => null]);
        }

        $rows = AppVersionModel::where('status', 1)->select();
        $best = null;
        foreach ($rows as $r) {
            $v = trim((string) ($r->version ?? ''));
            if ($v === '' || !version_compare($v, $current, '>')) {
                continue;
            }
            if ($best === null || version_compare($v, (string) $best->version, '>')) {
                $best = $r;
            }
        }

        if ($best === null) {
            return $this->jsonOut([
                'code' => 0,
                'msg' => 'ok',
                'data' => [
                    'has_update' => false,
                    'version' => '',
                    'release_notes' => '',
                    'download_url' => '',
                    'is_mandatory' => 0,
                ],
            ]);
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'has_update' => true,
                'version' => (string) $best->version,
                'release_notes' => (string) ($best->release_notes ?? ''),
                'download_url' => (string) ($best->download_url ?? ''),
                'is_mandatory' => (int) ($best->is_mandatory ?? 0),
            ],
        ]);
    }
}
