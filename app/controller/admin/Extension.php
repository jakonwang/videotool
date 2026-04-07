<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\AdminAuthService;
use app\service\ModuleManagerService;
use think\facade\View;

/**
 * 模块管理
 */
class Extension extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    private function jsonErr(string $msg, int $code = 1)
    {
        return json(['code' => $code, 'msg' => $msg]);
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

    public function index()
    {
        return View::fetch('admin/extension/index', []);
    }

    public function listJson()
    {
        $items = ModuleManagerService::modulesForCurrentRole(true);
        return $this->jsonOk([
            'items' => $items,
            'role' => AdminAuthService::role(),
            'can_manage' => in_array(AdminAuthService::role(), ['super_admin', 'operator'], true) ? 1 : 0,
            'can_edit_permissions' => AdminAuthService::role() === 'super_admin' ? 1 : 0,
        ]);
    }

    public function logsJson()
    {
        $limit = (int) $this->request->param('limit', 30);
        return $this->jsonOk(['items' => ModuleManagerService::logs($limit)]);
    }

    public function permissionMatrix()
    {
        return $this->jsonOk(['items' => ModuleManagerService::permissionMatrix()]);
    }

    public function savePermission()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $role = trim((string) ($payload['role'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $canView = (int) ($payload['can_view'] ?? 0);
        $res = ModuleManagerService::updateRolePermission($role, $name, $canView);
        if (!($res['ok'] ?? false)) {
            return $this->jsonErr((string) ($res['message'] ?? 'save permission failed'));
        }
        return $this->jsonOk([], '已更新');
    }

    public function install()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $name = trim((string) ($payload['name'] ?? ''));
        $res = ModuleManagerService::install($name);
        if (!($res['ok'] ?? false)) {
            return $this->jsonErr((string) ($res['message'] ?? 'install failed'));
        }
        return $this->jsonOk([], '已安装');
    }

    public function uninstall()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $name = trim((string) ($payload['name'] ?? ''));
        $purgeData = (int) ($payload['purge_data'] ?? 0) === 1;
        $res = ModuleManagerService::uninstall($name, $purgeData);
        if (!($res['ok'] ?? false)) {
            return $this->jsonErr((string) ($res['message'] ?? 'uninstall failed'));
        }
        return $this->jsonOk([], '已卸载');
    }

    public function toggle()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $name = trim((string) ($payload['name'] ?? ''));
        $isEnabled = (int) ($payload['is_enabled'] ?? 0);
        $res = ModuleManagerService::toggle($name, $isEnabled);
        if (!($res['ok'] ?? false)) {
            return $this->jsonErr((string) ($res['message'] ?? 'toggle failed'));
        }
        return $this->jsonOk([], '已更新');
    }
}

