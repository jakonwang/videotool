<?php
declare(strict_types=1);

namespace app\service;

use think\Request;
use think\facade\Db;

/**
 * Lightweight admin audit logger for sensitive operations.
 */
class AdminAuditService
{
    private static ?bool $tableExists = null;

    private static function hasLogTable(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }
        try {
            Db::name('admin_logs')->where('id', 0)->find();
            self::$tableExists = true;
        } catch (\Throwable $e) {
            self::$tableExists = false;
        }

        return self::$tableExists;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function log(Request $request, string $action, string $targetTable = '', int $targetId = 0, array $payload = []): void
    {
        if (!self::hasLogTable()) {
            return;
        }
        $action = trim($action);
        if ($action === '') {
            return;
        }

        $uid = AdminAuthService::userId();
        $tenantId = AdminAuthService::tenantId();
        $username = AdminAuthService::username();
        $role = AdminAuthService::role();
        $ip = (string) ($request->ip() ?? '');
        $ua = (string) $request->header('user-agent', '');
        $rawFingerprint = trim((string) $request->header('x-device-fingerprint', ''));
        if ($rawFingerprint === '') {
            $rawFingerprint = trim((string) $request->header('x-hardware-fingerprint', ''));
        }
        if ($rawFingerprint === '') {
            $rawFingerprint = trim((string) $request->param('fingerprint', ''));
        }
        if ($rawFingerprint === '') {
            $rawFingerprint = hash('sha256', $ip . '|' . $ua);
        }
        $fingerprintHash = hash('sha256', $rawFingerprint);

        try {
            Db::name('admin_logs')->insert([
                'tenant_id' => $tenantId > 0 ? $tenantId : 1,
                'admin_user_id' => $uid > 0 ? $uid : null,
                'admin_username' => $username !== '' ? $username : null,
                'admin_role' => $role !== '' ? $role : null,
                'action' => mb_substr($action, 0, 120),
                'target_table' => $targetTable !== '' ? mb_substr($targetTable, 0, 64) : null,
                'target_id' => $targetId > 0 ? $targetId : null,
                'request_path' => mb_substr('/' . ltrim((string) $request->pathinfo(), '/'), 0, 255),
                'request_method' => strtoupper((string) ($request->method() ?? '')),
                'ip' => mb_substr($ip, 0, 64),
                'user_agent' => $ua !== '' ? mb_substr($ua, 0, 500) : null,
                'hardware_fingerprint' => mb_substr($rawFingerprint, 0, 255),
                'fingerprint_hash' => $fingerprintHash,
                'payload_json' => $payload !== []
                    ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // ignore audit write failure
        }
    }
}

