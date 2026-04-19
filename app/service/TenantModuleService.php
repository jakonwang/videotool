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
    private static ?bool $tenantTableExists = null;

    /**
     * @var array<int, bool>
     */
    private static array $seeded = [];

    /**
     * @var array<int, bool>
     */
    private static array $tenantActiveCache = [];

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

    private static function hasTenantTable(): bool
    {
        if (self::$tenantTableExists !== null) {
            return self::$tenantTableExists;
        }
        try {
            Db::name('tenants')->where('id', 0)->find();
            self::$tenantTableExists = true;
        } catch (\Throwable $e) {
            self::$tenantTableExists = false;
        }

        return self::$tenantTableExists;
    }

    private static function tenantActive(int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }
        if (!self::hasTenantTable()) {
            return true;
        }
        if (array_key_exists($tenantId, self::$tenantActiveCache)) {
            return self::$tenantActiveCache[$tenantId];
        }

        try {
            $row = Db::name('tenants')->where('id', $tenantId)->find();
            $active = $row ? ((int) ($row['status'] ?? 0) === 1) : false;
        } catch (\Throwable $e) {
            $active = false;
        }
        self::$tenantActiveCache[$tenantId] = $active;

        return $active;
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
     * @return array{allowed:bool,reason:string,expires_at:?string,access_mode:string}
     */
    public static function moduleState(string $moduleName, ?int $tenantId = null): array
    {
        $name = trim($moduleName);
        if ($name === '') {
            return ['allowed' => true, 'reason' => 'none', 'expires_at' => null, 'access_mode' => 'enabled'];
        }
        if (!self::hasSubscriptionTable()) {
            return ['allowed' => true, 'reason' => 'no_table', 'expires_at' => null, 'access_mode' => 'enabled'];
        }

        $tid = $tenantId !== null ? (int) $tenantId : AdminAuthService::tenantId();
        if ($tid <= 0) {
            $tid = 1;
        }

        if (!self::tenantActive($tid)) {
            return ['allowed' => false, 'reason' => 'tenant_disabled', 'expires_at' => null, 'access_mode' => 'disabled'];
        }

        self::seedTenantIfNeeded($tid);

        try {
            $row = Db::name('tenant_module_subscriptions')
                ->where('tenant_id', $tid)
                ->where('module_name', $name)
                ->find();
        } catch (\Throwable $e) {
            return ['allowed' => true, 'reason' => 'query_failed', 'expires_at' => null, 'access_mode' => 'enabled'];
        }

        if (!$row) {
            return ['allowed' => true, 'reason' => 'not_configured', 'expires_at' => null, 'access_mode' => 'enabled'];
        }

        $enabled = (int) ($row['is_enabled'] ?? 1) === 1;
        $expiresAt = trim((string) ($row['expires_at'] ?? ''));
        if (!$enabled) {
            return ['allowed' => false, 'reason' => 'disabled', 'expires_at' => $expiresAt !== '' ? $expiresAt : null, 'access_mode' => 'disabled'];
        }

        if ($expiresAt !== '') {
            $exp = strtotime($expiresAt);
            if ($exp !== false && $exp < time()) {
                return ['allowed' => true, 'reason' => 'expired', 'expires_at' => $expiresAt, 'access_mode' => 'expired_readonly'];
            }
        }

        return ['allowed' => true, 'reason' => 'ok', 'expires_at' => $expiresAt !== '' ? $expiresAt : null, 'access_mode' => 'enabled'];
    }

    public static function moduleAccessMode(string $moduleName, ?int $tenantId = null): string
    {
        $state = self::moduleState($moduleName, $tenantId);
        $mode = (string) ($state['access_mode'] ?? 'enabled');

        return in_array($mode, ['enabled', 'disabled', 'expired_readonly'], true) ? $mode : 'enabled';
    }

    public static function moduleWriteAllowed(string $moduleName, ?int $tenantId = null): bool
    {
        return self::moduleAccessMode($moduleName, $tenantId) === 'enabled';
    }

    public static function moduleAllowed(string $moduleName, ?int $tenantId = null): bool
    {
        return self::moduleAccessMode($moduleName, $tenantId) !== 'disabled';
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
            if (self::moduleAccessMode((string) $moduleName, $tenantId) === 'disabled') {
                $enabledMap[$moduleName] = 0;
            }
        }

        return $enabledMap;
    }

    public static function resolveModuleNameByPath(string $path): string
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
        if (preg_match('#^/tenant(/|$)#i', $path)) {
            return 'platform_ops';
        }
        if (preg_match('#^/(settings|ops_center|client_license|client_version|cache|downloadlog|download_log|user|extension)(/|$)#i', $path)) {
            return 'system_ops';
        }

        return '';
    }
}
