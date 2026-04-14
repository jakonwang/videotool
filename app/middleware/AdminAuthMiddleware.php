<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\AdminAuthService;
use app\service\TenantModuleService;
use Closure;
use think\Request;
use think\facade\Db;

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

        if (AdminAuthService::isLoggedIn()) {
            $moduleName = $this->resolveModuleName($path);
            if ($moduleName !== '') {
                $state = TenantModuleService::moduleState($moduleName, AdminAuthService::tenantId());
                if (!(bool) ($state['allowed'] ?? true)) {
                    if ($this->expectsJson($request, $path)) {
                        return json([
                            'code' => 403,
                            'msg' => 'module_forbidden',
                            'error_key' => 'common.forbidden',
                            'data' => [
                                'module' => $moduleName,
                                'reason' => (string) ($state['reason'] ?? 'forbidden'),
                                'expires_at' => $state['expires_at'] ?? null,
                            ],
                        ]);
                    }

                    return redirect('/admin.php');
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

    private function resolveModuleName(string $path): string
    {
        if ($path === '/' || str_starts_with($path, '/stats/')) {
            return 'overview';
        }
        if (preg_match('#^/(product_search|offline_order)(/|$)#i', $path)) {
            return 'product_search';
        }
        if (preg_match('#^/industry_trend(/|$)#i', $path)) {
            return 'industry_trend';
        }
        if (preg_match('#^/competitor_analysis(/|$)#i', $path)) {
            return 'competitor_analysis';
        }
        if (preg_match('#^/ad_insight(/|$)#i', $path)) {
            return 'ad_insight';
        }
        if (preg_match('#^/data_import(/|$)#i', $path)) {
            return 'data_import';
        }
        if (preg_match('#^/profit_center(/|$)#i', $path)) {
            return 'profit_center';
        }
        if (preg_match('#^/category(/|$)#i', $path)) {
            return 'category';
        }
        if (preg_match('#^/(influencer|outreach_workspace|sample|message_template|distribute|mobile_task|mobile_device|mobile_agent|auto_dm)(/|$)#i', $path)) {
            return 'creator_crm';
        }
        if (preg_match('#^/(video|product)(/|$)#i', $path)) {
            return 'material_distribution';
        }
        if (preg_match('#^/(platform|device)(/|$)#i', $path)) {
            return 'terminal_devices';
        }
        if (preg_match('#^/(settings|ops_center|client_license|client_version|cache|downloadlog|download_log|user|extension)(/|$)#i', $path)) {
            return 'system_ops';
        }

        return '';
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
        if (preg_match('#/(list|listJson|summary|sourceList|adapterList|jobList|jobLogs|nextTask)$#i', $path)) {
            return true;
        }
        if (preg_match('#^/(product_search|offline_order|influencer|category|extension|message_template|outreach_workspace|sample|industry_trend|competitor_analysis|ad_insight|data_import|profit_center|stats|mobile_task|mobile_device|mobile_agent|auto_dm)/#i', $path)) {
            return true;
        }
        if (preg_match('#^/(client_version|client_license)/(list|add|batchGenerate|update|toggle|delete|unbind|uploadPackage)#i', $path)) {
            return true;
        }

        return false;
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
}
