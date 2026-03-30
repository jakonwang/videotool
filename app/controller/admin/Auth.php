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
        return View::fetch('admin/auth/login', [
            'redirect' => $redirect,
        ]);
    }

    public function logout()
    {
        AdminAuthService::logout();
        return json(['code' => 0, 'msg' => '已退出']);
    }
}

