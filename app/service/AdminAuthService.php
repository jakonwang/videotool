<?php
declare(strict_types=1);

namespace app\service;

use app\model\AdminUser;
use think\facade\Session;

class AdminAuthService
{
    public const SESSION_UID = 'admin_user_id';
    public const SESSION_UNAME = 'admin_username';
    public const SESSION_ROLE = 'admin_role';

    public static function userId(): int
    {
        return (int) Session::get(self::SESSION_UID, 0);
    }

    public static function username(): string
    {
        return (string) Session::get(self::SESSION_UNAME, '');
    }

    public static function isLoggedIn(): bool
    {
        return self::userId() > 0;
    }

    public static function role(): string
    {
        $r = trim((string) Session::get(self::SESSION_ROLE, ''));
        if (!in_array($r, ['super_admin', 'operator', 'viewer'], true)) {
            return 'super_admin';
        }
        return $r;
    }

    public static function logout(): void
    {
        Session::delete(self::SESSION_UID);
        Session::delete(self::SESSION_UNAME);
        Session::delete(self::SESSION_ROLE);
    }

    /**
     * @return array{ok:bool,msg:string,user?:array{id:int,username:string,role:string}}
     */
    public static function attemptLogin(string $username, string $password, string $ip = ''): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return ['ok' => false, 'msg' => '请输入用户名和密码'];
        }

        /** @var AdminUser|null $user */
        $user = AdminUser::where('username', $username)->find();
        if (!$user) {
            return ['ok' => false, 'msg' => '用户名或密码错误'];
        }
        if ((int) ($user->status ?? 0) !== 1) {
            return ['ok' => false, 'msg' => '账号已禁用'];
        }

        $hash = (string) ($user->password_hash ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return ['ok' => false, 'msg' => '用户名或密码错误'];
        }

        Session::set(self::SESSION_UID, (int) $user->id);
        Session::set(self::SESSION_UNAME, (string) $user->username);
        Session::set(self::SESSION_ROLE, (string) ($user->role ?: 'super_admin'));

        $user->last_login_ip = $ip ?: null;
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->save();

        return [
            'ok' => true,
            'msg' => '登录成功',
            'user' => [
                'id' => (int) $user->id,
                'username' => (string) $user->username,
                'role' => (string) ($user->role ?: 'super_admin'),
            ],
        ];
    }
}

