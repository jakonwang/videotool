<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\AdminAuthService;
use app\service\ProfitPluginTokenService;
use app\service\TenantModuleService;
use Closure;
use think\Request;
use think\Response;
use think\facade\Db;
use think\facade\View;

class AdminAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!defined('ENTRY_FILE') || ENTRY_FILE !== 'admin') {
            return $next($request);
        }

        $path = '/' . ltrim((string) $request->pathinfo(), '/');
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }

        // Whitelist login/logout routes.
        if ($path === '/auth/login' || $path === '/auth/logout') {
            return $next($request);
        }

        if (
            $path === '/mobile_agent/pull'
            || $path === '/mobile_agent/report'
            || $path === '/mobile_agent/pull_auto'
            || $path === '/mobile_agent/report_auto'
        ) {
            if ($this->passesMobileAgentAuth($request)) {
                return $next($request);
            }
            return json([
                'code' => 401,
                'msg' => 'token_required',
                'error_key' => 'common.forbidden',
                'data' => null,
            ]);
        }

        if ($this->isProfitPluginPublicPath($path) && !AdminAuthService::isLoggedIn()) {
            if ($this->passesProfitPluginAuth($request)) {
                return $next($request);
            }
            return json([
                'code' => 401,
                'msg' => 'token_required',
                'error_key' => 'common.forbidden',
                'data' => null,
            ]);
        }

        if (AdminAuthService::isLoggedIn()) {
            $moduleName = TenantModuleService::resolveModuleNameByPath($path);
            if ($moduleName !== '') {
                $state = TenantModuleService::moduleState($moduleName, AdminAuthService::tenantId());
                $accessMode = (string) ($state['access_mode'] ?? 'enabled');
                if ($accessMode === 'disabled') {
                    if ($this->expectsJson($request, $path)) {
                        return json([
                            'code' => 403,
                            'msg' => 'module_forbidden',
                            'error_key' => 'common.forbidden',
                            'data' => [
                                'module' => $moduleName,
                                'reason' => (string) ($state['reason'] ?? 'forbidden'),
                                'module_access_mode' => $accessMode,
                                'expires_at' => $state['expires_at'] ?? null,
                            ],
                        ]);
                    }

                    return $this->renderNoAccessPage($moduleName, $state);
                }
                if ($accessMode === 'expired_readonly' && $this->isWriteRequest($request)) {
                    return json([
                        'code' => 403,
                        'msg' => 'module_expired_readonly',
                        'error_key' => 'common.forbidden',
                        'data' => [
                            'module' => $moduleName,
                            'reason' => (string) ($state['reason'] ?? 'expired'),
                            'module_access_mode' => $accessMode,
                            'expires_at' => $state['expires_at'] ?? null,
                        ],
                    ]);
                }
            }

            return $next($request);
        }

        if ($this->expectsJson($request, $path)) {
            return json(['code' => 401, 'msg' => 'not_logged_in', 'error_key' => 'common.sessionExpired', 'data' => null]);
        }

        $redirect = $this->buildAdminRedirect($request, $path);
        $to = '/admin.php/auth/login?redirect=' . urlencode($redirect);
        return redirect($to);
    }

    private function buildAdminRedirect(Request $request, string $path): string
    {
        $base = '/admin.php';
        $uri = ($path === '/' ? $base : ($base . $path));

        $query = $request->get();
        if (!empty($query)) {
            $qs = http_build_query($query);
            if ($qs !== '') {
                $uri .= '?' . $qs;
            }
        }

        return $uri;
    }

    private function expectsJson(Request $request, string $path): bool
    {
        $accept = (string) $request->header('accept', '');
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }
        if ($request->isAjax()) {
            return true;
        }
        if (preg_match('#/(list|summary|sourceList|adapterList|jobList|jobLogs|nextTask)$#i', $path)) {
            return true;
        }
        if (preg_match('#^/(product_search|offline_order|influencer|category|extension|message_template|outreach_workspace|sample|industry_trend|competitor_analysis|ad_insight|data_import|profit_center|stats|mobile_task|mobile_device|mobile_agent|auto_dm|ops_frontend)/#i', $path)) {
            return true;
        }
        if (preg_match('#^/tenant/#i', $path)) {
            return true;
        }
        if (preg_match('#^/(client_version|client_license)/(list|add|batchGenerate|update|toggle|delete|unbind|uploadPackage)#i', $path)) {
            return true;
        }

        return false;
    }

    private function isWriteRequest(Request $request): bool
    {
        $method = strtoupper((string) $request->method());
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private function passesMobileAgentAuth(Request $request): bool
    {
        $token = trim((string) $request->header('x-mobile-agent-token', ''));
        if ($token === '') {
            $auth = trim((string) $request->header('authorization', ''));
            if (stripos($auth, 'bearer ') === 0) {
                $token = trim(substr($auth, 7));
            }
        }
        if ($token === '') {
            $raw = (string) $request->getContent();
            if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    $token = trim((string) ($json['token'] ?? ''));
                }
            }
        }
        if ($token === '') {
            $token = trim((string) $request->param('token', ''));
        }
        if ($token === '') {
            return false;
        }

        try {
            $exists = Db::name('mobile_devices')
                ->where('agent_token', $token)
                ->where('status', 1)
                ->count();
            return (int) $exists > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function isProfitPluginPublicPath(string $path): bool
    {
        return $path === '/profit_center/plugin/bootstrap'
            || $path === '/profit_center/plugin/ingestBatch';
    }

    private function passesProfitPluginAuth(Request $request): bool
    {
        try {
            $token = ProfitPluginTokenService::extractTokenFromRequest($request);
            $verify = ProfitPluginTokenService::verifyToken($token, ProfitPluginTokenService::SCOPE_INGEST);
            return (bool) ($verify['ok'] ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function renderNoAccessPage(string $moduleName, array $state): Response
    {
        $html = View::fetch('admin/common/no_access', [
            'module_name' => $moduleName,
            'module_reason' => (string) ($state['reason'] ?? 'forbidden'),
            'module_access_mode' => (string) ($state['access_mode'] ?? 'disabled'),
            'module_expires_at' => (string) ($state['expires_at'] ?? ''),
        ]);

        return Response::create($html, 'html', 403);
    }
}
