<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\AdminAuthService;
use app\service\OpsMaintenanceService;
use think\facade\View;

class OpsCenter extends BaseController
{
    public function index()
    {
        return View::fetch('admin/ops_center/index', []);
    }

    public function status()
    {
        if (!$this->canManageOps()) {
            return $this->apiJsonErr('forbidden', 403, null, 'common.forbidden');
        }

        return $this->apiJsonOk(OpsMaintenanceService::status());
    }

    public function runMigrations()
    {
        if (!$this->canManageOps()) {
            return $this->apiJsonErr('forbidden', 403, null, 'common.forbidden');
        }
        if (!$this->request->isPost()) {
            return $this->apiJsonErr('only_post', 1, null, 'common.onlyPost');
        }

        $res = OpsMaintenanceService::runMigrations();
        if (!(bool) ($res['ok'] ?? false)) {
            return $this->apiJsonErr((string) ($res['message'] ?? 'migration_failed'), 1, $res, 'common.operationFailed');
        }
        return $this->apiJsonOk($res);
    }

    public function gitPull()
    {
        if (!$this->canManageOps()) {
            return $this->apiJsonErr('forbidden', 403, null, 'common.forbidden');
        }
        if (!$this->request->isPost()) {
            return $this->apiJsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $allowDirty = (int) ($payload['allow_dirty'] ?? 0) === 1;

        $res = OpsMaintenanceService::gitPull($allowDirty);
        if (!(bool) ($res['ok'] ?? false)) {
            $msg = (string) ($res['message'] ?? 'git_pull_failed');
            $errKey = $msg === 'git_worktree_dirty' ? 'page.opsCenter.gitDirtyError' : 'common.operationFailed';
            return $this->apiJsonErr($msg, 1, $res, $errKey);
        }
        return $this->apiJsonOk($res);
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

    private function canManageOps(): bool
    {
        $role = strtolower(trim((string) AdminAuthService::role()));
        return $role === 'super_admin';
    }
}
