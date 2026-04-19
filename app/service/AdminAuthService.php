<?php
declare(strict_types=1);

namespace app\service;

use app\model\AdminUser;
use think\facade\Db;
use think\facade\Session;

class AdminAuthService
{
    public const SESSION_UID = 'admin_user_id';
    public const SESSION_TENANT_ID = 'admin_tenant_id';
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

    public static function tenantId(): int
    {
        $tid = (int) Session::get(self::SESSION_TENANT_ID, 1);
        return $tid > 0 ? $tid : 1;
    }

    public static function isLoggedIn(): bool
    {
        return self::userId() > 0;
    }

    public static function role(): string
    {
        $role = trim((string) Session::get(self::SESSION_ROLE, ''));
        if (!in_array($role, ['super_admin', 'operator', 'viewer'], true)) {
            return 'super_admin';
        }
        return $role;
    }

    public static function logout(): void
    {
        Session::delete(self::SESSION_UID);
        Session::delete(self::SESSION_TENANT_ID);
        Session::delete(self::SESSION_UNAME);
        Session::delete(self::SESSION_ROLE);
    }

    private static function tenantIsActive(int $tenantId): bool
    {
        $tenantId = $tenantId > 0 ? $tenantId : 1;
        try {
            Db::name('tenants')->where('id', 0)->find();
        } catch (\Throwable $e) {
            return true;
        }
        try {
            $row = Db::name('tenants')->where('id', $tenantId)->find();
            return $row ? ((int) ($row['status'] ?? 0) === 1) : false;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array{ok:bool,msg:string,user?:array{id:int,tenant_id:int,username:string,role:string}}
     */
    public static function attemptLogin(string $username, string $password, string $ip = ''): array
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return ['ok' => false, 'msg' => 'username_or_password_required'];
        }

        /** @var AdminUser|null $user */
        $user = AdminUser::where('username', $username)->find();
        if (!$user) {
            return ['ok' => false, 'msg' => 'invalid_credentials'];
        }
        if ((int) ($user->status ?? 0) !== 1) {
            return ['ok' => false, 'msg' => 'account_disabled'];
        }

        $tenantId = max(1, (int) ($user->tenant_id ?? 1));
        if (!self::tenantIsActive($tenantId)) {
            return ['ok' => false, 'msg' => 'tenant_disabled'];
        }

        $hash = (string) ($user->password_hash ?? '');
        if ($hash === '' || !password_verify($password, $hash)) {
            return ['ok' => false, 'msg' => 'invalid_credentials'];
        }

        Session::set(self::SESSION_UID, (int) $user->id);
        Session::set(self::SESSION_TENANT_ID, $tenantId);
        Session::set(self::SESSION_UNAME, (string) $user->username);
        Session::set(self::SESSION_ROLE, (string) ($user->role ?: 'super_admin'));

        $user->last_login_ip = $ip ?: null;
        $user->last_login_at = date('Y-m-d H:i:s');
        $user->save();

        return [
            'ok' => true,
            'msg' => 'login_success',
            'user' => [
                'id' => (int) $user->id,
                'tenant_id' => $tenantId,
                'username' => (string) $user->username,
                'role' => (string) ($user->role ?: 'super_admin'),
            ],
        ];
    }
}
