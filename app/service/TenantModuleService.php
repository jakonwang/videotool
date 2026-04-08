<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * Tenant-level module availability guard.
 */
class TenantModuleService
{
    private static ?bool $tableExists = null;

    /**
     * @var array<int, bool>
     */
    private static array $seeded = [];

    private static function hasSubscriptionTable(): bool
    {
        if (self::$tableExists !== null) {
            return self::$tableExists;
        }
        try {
            Db::name('tenant_module_subscriptions')->where('id', 0)->find();
            self::$tableExists = true;
        } catch (\Throwable $e) {
            self::$tableExists = false;
        }

        return self::$tableExists;
    }

    private static function seedTenantIfNeeded(int $tenantId): void
    {
        if (!self::hasSubscriptionTable() || $tenantId <= 0) {
            return;
        }
        if (!empty(self::$seeded[$tenantId])) {
            return;
        }
        self::$seeded[$tenantId] = true;

        try {
            $exists = (int) Db::name('tenant_module_subscriptions')
                ->where('tenant_id', $tenantId)
                ->count();
            if ($exists > 0) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $rows = [];
        $now = date('Y-m-d H:i:s');
        try {
            $extExists = false;
            try {
                Db::name('extensions')->where('id', 0)->find();
                $extExists = true;
            } catch (\Throwable $e) {
                $extExists = false;
            }
            if ($extExists) {
                $extensions = Db::name('extensions')->field('name,is_enabled')->select()->toArray();
                foreach ($extensions as $ext) {
                    $name = trim((string) ($ext['name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    $rows[] = [
                        'tenant_id' => $tenantId,
                        'module_name' => $name,
                        'is_enabled' => (int) ($ext['is_enabled'] ?? 1) === 1 ? 1 : 0,
                        'expires_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        } catch (\Throwable $e) {
            $rows = [];
        }

        if ($rows === []) {
            return;
        }

        try {
            Db::name('tenant_module_subscriptions')->insertAll($rows);
        } catch (\Throwable $e) {
            // ignore seed failures
        }
    }

    /**
     * @return array{allowed:bool,reason:string,expires_at:?string}
     */
    public static function moduleState(string $moduleName, ?int $tenantId = null): array
    {
        $name = trim($moduleName);
        if ($name === '') {
            return ['allowed' => true, 'reason' => 'none', 'expires_at' => null];
        }
        if (!self::hasSubscriptionTable()) {
            return ['allowed' => true, 'reason' => 'no_table', 'expires_at' => null];
        }

        $tid = $tenantId !== null ? (int) $tenantId : AdminAuthService::tenantId();
        if ($tid <= 0) {
            $tid = 1;
        }

        self::seedTenantIfNeeded($tid);

        try {
            $row = Db::name('tenant_module_subscriptions')
                ->where('tenant_id', $tid)
                ->where('module_name', $name)
                ->find();
        } catch (\Throwable $e) {
            return ['allowed' => true, 'reason' => 'query_failed', 'expires_at' => null];
        }

        if (!$row) {
            return ['allowed' => true, 'reason' => 'not_configured', 'expires_at' => null];
        }

        $enabled = (int) ($row['is_enabled'] ?? 1) === 1;
        $expiresAt = trim((string) ($row['expires_at'] ?? ''));
        if (!$enabled) {
            return ['allowed' => false, 'reason' => 'disabled', 'expires_at' => $expiresAt !== '' ? $expiresAt : null];
        }

        if ($expiresAt !== '') {
            $exp = strtotime($expiresAt);
            if ($exp !== false && $exp < time()) {
                return ['allowed' => false, 'reason' => 'expired', 'expires_at' => $expiresAt];
            }
        }

        return ['allowed' => true, 'reason' => 'ok', 'expires_at' => $expiresAt !== '' ? $expiresAt : null];
    }

    public static function moduleAllowed(string $moduleName, ?int $tenantId = null): bool
    {
        $state = self::moduleState($moduleName, $tenantId);

        return (bool) ($state['allowed'] ?? true);
    }

    /**
     * @param array<string, int> $enabledMap
     * @return array<string, int>
     */
    public static function filterEnabledMap(array $enabledMap, ?int $tenantId = null): array
    {
        if ($enabledMap === []) {
            return $enabledMap;
        }
        foreach ($enabledMap as $moduleName => $enabled) {
            if ((int) $enabled !== 1) {
                continue;
            }
            if (!self::moduleAllowed((string) $moduleName, $tenantId)) {
                $enabledMap[$moduleName] = 0;
            }
        }

        return $enabledMap;
    }
}

