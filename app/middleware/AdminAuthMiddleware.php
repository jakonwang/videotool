<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\AdminAuthService;
use Closure;
use think\Request;

class AdminAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!defined('ENTRY_FILE') || ENTRY_FILE !== 'admin') {
            return $next($request);
        }

        $path = '/' . ltrim((string) $request->pathinfo(), '/');
        $path = rtrim($path, '/');
        if ($path === '') $path = '/';

        // 放行：登录与退出
        if ($path === '/auth/login' || $path === '/auth/logout') {
            return $next($request);
        }

        if (AdminAuthService::isLoggedIn()) {
            return $next($request);
        }

        if ($this->expectsJson($request, $path)) {
            return json(['code' => 401, 'msg' => '未登录', 'data' => null]);
        }

        $redirect = $this->buildAdminRedirect($request, $path);
        $to = '/admin.php/auth/login?redirect=' . urlencode($redirect);
        return redirect($to);
    }

    private function buildAdminRedirect(Request $request, string $path): string
    {
        // 统一生成后台可回跳地址，避免某些环境下 url(true) 变成站点根（前端入口）
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
        // list 接口约定：多为 JSON
        if (preg_match('#/(list|listJson)$#i', $path)) {
            return true;
        }
        // 桌面端发卡/版本：POST/JSON 接口在未登录时须返回 JSON，避免 fetch 收到登录页 HTML
        if (preg_match('#^/(client_version|client_license)/(list|add|batchGenerate|update|toggle|delete|unbind|uploadPackage)#i', $path)) {
            return true;
        }
        if (preg_match('#^/product_search/(list|importCsv|importTaskStatus|importTaskTick|syncAliyunQueue|batchDelete|update|delete|sampleCsv)#i', $path)) {
            return true;
        }
        if (preg_match('#^/influencer/(list|search|importCsv|importTaskStatus|importTaskTick)#i', $path)) {
            return true;
        }
        return false;
    }
}

