<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\AdminAuthService;
use think\facade\View;

/**
 * 后台认证（登录/退出）
 */
class Auth extends BaseController
{
    public function login()
    {
        if ($this->request->isPost()) {
            $username = (string) $this->request->post('username', '');
            $password = (string) $this->request->post('password', '');
            $redirect = (string) $this->request->post('redirect', '');

            $ip = function_exists('get_client_ip') ? (string) get_client_ip() : ((string) ($this->request->ip() ?? ''));
            $res = AdminAuthService::attemptLogin($username, $password, $ip);
            if (!$res['ok']) {
                return json(['code' => 1, 'msg' => $res['msg'] ?? '登录失败']);
            }
            $redirect = $this->sanitizeRedirect($redirect);
            return json([
                'code' => 0,
                'msg' => $res['msg'] ?? 'ok',
                'data' => [
                    'redirect' => $redirect ?: '/admin.php',
                    'user' => $res['user'] ?? null,
                ],
            ]);
        }

        $redirect = (string) $this->request->param('redirect', '');
        return View::fetch('admin/auth/login', ['redirect' => $this->sanitizeRedirect($redirect)]);
    }

    private function sanitizeRedirect(string $redirect): string
    {
        $r = trim($redirect);
        if ($r === '') return '';

        // 允许绝对 URL 但只取其 path+query，并且必须指向 /admin.php
        if (stripos($r, 'http://') === 0 || stripos($r, 'https://') === 0) {
            $p = parse_url($r);
            $path = (string) ($p['path'] ?? '');
            $query = (string) ($p['query'] ?? '');
            $r = $path . ($query !== '' ? ('?' . $query) : '');
        }

        // 白名单：只允许跳转后台
        if (strpos($r, '/admin.php') !== 0) {
            return '';
        }
        return $r;
    }

    public function logout()
    {
        AdminAuthService::logout();
        return json(['code' => 0, 'msg' => '已退出']);
    }
}

