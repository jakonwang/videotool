<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;
use think\facade\Session;

class TenantSaasService
{
    /**
     * @var array<string, bool>
     */
    private static array $tableExistsCache = [];

    private static function tableExists(string $table): bool
    {
        $table = trim($table);
        if ($table === '') {
            return false;
        }
        if (array_key_exists($table, self::$tableExistsCache)) {
            return self::$tableExistsCache[$table];
        }
        try {
            Db::name($table)->where('id', 0)->find();
            self::$tableExistsCache[$table] = true;
        } catch (\Throwable $e) {
            self::$tableExistsCache[$table] = false;
        }

        return self::$tableExistsCache[$table];
    }

    private static function hasColumn(string $table, string $column): bool
    {
        if (!self::tableExists($table)) {
            return false;
        }
        try {
            $fields = Db::name($table)->getFields();
            return is_array($fields) && array_key_exists($column, $fields);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function normalizeRole(string $role): string
    {
        $role = trim($role);
        if (!in_array($role, ['super_admin', 'operator', 'viewer'], true)) {
            return 'operator';
        }

        return $role;
    }

    private static function normalizeTenantCode(string $code): string
    {
        $normalized = strtolower(trim($code));
        $normalized = preg_replace('/[^a-z0-9_\-]/', '_', $normalized) ?: '';
        $normalized = trim($normalized, '_-');
        if ($normalized === '') {
            $normalized = 'tenant_' . date('ymdHis');
        }
        if (strlen($normalized) > 64) {
            $normalized = substr($normalized, 0, 64);
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     * @param array<string> $allowed
     * @return array<string>
     */
    private static function normalizeModuleList($value, array $allowed = []): array
    {
        $raw = [];
        if (is_array($value)) {
            $raw = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $parsed = json_decode($value, true);
            if (is_array($parsed)) {
                $raw = $parsed;
            } else {
                $raw = preg_split('/[,\s]+/', $value) ?: [];
            }
        }

        $allowedMap = $allowed !== [] ? array_fill_keys($allowed, 1) : [];
        $result = [];
        foreach ($raw as $item) {
            $name = trim((string) $item);
            if ($name === '') {
                continue;
            }
            if ($allowedMap !== [] && !isset($allowedMap[$name])) {
                continue;
            }
            $result[$name] = true;
        }

        return array_keys($result);
    }

    private static function normalizeDateTime($value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $ts);
    }

    /**
     * @return array<string>
     */
    public static function allModuleNames(): array
    {
        $rows = ModuleManagerService::scanModules();
        $modules = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $modules[$name] = true;
            }
        }

        $list = array_keys($modules);
        sort($list);

        return $list;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function moduleCatalog(): array
    {
        $rows = ModuleManagerService::scanModules();
        $items = [];
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $items[] = [
                'name' => $name,
                'title' => (string) ($row['title'] ?? $name),
                'min_role' => (string) ($row['min_role'] ?? 'operator'),
                'default_enabled' => (int) ($row['default_enabled'] ?? 1) === 1 ? 1 : 0,
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $items;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public static function listTenants(array $filters = []): array
    {
        if (!self::tableExists('tenants')) {
            return [];
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $status = $filters['status'] ?? null;
        $query = Db::name('tenants')->alias('t');

        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('t.tenant_name', '%' . $keyword . '%')
                    ->whereOrLike('t.tenant_code', '%' . $keyword . '%');
            });
        }
        if ($status !== null && $status !== '' && (int) $status >= 0) {
            $query->where('t.status', (int) $status === 1 ? 1 : 0);
        }

        $rows = $query->order('t.id', 'desc')->select()->toArray();

        $subscriptionMap = [];
        if (self::tableExists('tenant_subscriptions')) {
            $subs = Db::name('tenant_subscriptions')->select()->toArray();
            foreach ($subs as $sub) {
                $subscriptionMap[(int) ($sub['tenant_id'] ?? 0)] = $sub;
            }
        }

        $packageMap = [];
        if (self::tableExists('tenant_packages')) {
            $packages = Db::name('tenant_packages')->field('id,package_name,package_code,status')->select()->toArray();
            foreach ($packages as $pkg) {
                $packageMap[(int) ($pkg['id'] ?? 0)] = $pkg;
            }
        }

        $adminCountMap = [];
        if (self::tableExists('admin_users')) {
            $adminRows = Db::name('admin_users')->field('tenant_id,COUNT(*) AS c')->group('tenant_id')->select()->toArray();
            foreach ($adminRows as $row) {
                $adminCountMap[(int) ($row['tenant_id'] ?? 0)] = (int) ($row['c'] ?? 0);
            }
        }

        $items = [];
        foreach ($rows as $row) {
            $tenantId = (int) ($row['id'] ?? 0);
            $sub = $subscriptionMap[$tenantId] ?? null;
            $package = null;
            if (is_array($sub)) {
                $package = $packageMap[(int) ($sub['package_id'] ?? 0)] ?? null;
            }
            $items[] = [
                'tenant_id' => $tenantId,
                'tenant_code' => (string) ($row['tenant_code'] ?? ''),
                'tenant_name' => (string) ($row['tenant_name'] ?? ''),
                'status' => (int) ($row['status'] ?? 0),
                'remark' => (string) ($row['remark'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
                'package_id' => is_array($sub) ? (int) ($sub['package_id'] ?? 0) : 0,
                'package_name' => is_array($package) ? (string) ($package['package_name'] ?? '') : '',
                'package_code' => is_array($package) ? (string) ($package['package_code'] ?? '') : '',
                'subscription_status' => is_array($sub) ? ((int) ($sub['status'] ?? 0) === 1 ? 1 : 0) : 0,
                'expires_at' => is_array($sub) ? (string) ($sub['expires_at'] ?? '') : '',
                'admin_count' => (int) ($adminCountMap[$tenantId] ?? 0),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok:bool,message:string,tenant_id?:int}
     */
    public static function saveTenant(array $payload): array
    {
        if (!self::tableExists('tenants')) {
            return ['ok' => false, 'message' => 'tenants_table_missing'];
        }

        $tenantId = (int) ($payload['tenant_id'] ?? $payload['id'] ?? 0);
        $tenantCodeRaw = (string) ($payload['tenant_code'] ?? '');
        $tenantCode = self::normalizeTenantCode($tenantCodeRaw);
        $tenantName = trim((string) ($payload['tenant_name'] ?? ''));
        $status = (int) ($payload['status'] ?? 1) === 1 ? 1 : 0;
        $remark = trim((string) ($payload['remark'] ?? ''));

        if ($tenantName === '') {
            return ['ok' => false, 'message' => 'tenant_name_required'];
        }

        if ($tenantId > 0) {
            $existing = Db::name('tenants')->where('id', $tenantId)->find();
            if (!$existing) {
                return ['ok' => false, 'message' => 'tenant_not_found'];
            }
            if ($tenantCodeRaw === '') {
                $tenantCode = (string) ($existing['tenant_code'] ?? $tenantCode);
            }
            $dupQuery = Db::name('tenants')->where('tenant_code', $tenantCode)->where('id', '<>', $tenantId);
            if ($dupQuery->find()) {
                return ['ok' => false, 'message' => 'tenant_code_duplicated'];
            }

            Db::name('tenants')->where('id', $tenantId)->update([
                'tenant_code' => $tenantCode,
                'tenant_name' => $tenantName,
                'status' => $status,
                'remark' => $remark,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } else {
            $baseCode = $tenantCode;
            $index = 1;
            while (Db::name('tenants')->where('tenant_code', $tenantCode)->find()) {
                $suffix = '_' . $index;
                $tenantCode = substr($baseCode, 0, max(1, 64 - strlen($suffix))) . $suffix;
                ++$index;
            }

            $tenantId = (int) Db::name('tenants')->insertGetId([
                'tenant_code' => $tenantCode,
                'tenant_name' => $tenantName,
                'status' => $status,
                'remark' => $remark,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($tenantId <= 0) {
            return ['ok' => false, 'message' => 'tenant_save_failed'];
        }

        if (self::tableExists('tenant_subscriptions') && (isset($payload['package_id']) || isset($payload['expires_at']) || isset($payload['addon_modules']) || isset($payload['disabled_modules']))) {
            $subPayload = [
                'tenant_id' => $tenantId,
                'package_id' => (int) ($payload['package_id'] ?? 0),
                'expires_at' => $payload['expires_at'] ?? null,
                'addon_modules' => $payload['addon_modules'] ?? [],
                'disabled_modules' => $payload['disabled_modules'] ?? [],
                'status' => $payload['subscription_status'] ?? 1,
            ];
            $subResult = self::saveSubscription($subPayload);
            if (!($subResult['ok'] ?? false)) {
                return ['ok' => false, 'message' => (string) ($subResult['message'] ?? 'subscription_save_failed')];
            }
        }

        return ['ok' => true, 'message' => 'ok', 'tenant_id' => $tenantId];
    }

    public static function updateTenantStatus(int $tenantId, int $status): array
    {
        if (!self::tableExists('tenants')) {
            return ['ok' => false, 'message' => 'tenants_table_missing'];
        }
        if ($tenantId <= 0) {
            return ['ok' => false, 'message' => 'invalid_tenant_id'];
        }

        $row = Db::name('tenants')->where('id', $tenantId)->find();
        if (!$row) {
            return ['ok' => false, 'message' => 'tenant_not_found'];
        }

        Db::name('tenants')->where('id', $tenantId)->update([
            'status' => $status === 1 ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return ['ok' => true, 'message' => 'ok'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listPackages(): array
    {
        if (!self::tableExists('tenant_packages')) {
            return [];
        }

        $packages = Db::name('tenant_packages')->order('id', 'desc')->select()->toArray();
        $moduleRows = self::tableExists('tenant_package_modules')
            ? Db::name('tenant_package_modules')->order('id', 'asc')->select()->toArray()
            : [];

        $grouped = [];
        foreach ($moduleRows as $row) {
            $packageId = (int) ($row['package_id'] ?? 0);
            if ($packageId <= 0) {
                continue;
            }
            $grouped[$packageId][] = [
                'module_name' => (string) ($row['module_name'] ?? ''),
                'is_optional' => (int) ($row['is_optional'] ?? 0) === 1 ? 1 : 0,
                'default_enabled' => (int) ($row['default_enabled'] ?? 1) === 1 ? 1 : 0,
            ];
        }

        $items = [];
        foreach ($packages as $pkg) {
            $packageId = (int) ($pkg['id'] ?? 0);
            $moduleItems = $grouped[$packageId] ?? [];
            $modules = [];
            $optionalModules = [];
            foreach ($moduleItems as $moduleItem) {
                $name = trim((string) ($moduleItem['module_name'] ?? ''));
                if ($name === '') {
                    continue;
                }
                $modules[] = $name;
                if ((int) ($moduleItem['is_optional'] ?? 0) === 1) {
                    $optionalModules[] = $name;
                }
            }
            $items[] = [
                'id' => $packageId,
                'package_code' => (string) ($pkg['package_code'] ?? ''),
                'package_name' => (string) ($pkg['package_name'] ?? ''),
                'description' => (string) ($pkg['description'] ?? ''),
                'status' => (int) ($pkg['status'] ?? 0),
                'modules' => $modules,
                'optional_modules' => $optionalModules,
                'created_at' => (string) ($pkg['created_at'] ?? ''),
                'updated_at' => (string) ($pkg['updated_at'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok:bool,message:string,package_id?:int}
     */
    public static function savePackage(array $payload): array
    {
        if (!self::tableExists('tenant_packages') || !self::tableExists('tenant_package_modules')) {
            return ['ok' => false, 'message' => 'tenant_package_tables_missing'];
        }

        $packageId = (int) ($payload['id'] ?? $payload['package_id'] ?? 0);
        $packageCode = self::normalizeTenantCode((string) ($payload['package_code'] ?? ''));
        $packageName = trim((string) ($payload['package_name'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $status = (int) ($payload['status'] ?? 1) === 1 ? 1 : 0;

        if ($packageName === '') {
            return ['ok' => false, 'message' => 'package_name_required'];
        }

        $allModules = self::allModuleNames();
        $modules = self::normalizeModuleList($payload['modules'] ?? [], $allModules);
        if ($modules === []) {
            $modules = $allModules;
        }
        $optionalModules = self::normalizeModuleList($payload['optional_modules'] ?? [], $modules);
        $optionalMap = array_fill_keys($optionalModules, true);

        Db::startTrans();
        try {
            if ($packageId > 0) {
                $pkgRow = Db::name('tenant_packages')->where('id', $packageId)->find();
                if (!$pkgRow) {
                    Db::rollback();
                    return ['ok' => false, 'message' => 'package_not_found'];
                }
                $dup = Db::name('tenant_packages')->where('package_code', $packageCode)->where('id', '<>', $packageId)->find();
                if ($dup) {
                    Db::rollback();
                    return ['ok' => false, 'message' => 'package_code_duplicated'];
                }

                Db::name('tenant_packages')->where('id', $packageId)->update([
                    'package_code' => $packageCode,
                    'package_name' => $packageName,
                    'description' => $description,
                    'status' => $status,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                Db::name('tenant_package_modules')->where('package_id', $packageId)->delete();
            } else {
                while (Db::name('tenant_packages')->where('package_code', $packageCode)->find()) {
                    $packageCode .= '_x';
                }
                $packageId = (int) Db::name('tenant_packages')->insertGetId([
                    'package_code' => $packageCode,
                    'package_name' => $packageName,
                    'description' => $description,
                    'status' => $status,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $now = date('Y-m-d H:i:s');
            foreach ($modules as $moduleName) {
                Db::name('tenant_package_modules')->insert([
                    'package_id' => $packageId,
                    'module_name' => $moduleName,
                    'is_optional' => isset($optionalMap[$moduleName]) ? 1 : 0,
                    'default_enabled' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return ['ok' => false, 'message' => 'package_save_failed'];
        }

        if (self::tableExists('tenant_subscriptions')) {
            $tenantIds = Db::name('tenant_subscriptions')->where('package_id', $packageId)->column('tenant_id');
            foreach ($tenantIds as $tenantId) {
                self::syncTenantModuleSubscriptions((int) $tenantId);
            }
        }

        return ['ok' => true, 'message' => 'ok', 'package_id' => $packageId];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok:bool,message:string}
     */
    public static function saveSubscription(array $payload): array
    {
        if (!self::tableExists('tenant_subscriptions') || !self::tableExists('tenants')) {
            return ['ok' => false, 'message' => 'tenant_subscription_tables_missing'];
        }

        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        if ($tenantId <= 0) {
            return ['ok' => false, 'message' => 'invalid_tenant_id'];
        }
        $tenant = Db::name('tenants')->where('id', $tenantId)->find();
        if (!$tenant) {
            return ['ok' => false, 'message' => 'tenant_not_found'];
        }

        $packageId = (int) ($payload['package_id'] ?? 0);
        if ($packageId <= 0 || !self::tableExists('tenant_packages')) {
            return ['ok' => false, 'message' => 'invalid_package_id'];
        }
        $package = Db::name('tenant_packages')->where('id', $packageId)->find();
        if (!$package) {
            return ['ok' => false, 'message' => 'package_not_found'];
        }

        $allModules = self::allModuleNames();
        $addonModules = self::normalizeModuleList($payload['addon_modules'] ?? [], $allModules);
        $disabledModules = self::normalizeModuleList($payload['disabled_modules'] ?? [], $allModules);
        $expiresAt = self::normalizeDateTime($payload['expires_at'] ?? null);
        $status = (int) ($payload['status'] ?? 1) === 1 ? 1 : 0;

        $exists = Db::name('tenant_subscriptions')->where('tenant_id', $tenantId)->find();
        $saveData = [
            'package_id' => $packageId,
            'status' => $status,
            'expires_at' => $expiresAt,
            'addon_modules_json' => json_encode(array_values($addonModules), JSON_UNESCAPED_UNICODE),
            'disabled_modules_json' => json_encode(array_values($disabledModules), JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($exists) {
            Db::name('tenant_subscriptions')->where('id', (int) $exists['id'])->update($saveData);
        } else {
            $saveData['tenant_id'] = $tenantId;
            $saveData['created_at'] = date('Y-m-d H:i:s');
            Db::name('tenant_subscriptions')->insert($saveData);
        }

        self::syncTenantModuleSubscriptions($tenantId);

        return ['ok' => true, 'message' => 'ok'];
    }

    public static function syncTenantModuleSubscriptions(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        if (!self::tableExists('tenant_module_subscriptions') || !self::tableExists('tenant_subscriptions')) {
            return;
        }

        $sub = Db::name('tenant_subscriptions')->where('tenant_id', $tenantId)->find();
        if (!$sub) {
            return;
        }

        $allModules = self::allModuleNames();
        $effective = [];
        if ((int) ($sub['status'] ?? 1) === 1) {
            $packageModules = [];
            if (self::tableExists('tenant_package_modules')) {
                $rows = Db::name('tenant_package_modules')
                    ->where('package_id', (int) ($sub['package_id'] ?? 0))
                    ->select()
                    ->toArray();
                foreach ($rows as $row) {
                    $name = trim((string) ($row['module_name'] ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    if ((int) ($row['default_enabled'] ?? 1) !== 1) {
                        continue;
                    }
                    $packageModules[$name] = true;
                }
            }

            $disabled = self::normalizeModuleList((string) ($sub['disabled_modules_json'] ?? '[]'), $allModules);
            foreach ($disabled as $moduleName) {
                unset($packageModules[$moduleName]);
            }

            $addon = self::normalizeModuleList((string) ($sub['addon_modules_json'] ?? '[]'), $allModules);
            foreach ($addon as $moduleName) {
                $packageModules[$moduleName] = true;
            }
            $effective = $packageModules;
        }

        $expiresAt = self::normalizeDateTime($sub['expires_at'] ?? null);
        $now = date('Y-m-d H:i:s');

        foreach ($allModules as $moduleName) {
            $isEnabled = isset($effective[$moduleName]) ? 1 : 0;
            $row = Db::name('tenant_module_subscriptions')
                ->where('tenant_id', $tenantId)
                ->where('module_name', $moduleName)
                ->find();

            if ($row) {
                Db::name('tenant_module_subscriptions')->where('id', (int) $row['id'])->update([
                    'is_enabled' => $isEnabled,
                    'expires_at' => $expiresAt,
                    'updated_at' => $now,
                ]);
            } else {
                Db::name('tenant_module_subscriptions')->insert([
                    'tenant_id' => $tenantId,
                    'module_name' => $moduleName,
                    'is_enabled' => $isEnabled,
                    'expires_at' => $expiresAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function tenantModules(int $tenantId): array
    {
        if ($tenantId <= 0) {
            return [];
        }

        $catalogRows = self::moduleCatalog();
        $catalogMap = [];
        foreach ($catalogRows as $row) {
            $catalogMap[(string) $row['name']] = $row;
        }

        $rows = self::tableExists('tenant_module_subscriptions')
            ? Db::name('tenant_module_subscriptions')->where('tenant_id', $tenantId)->select()->toArray()
            : [];

        $rowMap = [];
        foreach ($rows as $row) {
            $moduleName = trim((string) ($row['module_name'] ?? ''));
            if ($moduleName === '') {
                continue;
            }
            $rowMap[$moduleName] = $row;
        }

        $allModules = self::allModuleNames();
        $items = [];
        foreach ($allModules as $moduleName) {
            $state = TenantModuleService::moduleState($moduleName, $tenantId);
            $subRow = $rowMap[$moduleName] ?? null;
            $catalog = $catalogMap[$moduleName] ?? ['title' => $moduleName, 'min_role' => 'operator'];
            $items[] = [
                'tenant_id' => $tenantId,
                'module_name' => $moduleName,
                'module_title' => (string) ($catalog['title'] ?? $moduleName),
                'min_role' => (string) ($catalog['min_role'] ?? 'operator'),
                'is_enabled' => is_array($subRow) ? ((int) ($subRow['is_enabled'] ?? 0) === 1 ? 1 : 0) : 1,
                'expires_at' => is_array($subRow) ? (string) ($subRow['expires_at'] ?? '') : '',
                'module_access_mode' => (string) ($state['access_mode'] ?? 'enabled'),
                'reason' => (string) ($state['reason'] ?? 'ok'),
            ];
        }

        usort($items, static function (array $a, array $b): int {
            return strcmp((string) ($a['module_name'] ?? ''), (string) ($b['module_name'] ?? ''));
        });

        return $items;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public static function listTenantAdmins(array $filters = []): array
    {
        if (!self::tableExists('admin_users')) {
            return [];
        }

        $tenantId = (int) ($filters['tenant_id'] ?? 0);
        $keyword = trim((string) ($filters['keyword'] ?? ''));

        $query = Db::name('admin_users')->alias('u');
        if ($tenantId > 0 && self::hasColumn('admin_users', 'tenant_id')) {
            $query->where('u.tenant_id', $tenantId);
        }
        if ($keyword !== '') {
            $query->whereLike('u.username', '%' . $keyword . '%');
        }

        $rows = $query->order('u.id', 'desc')->select()->toArray();

        $tenantMap = [];
        if (self::tableExists('tenants')) {
            $tenants = Db::name('tenants')->field('id,tenant_name,tenant_code')->select()->toArray();
            foreach ($tenants as $tenant) {
                $tenantMap[(int) ($tenant['id'] ?? 0)] = $tenant;
            }
        }

        $items = [];
        foreach ($rows as $row) {
            $tid = (int) ($row['tenant_id'] ?? 1);
            $tenant = $tenantMap[$tid] ?? null;
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'tenant_id' => $tid,
                'tenant_name' => is_array($tenant) ? (string) ($tenant['tenant_name'] ?? '') : '',
                'tenant_code' => is_array($tenant) ? (string) ($tenant['tenant_code'] ?? '') : '',
                'username' => (string) ($row['username'] ?? ''),
                'role' => (string) ($row['role'] ?? 'operator'),
                'status' => (int) ($row['status'] ?? 0),
                'last_login_at' => (string) ($row['last_login_at'] ?? ''),
                'last_login_ip' => (string) ($row['last_login_ip'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok:bool,message:string,id?:int}
     */
    public static function saveTenantAdmin(array $payload): array
    {
        if (!self::tableExists('admin_users')) {
            return ['ok' => false, 'message' => 'admin_users_table_missing'];
        }

        $id = (int) ($payload['id'] ?? 0);
        $tenantId = (int) ($payload['tenant_id'] ?? 0);
        $username = trim((string) ($payload['username'] ?? ''));
        $role = self::normalizeRole((string) ($payload['role'] ?? 'operator'));
        $status = (int) ($payload['status'] ?? 1) === 1 ? 1 : 0;
        $password = (string) ($payload['password'] ?? '');

        if ($tenantId <= 0) {
            return ['ok' => false, 'message' => 'invalid_tenant_id'];
        }
        if ($username === '') {
            return ['ok' => false, 'message' => 'username_required'];
        }

        if ($id > 0) {
            $row = Db::name('admin_users')->where('id', $id)->find();
            if (!$row) {
                return ['ok' => false, 'message' => 'admin_not_found'];
            }

            $dup = Db::name('admin_users')->where('username', $username)->where('id', '<>', $id)->find();
            if ($dup) {
                return ['ok' => false, 'message' => 'username_duplicated'];
            }

            $saveData = [
                'username' => $username,
                'role' => $role,
                'status' => $status,
            ];
            if (self::hasColumn('admin_users', 'tenant_id')) {
                $saveData['tenant_id'] = $tenantId;
            }
            if ($password !== '') {
                if (strlen($password) < 6) {
                    return ['ok' => false, 'message' => 'password_too_short'];
                }
                $saveData['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
            }
            Db::name('admin_users')->where('id', $id)->update($saveData);

            return ['ok' => true, 'message' => 'ok', 'id' => $id];
        }

        if (strlen($password) < 6) {
            return ['ok' => false, 'message' => 'password_too_short'];
        }
        if (Db::name('admin_users')->where('username', $username)->find()) {
            return ['ok' => false, 'message' => 'username_duplicated'];
        }

        $insert = [
            'username' => $username,
            'role' => $role,
            'status' => $status,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ];
        if (self::hasColumn('admin_users', 'tenant_id')) {
            $insert['tenant_id'] = $tenantId;
        }
        $newId = (int) Db::name('admin_users')->insertGetId($insert);

        return ['ok' => true, 'message' => 'ok', 'id' => $newId];
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public static function listTenantAudit(array $filters = []): array
    {
        if (!self::tableExists('admin_logs')) {
            return [];
        }

        $tenantId = (int) ($filters['tenant_id'] ?? 0);
        $action = trim((string) ($filters['action'] ?? ''));
        $limit = (int) ($filters['limit'] ?? 100);
        $limit = max(1, min(500, $limit));

        $query = Db::name('admin_logs')->alias('l');
        if ($tenantId > 0) {
            $query->where('l.tenant_id', $tenantId);
        }
        if ($action !== '') {
            $query->whereLike('l.action', '%' . $action . '%');
        }

        $rows = $query->order('l.id', 'desc')->limit($limit)->select()->toArray();
        $items = [];
        foreach ($rows as $row) {
            $payload = [];
            $payloadRaw = trim((string) ($row['payload_json'] ?? ''));
            if ($payloadRaw !== '') {
                $json = json_decode($payloadRaw, true);
                if (is_array($json)) {
                    $payload = $json;
                }
            }
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'tenant_id' => (int) ($row['tenant_id'] ?? 0),
                'admin_user_id' => (int) ($row['admin_user_id'] ?? 0),
                'admin_username' => (string) ($row['admin_username'] ?? ''),
                'admin_role' => (string) ($row['admin_role'] ?? ''),
                'action' => (string) ($row['action'] ?? ''),
                'target_table' => (string) ($row['target_table'] ?? ''),
                'target_id' => (int) ($row['target_id'] ?? 0),
                'request_path' => (string) ($row['request_path'] ?? ''),
                'request_method' => (string) ($row['request_method'] ?? ''),
                'ip' => (string) ($row['ip'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'payload' => $payload,
            ];
        }

        return $items;
    }

    public static function switchTenant(int $tenantId): array
    {
        if ($tenantId <= 0) {
            return ['ok' => false, 'message' => 'invalid_tenant_id'];
        }
        if (!self::tableExists('tenants')) {
            return ['ok' => false, 'message' => 'tenants_table_missing'];
        }

        $tenant = Db::name('tenants')->where('id', $tenantId)->find();
        if (!$tenant) {
            return ['ok' => false, 'message' => 'tenant_not_found'];
        }
        if ((int) ($tenant['status'] ?? 0) !== 1) {
            return ['ok' => false, 'message' => 'tenant_disabled'];
        }

        Session::set(AdminAuthService::SESSION_TENANT_ID, $tenantId);

        return ['ok' => true, 'message' => 'ok'];
    }
}
