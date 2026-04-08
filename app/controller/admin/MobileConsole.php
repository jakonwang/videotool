<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\AdminAuthService;
use app\service\ModuleManagerService;

class MobileConsole extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return $this->apiJsonOk($data, $msg);
    }

    public function bootstrap()
    {
        $lang = strtolower(trim((string) $this->request->param('lang', '')));
        if (!in_array($lang, ['zh', 'en', 'vi'], true)) {
            $lang = 'zh';
        }

        $role = AdminAuthService::role();
        $portal = $role === 'viewer' ? 'influencer' : 'merchant';

        return $this->jsonOk([
            'lang' => $lang,
            'user' => [
                'id' => AdminAuthService::userId(),
                'username' => AdminAuthService::username(),
                'role' => $role,
                'tenant_id' => AdminAuthService::tenantId(),
            ],
            'portal' => $portal,
            'menus' => ModuleManagerService::getEnabledMenus(),
            'enabled_modules' => ModuleManagerService::modulesForCurrentRole(true),
        ]);
    }
}

