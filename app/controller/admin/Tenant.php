<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\AdminAuditService;
use app\service\AdminAuthService;
use app\service\TenantModuleService;
use app\service\TenantSaasService;
use think\facade\View;

class Tenant extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return $this->apiJsonOk($data, $msg);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        return $this->apiJsonErr($msg, $code, $data, $errorKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonOrPost(): array
    {
        $raw = (string) $this->request->getContent();
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }

        return $this->request->post();
    }

    private function ensureSuperAdmin()
    {
        if (AdminAuthService::role() !== 'super_admin') {
            return $this->jsonErr('forbidden', 403, null, 'common.forbidden');
        }

        return null;
    }

    private function currentModuleAccessMode(): string
    {
        $state = TenantModuleService::moduleState('platform_ops', AdminAuthService::tenantId());
        return (string) ($state['access_mode'] ?? 'enabled');
    }

    public function index()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }

        return View::fetch('admin/tenant/index');
    }

    public function list()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }

        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = $this->request->param('status', null);
        $items = TenantSaasService::listTenants([
            'keyword' => $keyword,
            'status' => $status,
        ]);

        return $this->jsonOk([
            'items' => $items,
            'tenant_id' => AdminAuthService::tenantId(),
            'module_access_mode' => $this->currentModuleAccessMode(),
        ]);
    }

    public function save()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }

        $payload = $this->parseJsonOrPost();
        $res = TenantSaasService::saveTenant($payload);
        if (!($res['ok'] ?? false)) {
            return $this->jsonErr((string) ($res['message'] ?? 'save_failed'));
        }

        AdminAuditService::log(
            $this->request,
            'tenant.save',
            'tenants',
            (int) ($res['tenant_id'] ?? 0),
            ['payload' => $payload]
        );

        return $this->jsonOk(['tenant_id' => (int) ($res['tenant_id'] ?? 0)], 'saved');
    }

    public function status()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }

        $payload = $this->parseJsonOrPost();
        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        $status = (int) ($payload['status'] ?? 0);
        $res = TenantSaasService::updateTenantStatus($tenantId, $status);
        if (!($res['ok'] ?? false)) {
            return $this->jsonErr((string) ($res['message'] ?? 'save_failed'));
        }

        AdminAuditService::log(
            $this->request,
            'tenant.status',
            'tenants',
            $tenantId,
            ['status' => $status === 1 ? 1 : 0]
        );

        return $this->jsonOk([], 'updated');
    }

    public function adminList()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }
        $tenantId = (int) $this->request->param('tenant_id', 0);
        $keyword = trim((string) $this->request->param('keyword', ''));
        $items = TenantSaasService::listTenantAdmins([
            'tenant_id' => $tenantId,
            'keyword' => $keyword,
        ]);

        return $this->jsonOk([
            'items' => $items,
            'module_access_mode' => $this->currentModuleAccessMode(),
        ]);
    }

    public function adminSave()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }

        $payload = $this->parseJsonOrPost();
        $res = TenantSaasService::saveTenantAdmin($payload);
        if (!($res['ok'] ?? false)) {
            return $this->jsonErr((string) ($res['message'] ?? 'save_failed'));
        }

        AdminAuditService::log(
            $this->request,
            'tenant.admin.save',
            'admin_users',
            (int) ($res['id'] ?? 0),
            ['tenant_id' => (int) ($payload['tenant_id'] ?? 0), 'role' => (string) ($payload['role'] ?? '')]
        );

        return $this->jsonOk(['id' => (int) ($res['id'] ?? 0)], 'saved');
    }

    public function packageList()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }
        $items = TenantSaasService::listPackages();

        return $this->jsonOk([
            'items' => $items,
            'module_catalog' => TenantSaasService::moduleCatalog(),
            'module_access_mode' => $this->currentModuleAccessMode(),
        ]);
    }

    public function packageSave()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }

        $payload = $this->parseJsonOrPost();
        $res = TenantSaasService::savePackage($payload);
        if (!($res['ok'] ?? false)) {
            return $this->jsonErr((string) ($res['message'] ?? 'save_failed'));
        }

        AdminAuditService::log(
            $this->request,
            'tenant.package.save',
            'tenant_packages',
            (int) ($res['package_id'] ?? 0),
            ['payload' => $payload]
        );

        return $this->jsonOk(['package_id' => (int) ($res['package_id'] ?? 0)], 'saved');
    }

    public function subscriptionSave()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }

        $payload = $this->parseJsonOrPost();
        $res = TenantSaasService::saveSubscription($payload);
        if (!($res['ok'] ?? false)) {
            return $this->jsonErr((string) ($res['message'] ?? 'save_failed'));
        }

        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        AdminAuditService::log(
            $this->request,
            'tenant.subscription.save',
            'tenant_subscriptions',
            $tenantId,
            ['payload' => $payload]
        );

        return $this->jsonOk([
            'tenant_id' => $tenantId,
            'module_access_mode' => $this->currentModuleAccessMode(),
        ], 'saved');
    }

    public function subscriptionModules()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }
        $tenantId = (int) $this->request->param('tenant_id', 0);
        if ($tenantId <= 0) {
            return $this->jsonErr('invalid_tenant_id', 1, null, 'common.invalidId');
        }
        $items = TenantSaasService::tenantModules($tenantId);

        return $this->jsonOk([
            'tenant_id' => $tenantId,
            'items' => $items,
            'module_access_mode' => $this->currentModuleAccessMode(),
        ]);
    }

    public function auditList()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }
        $tenantId = (int) $this->request->param('tenant_id', 0);
        $action = trim((string) $this->request->param('action', ''));
        $limit = (int) $this->request->param('limit', 100);
        $items = TenantSaasService::listTenantAudit([
            'tenant_id' => $tenantId,
            'action' => $action,
            'limit' => $limit,
        ]);

        return $this->jsonOk([
            'items' => $items,
            'module_access_mode' => $this->currentModuleAccessMode(),
        ]);
    }

    public function switchTenant()
    {
        if (($guard = $this->ensureSuperAdmin()) !== null) {
            return $guard;
        }
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }

        $payload = $this->parseJsonOrPost();
        $targetTenantId = (int) ($payload['tenant_id'] ?? 0);
        $fromTenantId = AdminAuthService::tenantId();
        $res = TenantSaasService::switchTenant($targetTenantId);
        if (!($res['ok'] ?? false)) {
            return $this->jsonErr((string) ($res['message'] ?? 'switch_failed'));
        }

        AdminAuditService::log(
            $this->request,
            'tenant.switch',
            'tenants',
            $targetTenantId,
            ['from_tenant_id' => $fromTenantId, 'to_tenant_id' => $targetTenantId]
        );

        return $this->jsonOk([
            'tenant_id' => AdminAuthService::tenantId(),
            'module_access_mode' => $this->currentModuleAccessMode(),
        ], 'updated');
    }
}
