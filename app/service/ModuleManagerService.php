<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * Dynamic module and sidebar menu manager.
 */
class ModuleManagerService
{
    private const ROLE_SUPER_ADMIN = 'super_admin';
    private const ROLE_OPERATOR = 'operator';
    private const ROLE_VIEWER = 'viewer';

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function builtInModules(): array
    {
        return [
            'overview' => [
                'name' => 'overview',
                'title' => 'Overview',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => [],
                'min_role' => self::ROLE_VIEWER,
            ],
            'product_search' => [
                'name' => 'product_search',
                'title' => 'Style Search',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => [],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'growth_hub' => [
                'name' => 'growth_hub',
                'title' => 'Growth Hub',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => [],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'industry_trend' => [
                'name' => 'industry_trend',
                'title' => 'Industry Trend',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => ['growth_hub'],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'competitor_analysis' => [
                'name' => 'competitor_analysis',
                'title' => 'Competitor Analysis',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => ['growth_hub'],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'ad_insight' => [
                'name' => 'ad_insight',
                'title' => 'Ad Insight',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => ['growth_hub'],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'data_import' => [
                'name' => 'data_import',
                'title' => 'Data Import',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => ['growth_hub'],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'creator_crm' => [
                'name' => 'creator_crm',
                'title' => 'Creator CRM',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => [],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'category' => [
                'name' => 'category',
                'title' => 'Category Config',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => ['creator_crm'],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'influencer' => [
                'name' => 'influencer',
                'title' => 'Influencer List',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => ['creator_crm'],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'outreach_workspace' => [
                'name' => 'outreach_workspace',
                'title' => 'Outreach Workspace',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => ['creator_crm'],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'sample_management' => [
                'name' => 'sample_management',
                'title' => 'Sample Management',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => ['creator_crm'],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'message_template' => [
                'name' => 'message_template',
                'title' => 'Message Templates',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => ['creator_crm'],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'distribute' => [
                'name' => 'distribute',
                'title' => 'Creator Links',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => ['creator_crm'],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'material_distribution' => [
                'name' => 'material_distribution',
                'title' => 'Material Distribution',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => [],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'terminal_devices' => [
                'name' => 'terminal_devices',
                'title' => 'Terminal Devices',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => [],
                'min_role' => self::ROLE_OPERATOR,
            ],
            'system_ops' => [
                'name' => 'system_ops',
                'title' => 'System Ops',
                'version' => '1.0.0',
                'default_enabled' => 1,
                'can_uninstall' => 0,
                'dependencies' => [],
                'min_role' => self::ROLE_SUPER_ADMIN,
            ],
        ];
    }
    /**
     * @return array<string>
     */
    private static function normalizeDependencies($dependencies): array
    {
        if (!is_array($dependencies)) {
            return [];
        }
        $out = [];
        foreach ($dependencies as $dep) {
            $v = trim((string) $dep);
            if ($v !== '') {
                $out[] = $v;
            }
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    private static function normalizeRole(string $role, string $fallback = self::ROLE_OPERATOR): string
    {
        $r = trim($role);
        if (!in_array($r, [self::ROLE_SUPER_ADMIN, self::ROLE_OPERATOR, self::ROLE_VIEWER], true)) {
            return $fallback;
        }
        return $r;
    }

    private static function roleRank(string $role): int
    {
        $r = self::normalizeRole($role, self::ROLE_VIEWER);
        if ($r === self::ROLE_SUPER_ADMIN) {
            return 3;
        }
        if ($r === self::ROLE_OPERATOR) {
            return 2;
        }
        return 1;
    }

    private static function extensionTableExists(): bool
    {
        try {
            Db::name('extensions')->where('id', 0)->find();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function dependencyTableExists(): bool
    {
        try {
            Db::name('extension_dependencies')->where('id', 0)->find();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function rolePermissionTableExists(): bool
    {
        try {
            Db::name('extension_role_permissions')->where('id', 0)->find();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function logTableExists(): bool
    {
        try {
            Db::name('extension_install_logs')->where('id', 0)->find();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function canManageModules(string $role): bool
    {
        return self::roleRank($role) >= self::roleRank(self::ROLE_OPERATOR);
    }

    /**
     * Scan built-in and extension modules and sync metadata to DB when available.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function scanModules(): array
    {
        $builtIns = self::builtInModules();
        $modules = $builtIns;

        $root = rtrim((string) root_path(), DIRECTORY_SEPARATOR);
        $dir = $root . DIRECTORY_SEPARATOR . 'extensions';
        if (is_dir($dir)) {
            $entries = scandir($dir);
            if (is_array($entries)) {
                foreach ($entries as $entry) {
                    if ($entry === '.' || $entry === '..') {
                        continue;
                    }
                    $metaFile = $dir . DIRECTORY_SEPARATOR . $entry . DIRECTORY_SEPARATOR . 'module.json';
                    if (!is_file($metaFile)) {
                        continue;
                    }
                    $raw = @file_get_contents($metaFile);
                    if (!is_string($raw) || $raw === '') {
                        continue;
                    }
                    $json = json_decode($raw, true);
                    if (!is_array($json)) {
                        continue;
                    }
                    $name = isset($json['name']) ? trim((string) $json['name']) : '';
                    if ($name === '') {
                        $name = trim((string) $entry);
                    }
                    if ($name === '') {
                        continue;
                    }
                    $modules[$name] = [
                        'name' => $name,
                        'title' => (string) ($json['title'] ?? $name),
                        'version' => (string) ($json['version'] ?? '1.0.0'),
                        'default_enabled' => (int) ($json['default_enabled'] ?? 0),
                        'can_uninstall' => (int) ($json['can_uninstall'] ?? 1),
                        'dependencies' => self::normalizeDependencies($json['dependencies'] ?? []),
                        'min_role' => self::normalizeRole((string) ($json['min_role'] ?? self::ROLE_OPERATOR), self::ROLE_OPERATOR),
                    ];
                }
            }
        }

        if (self::extensionTableExists()) {
            foreach ($modules as $name => $meta) {
                $exists = Db::name('extensions')->where('name', $name)->find();
                if ($exists) {
                    Db::name('extensions')->where('name', $name)->update([
                        'title' => (string) $meta['title'],
                        'version' => (string) $meta['version'],
                        'config_json' => json_encode([
                            'can_uninstall' => (int) $meta['can_uninstall'],
                            'dependencies' => self::normalizeDependencies($meta['dependencies'] ?? []),
                            'min_role' => self::normalizeRole((string) ($meta['min_role'] ?? self::ROLE_OPERATOR), self::ROLE_OPERATOR),
                        ], JSON_UNESCAPED_UNICODE),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    continue;
                }
                Db::name('extensions')->insert([
                    'name' => $name,
                    'title' => (string) $meta['title'],
                    'version' => (string) $meta['version'],
                    'is_enabled' => (int) $meta['default_enabled'],
                    'config_json' => json_encode([
                        'can_uninstall' => (int) $meta['can_uninstall'],
                        'dependencies' => self::normalizeDependencies($meta['dependencies'] ?? []),
                        'min_role' => self::normalizeRole((string) ($meta['min_role'] ?? self::ROLE_OPERATOR), self::ROLE_OPERATOR),
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        if (self::dependencyTableExists()) {
            foreach ($modules as $name => $meta) {
                $deps = self::normalizeDependencies($meta['dependencies'] ?? []);
                Db::name('extension_dependencies')->where('extension_name', $name)->delete();
                foreach ($deps as $dep) {
                    Db::name('extension_dependencies')->insert([
                        'extension_name' => $name,
                        'depends_on' => $dep,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        if (self::rolePermissionTableExists()) {
            $roles = [self::ROLE_SUPER_ADMIN, self::ROLE_OPERATOR, self::ROLE_VIEWER];
            foreach ($modules as $name => $meta) {
                $minRole = self::normalizeRole((string) ($meta['min_role'] ?? self::ROLE_OPERATOR), self::ROLE_OPERATOR);
                foreach ($roles as $role) {
                    $allow = self::roleRank($role) >= self::roleRank($minRole) ? 1 : 0;
                    $exists = Db::name('extension_role_permissions')
                        ->where('role', $role)
                        ->where('extension_name', $name)
                        ->find();
                    if (!$exists) {
                        Db::name('extension_role_permissions')->insert([
                            'role' => $role,
                            'extension_name' => $name,
                            'can_view' => $allow,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);
                    }
                }
            }
        }

        $rows = [];
        foreach ($modules as $meta) {
            $meta['is_enabled'] = (int) $meta['default_enabled'];
            $meta['dependencies'] = self::normalizeDependencies($meta['dependencies'] ?? []);
            $meta['min_role'] = self::normalizeRole((string) ($meta['min_role'] ?? self::ROLE_OPERATOR), self::ROLE_OPERATOR);
            $rows[] = $meta;
        }

        if (self::extensionTableExists()) {
            $dbRows = Db::name('extensions')->order('id asc')->select()->toArray();
            foreach ($dbRows as &$row) {
                $cfg = [];
                if (!empty($row['config_json'])) {
                    $tmp = json_decode((string) $row['config_json'], true);
                    if (is_array($tmp)) {
                        $cfg = $tmp;
                    }
                }
                $row['can_uninstall'] = (int) ($cfg['can_uninstall'] ?? (isset($builtIns[$row['name']]) ? 0 : 1));
                $row['dependencies'] = self::normalizeDependencies($cfg['dependencies'] ?? []);
                if (self::dependencyTableExists()) {
                    $deps = Db::name('extension_dependencies')
                        ->where('extension_name', (string) $row['name'])
                        ->column('depends_on');
                    $row['dependencies'] = self::normalizeDependencies($deps);
                }
                $row['min_role'] = self::normalizeRole((string) ($cfg['min_role'] ?? ($builtIns[$row['name']]['min_role'] ?? self::ROLE_OPERATOR)), self::ROLE_OPERATOR);
            }
            return $dbRows;
        }

        return $rows;
    }

    private static function runScriptIfExists(string $name, string $action): void
    {
        $root = rtrim((string) root_path(), DIRECTORY_SEPARATOR);
        $phpFile = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'extensions'
            . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $action . '.php';
        if (is_file($phpFile)) {
            $runner = include $phpFile;
            if (is_callable($runner)) {
                $runner();
            }
            return;
        }

        $sqlFile = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'extensions'
            . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $action . '.sql';
        if (is_file($sqlFile)) {
            $sql = (string) @file_get_contents($sqlFile);
            if ($sql !== '') {
                $parts = preg_split('/;\s*[\r\n]+/', $sql) ?: [];
                foreach ($parts as $stmt) {
                    $stmt = trim((string) $stmt);
                    if ($stmt !== '') {
                        Db::execute($stmt);
                    }
                }
            }
        }
    }

    /**
     * @return array<string>
     */
    private static function dependenciesFor(string $name): array
    {
        if (self::dependencyTableExists()) {
            $deps = Db::name('extension_dependencies')->where('extension_name', $name)->column('depends_on');
            return self::normalizeDependencies($deps);
        }
        $rows = self::scanModules();
        foreach ($rows as $r) {
            if ((string) $r['name'] === $name) {
                return self::normalizeDependencies($r['dependencies'] ?? []);
            }
        }
        return [];
    }

    /**
     * @return array<string>
     */
    private static function enabledModuleNames(): array
    {
        $rows = self::scanModules();
        $out = [];
        foreach ($rows as $r) {
            if ((int) ($r['is_enabled'] ?? 0) === 1) {
                $out[] = (string) $r['name'];
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @return array<string>
     */
    private static function enabledDependentsOf(string $name): array
    {
        $all = self::scanModules();
        $enabled = self::enabledModuleNames();
        $enabledMap = array_fill_keys($enabled, 1);
        $dependents = [];
        foreach ($all as $row) {
            $moduleName = (string) ($row['name'] ?? '');
            if ($moduleName === '' || !isset($enabledMap[$moduleName])) {
                continue;
            }
            $deps = self::dependenciesFor($moduleName);
            if (in_array($name, $deps, true)) {
                $dependents[] = $moduleName;
            }
        }
        return array_values(array_unique($dependents));
    }

    /**
     * @return array{ok:bool,message:string,missing?:array<string>}
     */
    private static function checkDependenciesReady(string $name): array
    {
        $deps = self::dependenciesFor($name);
        if (!$deps) {
            return ['ok' => true, 'message' => 'ok'];
        }
        $enabled = self::enabledModuleNames();
        $enabledMap = array_fill_keys($enabled, 1);
        $missing = [];
        foreach ($deps as $dep) {
            if (!isset($enabledMap[$dep])) {
                $missing[] = $dep;
            }
        }
        if ($missing) {
            return [
                'ok' => false,
                'message' => 'missing dependencies: ' . implode(', ', $missing),
                'missing' => $missing,
            ];
        }
        return ['ok' => true, 'message' => 'ok'];
    }

    /**
     * @return array{ok:bool,message:string,dependents?:array<string>}
     */
    private static function checkNoEnabledDependents(string $name): array
    {
        $dependents = self::enabledDependentsOf($name);
        if (!$dependents) {
            return ['ok' => true, 'message' => 'ok'];
        }
        return [
            'ok' => false,
            'message' => 'enabled dependent modules: ' . implode(', ', $dependents),
            'dependents' => $dependents,
        ];
    }

    private static function writeLog(string $extensionName, string $action, bool $ok, string $message = '', array $detail = []): void
    {
        if (!self::logTableExists()) {
            return;
        }
        try {
            Db::name('extension_install_logs')->insert([
                'extension_name' => $extensionName,
                'action' => $action,
                'operator_id' => AdminAuthService::userId() > 0 ? AdminAuthService::userId() : null,
                'operator_name' => AdminAuthService::username() !== '' ? AdminAuthService::username() : null,
                'result' => $ok ? 1 : 0,
                'message' => $message !== '' ? $message : null,
                'detail_json' => $detail ? json_encode($detail, JSON_UNESCAPED_UNICODE) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // ignore log failure
        }
    }

    public static function install(string $name): array
    {
        $role = AdminAuthService::role();
        if (!self::canManageModules($role)) {
            return ['ok' => false, 'message' => 'permission denied'];
        }
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'message' => 'module name is required'];
        }
        self::scanModules();
        if (!self::extensionTableExists()) {
            return ['ok' => false, 'message' => 'extensions table not found'];
        }
        $row = Db::name('extensions')->where('name', $name)->find();
        if (!$row) {
            return ['ok' => false, 'message' => 'module not found'];
        }

        $depCheck = self::checkDependenciesReady($name);
        if (!($depCheck['ok'] ?? false)) {
            self::writeLog($name, 'install', false, (string) ($depCheck['message'] ?? 'dependency failed'), $depCheck);
            return ['ok' => false, 'message' => (string) ($depCheck['message'] ?? 'dependency failed')];
        }

        try {
            self::runScriptIfExists($name, 'install');
            Db::name('extensions')->where('name', $name)->update([
                'is_enabled' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            self::writeLog($name, 'install', true, 'installed');
            return ['ok' => true];
        } catch (\Throwable $e) {
            self::writeLog($name, 'install', false, $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public static function uninstall(string $name, bool $purgeData = false): array
    {
        $role = AdminAuthService::role();
        if ($role !== self::ROLE_SUPER_ADMIN) {
            return ['ok' => false, 'message' => 'permission denied'];
        }
        $name = trim($name);
        if ($name === '') {
            return ['ok' => false, 'message' => 'module name is required'];
        }
        self::scanModules();
        if (!self::extensionTableExists()) {
            return ['ok' => false, 'message' => 'extensions table not found'];
        }
        $row = Db::name('extensions')->where('name', $name)->find();
        if (!$row) {
            return ['ok' => false, 'message' => 'module not found'];
        }

        $cfg = [];
        if (!empty($row['config_json'])) {
            $cfg = json_decode((string) $row['config_json'], true) ?: [];
        }
        $canUninstall = (int) ($cfg['can_uninstall'] ?? 1);
        if ($canUninstall !== 1) {
            return ['ok' => false, 'message' => 'core module cannot be uninstalled'];
        }

        $depCheck = self::checkNoEnabledDependents($name);
        if (!($depCheck['ok'] ?? false)) {
            self::writeLog($name, 'uninstall', false, (string) ($depCheck['message'] ?? 'dependent check failed'), $depCheck);
            return ['ok' => false, 'message' => (string) ($depCheck['message'] ?? 'dependent check failed')];
        }

        try {
            if ($purgeData) {
                self::runScriptIfExists($name, 'uninstall');
            }
            Db::name('extensions')->where('name', $name)->update([
                'is_enabled' => 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            self::writeLog($name, 'uninstall', true, $purgeData ? 'uninstalled with purge' : 'uninstalled');
            return ['ok' => true];
        } catch (\Throwable $e) {
            self::writeLog($name, 'uninstall', false, $e->getMessage(), ['purge_data' => $purgeData ? 1 : 0]);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public static function toggle(string $name, int $isEnabled): array
    {
        $role = AdminAuthService::role();
        if (!self::canManageModules($role)) {
            return ['ok' => false, 'message' => 'permission denied'];
        }
        $name = trim($name);
        $isEnabled = $isEnabled === 1 ? 1 : 0;
        if ($name === '') {
            return ['ok' => false, 'message' => 'module name is required'];
        }
        self::scanModules();
        if (!self::extensionTableExists()) {
            return ['ok' => false, 'message' => 'extensions table not found'];
        }
        $row = Db::name('extensions')->where('name', $name)->find();
        if (!$row) {
            return ['ok' => false, 'message' => 'module not found'];
        }

        if ($isEnabled === 1) {
            $depCheck = self::checkDependenciesReady($name);
            if (!($depCheck['ok'] ?? false)) {
                self::writeLog($name, 'toggle', false, (string) ($depCheck['message'] ?? 'dependency failed'), array_merge($depCheck, ['target' => 1]));
                return ['ok' => false, 'message' => (string) ($depCheck['message'] ?? 'dependency failed')];
            }
        } else {
            $depCheck = self::checkNoEnabledDependents($name);
            if (!($depCheck['ok'] ?? false)) {
                self::writeLog($name, 'toggle', false, (string) ($depCheck['message'] ?? 'dependent check failed'), array_merge($depCheck, ['target' => 0]));
                return ['ok' => false, 'message' => (string) ($depCheck['message'] ?? 'dependent check failed')];
            }
        }

        Db::name('extensions')->where('name', $name)->update([
            'is_enabled' => $isEnabled,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        self::writeLog($name, 'toggle', true, 'toggled', ['target' => $isEnabled]);
        return ['ok' => true];
    }

    public static function updateRolePermission(string $role, string $moduleName, int $canView): array
    {
        if (AdminAuthService::role() !== self::ROLE_SUPER_ADMIN) {
            return ['ok' => false, 'message' => 'permission denied'];
        }
        if (!self::rolePermissionTableExists()) {
            return ['ok' => false, 'message' => 'permission table not found'];
        }
        $role = self::normalizeRole($role, '');
        $moduleName = trim($moduleName);
        if ($role === '' || $moduleName === '') {
            return ['ok' => false, 'message' => 'invalid params'];
        }

        $exists = Db::name('extension_role_permissions')
            ->where('role', $role)
            ->where('extension_name', $moduleName)
            ->find();
        if ($exists) {
            Db::name('extension_role_permissions')
                ->where('id', (int) $exists['id'])
                ->update([
                    'can_view' => $canView === 1 ? 1 : 0,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } else {
            Db::name('extension_role_permissions')->insert([
                'role' => $role,
                'extension_name' => $moduleName,
                'can_view' => $canView === 1 ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return ['ok' => true];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function permissionMatrix(): array
    {
        $rows = self::scanModules();
        $permMap = [];
        if (self::rolePermissionTableExists()) {
            $pers = Db::name('extension_role_permissions')->select()->toArray();
            foreach ($pers as $p) {
                $permMap[(string) $p['extension_name'] . '::' . (string) $p['role']] = (int) ($p['can_view'] ?? 0);
            }
        }
        $roles = [self::ROLE_SUPER_ADMIN, self::ROLE_OPERATOR, self::ROLE_VIEWER];
        foreach ($rows as &$row) {
            $moduleName = (string) $row['name'];
            $minRole = self::normalizeRole((string) ($row['min_role'] ?? self::ROLE_OPERATOR), self::ROLE_OPERATOR);
            $permissions = [];
            foreach ($roles as $role) {
                $k = $moduleName . '::' . $role;
                if (array_key_exists($k, $permMap)) {
                    $permissions[$role] = (int) $permMap[$k];
                } else {
                    $permissions[$role] = self::roleRank($role) >= self::roleRank($minRole) ? 1 : 0;
                }
            }
            $row['permissions'] = $permissions;
        }
        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function logs(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));
        if (!self::logTableExists()) {
            return [];
        }
        $rows = Db::name('extension_install_logs')->order('id', 'desc')->limit($limit)->select()->toArray();
        foreach ($rows as &$row) {
            $detail = [];
            if (!empty($row['detail_json'])) {
                $tmp = json_decode((string) $row['detail_json'], true);
                if (is_array($tmp)) {
                    $detail = $tmp;
                }
            }
            $row['detail'] = $detail;
        }
        return $rows;
    }

    private static function canViewByRole(string $moduleName, string $role, string $minRole): bool
    {
        if (self::rolePermissionTableExists()) {
            $row = Db::name('extension_role_permissions')
                ->where('role', $role)
                ->where('extension_name', $moduleName)
                ->find();
            if ($row) {
                return (int) ($row['can_view'] ?? 0) === 1;
            }
        }
        return self::roleRank($role) >= self::roleRank($minRole);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function modulesForCurrentRole(bool $includeDisabled = true): array
    {
        $role = AdminAuthService::role();
        $rows = self::permissionMatrix();
        $out = [];
        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $minRole = self::normalizeRole((string) ($row['min_role'] ?? self::ROLE_OPERATOR), self::ROLE_OPERATOR);
            if (!self::canViewByRole($name, $role, $minRole)) {
                continue;
            }
            if (!$includeDisabled && (int) ($row['is_enabled'] ?? 0) !== 1) {
                continue;
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * @return array<string, int>
     */
    private static function enabledModuleMap(): array
    {
        $map = [];
        $rows = self::modulesForCurrentRole(true);
        foreach ($rows as $row) {
            $map[(string) $row['name']] = (int) ($row['is_enabled'] ?? 0);
        }
        return $map;
    }

    private static function tableExists(string $table): bool
    {
        try {
            Db::name($table)->where('id', 0)->find();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @param array<string, mixed> $where
     */
    private static function safeCount(string $table, array $where = []): int
    {
        if (!self::tableExists($table)) {
            return 0;
        }
        try {
            $query = Db::name($table);
            foreach ($where as $k => $v) {
                if (is_array($v) && count($v) === 2) {
                    $query->where((string) $k, (string) $v[0], $v[1]);
                } else {
                    $query->where((string) $k, $v);
                }
            }
            return (int) $query->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * @return array<string, int>
     */
    private static function menuBadges(): array
    {
        return [
            'influencer_pending' => self::safeCount('influencers', ['status' => 0]),
            'offline_order_pending' => self::safeCount('offline_orders', ['status' => 0]),
            'creator_links_total' => self::safeCount('product_links'),
            'creator_category_total' => self::safeCount('categories', ['type' => 'influencer']),
            'template_total' => self::safeCount('message_templates', ['status' => 1]),
            'outreach_pending' => self::safeCount('influencer_outreach_tasks', ['task_status' => 0]),
            'sample_pending' => self::safeCount('sample_shipments', ['shipment_status' => 0]) + self::safeCount('sample_shipments', ['shipment_status' => 1]),
            'industry_total' => self::safeCount('growth_industry_metrics'),
            'competitor_total' => self::safeCount('growth_competitors'),
            'ad_total' => self::safeCount('growth_ad_creatives'),
            'import_running' => self::safeCount('import_jobs', ['status' => 1]),
            'video_total' => self::safeCount('videos'),
            'product_total' => self::safeCount('products'),
            'ops_error_total' => self::safeCount('download_logs'),
        ];
    }
    /**
     * @param array<int, array<string, mixed>> $items
     */
    private static function menuTreeHasActive(array $items): bool
    {
        foreach ($items as $item) {
            if (!empty($item['hidden'])) {
                continue;
            }
            if (!empty($item['active'])) {
                return true;
            }
            if (!empty($item['children']) && is_array($item['children']) && self::menuTreeHasActive($item['children'])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build enabled sidebar menus for current role and controller/action context.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getEnabledMenus(string $currentController = '', string $currentAction = ''): array
    {
        $currentController = strtolower(trim($currentController));
        $currentAction = strtolower(trim($currentAction));
        $isBatchUploadAction = in_array($currentAction, ['batchupload', 'batch_upload', 'batch-upload'], true);
        $role = AdminAuthService::role();
        $enabled = self::enabledModuleMap();
        $badges = self::menuBadges();
        $operatorOnly = $role === self::ROLE_OPERATOR;

        $isEnabled = static function (string $moduleName, int $fallback = 0) use ($enabled): bool {
            return (int) ($enabled[$moduleName] ?? $fallback) === 1;
        };

        $overviewEnabled = $isEnabled('overview');
        $searchEnabled = $isEnabled('product_search', (int) ($enabled['style_search'] ?? 0));

        $growthEnabled = $isEnabled('growth_hub');
        $industryEnabled = $growthEnabled && $isEnabled('industry_trend', 1);
        $competitorEnabled = $growthEnabled && $isEnabled('competitor_analysis', 1);
        $adInsightEnabled = $growthEnabled && $isEnabled('ad_insight', 1);
        $dataImportEnabled = $growthEnabled && $isEnabled('data_import', 1);

        $creatorEnabled = $isEnabled('creator_crm');
        $creatorCategoryEnabled = $creatorEnabled && $isEnabled('category', 1);
        $creatorInfluencerEnabled = $creatorEnabled && $isEnabled('influencer', 1);
        $creatorOutreachEnabled = $creatorEnabled && $isEnabled('outreach_workspace', 1);
        $creatorSampleEnabled = $creatorEnabled && $isEnabled('sample_management', 1);
        $creatorTemplateEnabled = $creatorEnabled && $isEnabled('message_template', 1);
        $creatorDistributeEnabled = $creatorEnabled && $isEnabled('distribute', 1);

        $materialEnabled = $isEnabled('material_distribution');
        $terminalEnabled = $isEnabled('terminal_devices');
        $systemEnabled = $isEnabled('system_ops');

        $menus = [];

        if (!$operatorOnly && $overviewEnabled) {
            $menus[] = [
                'section_i18n' => 'admin.menu.overview',
                'items' => [
                    [
                        'kind' => 'link',
                        'href' => '/admin.php',
                        'icon' => 'layout-dashboard',
                        'text_i18n' => 'admin.menu.dashboard',
                        'active' => $currentController === 'index',
                    ],
                ],
            ];
        }

        if (!$operatorOnly && $searchEnabled) {
            $menus[] = [
                'section_i18n' => 'admin.menu.groupSearch',
                'items' => [
                    [
                        'kind' => 'link',
                        'href' => '/admin.php/product_search',
                        'icon' => 'camera',
                        'text_i18n' => 'admin.menu.styleSearch',
                        'active' => $currentController === 'productsearch',
                    ],
                    [
                        'kind' => 'link',
                        'href' => '/admin.php/offline_order',
                        'icon' => 'shopping-cart',
                        'text_i18n' => 'admin.menu.offlineOrders',
                        'active' => $currentController === 'offlineorder',
                        'badge' => ($badges['offline_order_pending'] ?? 0) > 0 ? (string) $badges['offline_order_pending'] : '',
                    ],
                ],
            ];
        }

        if (!$operatorOnly && $growthEnabled) {
            $growthChildren = [];
            if ($industryEnabled) {
                $growthChildren[] = [
                    'kind' => 'link',
                    'href' => '/admin.php/industry_trend',
                    'icon' => 'trending-up',
                    'text_i18n' => 'admin.menu.industryTrend',
                    'active' => $currentController === 'industrytrend',
                    'badge' => ($badges['industry_total'] ?? 0) > 0 ? (string) $badges['industry_total'] : '',
                ];
            }
            if ($competitorEnabled) {
                $growthChildren[] = [
                    'kind' => 'link',
                    'href' => '/admin.php/competitor_analysis',
                    'icon' => 'target',
                    'text_i18n' => 'admin.menu.competitorAnalysis',
                    'active' => $currentController === 'competitoranalysis',
                    'badge' => ($badges['competitor_total'] ?? 0) > 0 ? (string) $badges['competitor_total'] : '',
                ];
            }
            if ($adInsightEnabled) {
                $growthChildren[] = [
                    'kind' => 'link',
                    'href' => '/admin.php/ad_insight',
                    'icon' => 'megaphone',
                    'text_i18n' => 'admin.menu.adInsight',
                    'active' => $currentController === 'adinsight',
                    'badge' => ($badges['ad_total'] ?? 0) > 0 ? (string) $badges['ad_total'] : '',
                ];
            }
            if ($dataImportEnabled) {
                $growthChildren[] = [
                    'kind' => 'link',
                    'href' => '/admin.php/data_import',
                    'icon' => 'database',
                    'text_i18n' => 'admin.menu.dataImport',
                    'active' => $currentController === 'dataimport',
                    'badge' => ($badges['import_running'] ?? 0) > 0 ? (string) $badges['import_running'] : '',
                ];
            }

            if ($growthChildren !== []) {
                $menus[] = [
                    'section_i18n' => 'admin.menu.growthHub',
                    'items' => [
                        [
                            'kind' => 'group',
                            'id' => 'growth',
                            'icon' => 'bar-chart-3',
                            'text_i18n' => 'admin.menu.growthHubMenu',
                            'expanded' => self::menuTreeHasActive($growthChildren),
                            'children' => $growthChildren,
                        ],
                    ],
                ];
            }
        }

        if ($creatorEnabled) {
            $creatorChildren = [];
            if ($creatorCategoryEnabled) {
                $creatorChildren[] = [
                    'kind' => 'link',
                    'href' => '/admin.php/category',
                    'icon' => 'tag',
                    'text_i18n' => 'admin.menu.category',
                    'active' => $currentController === 'category',
                    'badge' => ($badges['creator_category_total'] ?? 0) > 0 ? (string) $badges['creator_category_total'] : '',
                ];
            }
            if ($creatorInfluencerEnabled) {
                $creatorChildren[] = [
                    'kind' => 'link',
                    'href' => '/admin.php/influencer',
                    'icon' => 'users',
                    'text_i18n' => 'admin.menu.influencerList',
                    'active' => $currentController === 'influencer',
                    'badge' => ($badges['influencer_pending'] ?? 0) > 0 ? (string) $badges['influencer_pending'] : '',
                ];
            }
            if ($creatorOutreachEnabled) {
                $creatorChildren[] = [
                    'kind' => 'link',
                    'href' => '/admin.php/outreach_workspace',
                    'icon' => 'send',
                    'text_i18n' => 'admin.menu.outreachWorkspace',
                    'active' => $currentController === 'outreachworkspace',
                    'badge' => ($badges['outreach_pending'] ?? 0) > 0 ? (string) $badges['outreach_pending'] : '',
                ];
            }
            if ($creatorSampleEnabled) {
                $creatorChildren[] = [
                    'kind' => 'link',
                    'href' => '/admin.php/sample',
                    'icon' => 'package',
                    'text_i18n' => 'admin.menu.sampleManagement',
                    'active' => $currentController === 'sample',
                    'badge' => ($badges['sample_pending'] ?? 0) > 0 ? (string) $badges['sample_pending'] : '',
                ];
            }
            if ($creatorTemplateEnabled) {
                $creatorChildren[] = [
                    'kind' => 'link',
                    'href' => '/admin.php/message_template',
                    'icon' => 'message-circle',
                    'text_i18n' => 'admin.menu.messageTemplates',
                    'active' => $currentController === 'messagetemplate',
                    'badge' => ($badges['template_total'] ?? 0) > 0 ? (string) $badges['template_total'] : '',
                ];
            }
            if ($creatorDistributeEnabled) {
                $creatorChildren[] = [
                    'kind' => 'link',
                    'href' => '/admin.php/distribute',
                    'icon' => 'link-2',
                    'text_i18n' => 'admin.menu.distribute',
                    'active' => $currentController === 'distribute',
                    'badge' => ($badges['creator_links_total'] ?? 0) > 0 ? (string) $badges['creator_links_total'] : '',
                ];
            }

            if ($creatorChildren !== []) {
                $menus[] = [
                    'section_i18n' => 'admin.menu.groupCreator',
                    'items' => [
                        [
                            'kind' => 'group',
                            'id' => 'creator',
                            'icon' => 'users',
                            'text_i18n' => 'admin.menu.groupCreatorMenu',
                            'expanded' => self::menuTreeHasActive($creatorChildren),
                            'children' => $creatorChildren,
                        ],
                    ],
                ];
            }
        }

        if ($materialEnabled) {
            $materialChildren = [
                [
                    'kind' => 'link',
                    'href' => '/admin.php/video',
                    'icon' => 'video',
                    'text_i18n' => 'admin.menu.video',
                    'active' => $currentController === 'video' && !$isBatchUploadAction,
                    'badge' => ($badges['video_total'] ?? 0) > 0 ? (string) $badges['video_total'] : '',
                ],
                [
                    'kind' => 'link',
                    'href' => '/admin.php/video/batchUpload',
                    'icon' => 'cloud-upload',
                    'text_i18n' => 'admin.menu.upload',
                    'active' => $currentController === 'video' && $isBatchUploadAction,
                ],
                [
                    'kind' => 'link',
                    'href' => '/admin.php/product',
                    'icon' => 'shopping-bag',
                    'text_i18n' => 'admin.menu.contentDistribution',
                    'active' => $currentController === 'product',
                    'badge' => ($badges['product_total'] ?? 0) > 0 ? (string) $badges['product_total'] : '',
                ],
            ];
            $menus[] = [
                'section_i18n' => 'admin.menu.material',
                'items' => [
                    [
                        'kind' => 'group',
                        'id' => 'material',
                        'icon' => 'folder',
                        'text_i18n' => 'admin.menu.materialMenu',
                        'expanded' => self::menuTreeHasActive($materialChildren),
                        'children' => $materialChildren,
                    ],
                ],
            ];
        }

        if (!$operatorOnly && $terminalEnabled) {
            $terminalChildren = [
                [
                    'kind' => 'link',
                    'href' => '/admin.php/platform',
                    'icon' => 'layers',
                    'text_i18n' => 'admin.menu.platform',
                    'active' => $currentController === 'platform',
                ],
                [
                    'kind' => 'link',
                    'href' => '/admin.php/device',
                    'icon' => 'smartphone',
                    'text_i18n' => 'admin.menu.device',
                    'active' => $currentController === 'device',
                ],
            ];
            $menus[] = [
                'section_i18n' => 'admin.menu.terminalSection',
                'items' => [
                    [
                        'kind' => 'group',
                        'id' => 'terminal',
                        'icon' => 'monitor-smartphone',
                        'text_i18n' => 'admin.menu.terminal',
                        'expanded' => self::menuTreeHasActive($terminalChildren),
                        'children' => $terminalChildren,
                    ],
                ],
            ];
        }

        if (!$operatorOnly && $systemEnabled) {
            $opsCenterActive = in_array($currentController, ['opscenter', 'clientlicense', 'clientversion', 'cache'], true);
            $systemChildren = [
                [
                    'kind' => 'link',
                    'href' => '/admin.php/settings',
                    'icon' => 'sliders-horizontal',
                    'text_i18n' => 'admin.menu.settings',
                    'active' => $currentController === 'settings',
                ],
                [
                    'kind' => 'link',
                    'href' => '/admin.php/ops_center',
                    'icon' => 'wrench',
                    'text_i18n' => 'admin.menu.opsCenter',
                    'active' => $opsCenterActive,
                ],
                [
                    'kind' => 'link',
                    'href' => '/admin.php/downloadLog',
                    'icon' => 'triangle-alert',
                    'text_i18n' => 'admin.menu.errors',
                    'active' => $currentController === 'downloadlog',
                    'badge' => ($badges['ops_error_total'] ?? 0) > 0 ? (string) $badges['ops_error_total'] : '',
                ],
                [
                    'kind' => 'link',
                    'href' => '/admin.php/user',
                    'icon' => 'users',
                    'text_i18n' => 'admin.menu.user',
                    'active' => $currentController === 'user',
                ],
                [
                    'kind' => 'link',
                    'href' => '/admin.php/extension',
                    'icon' => 'puzzle',
                    'text_i18n' => 'admin.menu.extensionManager',
                    'active' => $currentController === 'extension',
                    'hidden' => !self::canManageModules($role),
                ],
                [
                    'kind' => 'link',
                    'href' => '/admin.php/auth/logout',
                    'icon' => 'log-out',
                    'text_i18n' => 'admin.menu.logout',
                    'active' => false,
                ],
            ];
            $systemChildren = array_values(array_filter($systemChildren, static function ($item) {
                return empty($item['hidden']);
            }));
            $menus[] = [
                'section_i18n' => 'admin.menu.system',
                'items' => [
                    [
                        'kind' => 'group',
                        'id' => 'system',
                        'icon' => 'settings',
                        'text_i18n' => 'admin.menu.system',
                        'expanded' => self::menuTreeHasActive($systemChildren),
                        'children' => $systemChildren,
                    ],
                ],
            ];
        }

        return $menus;
    }
}
