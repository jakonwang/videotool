<?php
/**
 * Tenant SaaS full suite migration.
 *
 * Windows: php database\run_migration_tenant_saas_suite.php
 * Linux:   php database/run_migration_tenant_saas_suite.php
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Please run this script in CLI.\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (!function_exists('env')) {
    function env(?string $name = null, $default = null)
    {
        return $default;
    }
}

$config = require $root . '/config/database.php';
$mysql = $config['connections']['mysql'] ?? null;
if (!$mysql) {
    fwrite(STDERR, "Missing mysql connection config.\n");
    exit(1);
}

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $mysql['hostname'],
    $mysql['hostport'],
    $mysql['database'],
    $mysql['charset'] ?? 'utf8mb4'
);

try {
    $pdo = new PDO($dsn, $mysql['username'], $mysql['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    fwrite(STDERR, 'DB connect failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$pdo->exec('SET NAMES utf8mb4');
$dbName = (string) $mysql['database'];

$hasTable = static function (string $table) use ($pdo, $dbName): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $st->execute([$dbName, $table]);
    return (int) $st->fetchColumn() > 0;
};

$hasColumn = static function (string $table, string $column) use ($pdo, $dbName): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$dbName, $table, $column]);
    return (int) $st->fetchColumn() > 0;
};

$hasIndex = static function (string $table, string $index) use ($pdo, $dbName): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $st->execute([$dbName, $table, $index]);
    return (int) $st->fetchColumn() > 0;
};

$addTenantColumn = static function (string $table, string $after) use ($pdo, $hasTable, $hasColumn, $hasIndex): void {
    if (!$hasTable($table)) {
        echo "  - {$table} not found, skip\n";
        return;
    }
    if (!$hasColumn($table, 'tenant_id')) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `{$after}`");
        echo "  - {$table}.tenant_id added\n";
    } else {
        echo "  - {$table}.tenant_id exists\n";
    }

    $pdo->exec("UPDATE `{$table}` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL OR `tenant_id` <= 0");

    if (!$hasIndex($table, 'idx_tenant_id')) {
        $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `idx_tenant_id` (`tenant_id`)");
        echo "    + {$table}.idx_tenant_id added\n";
    }
};

echo "[1/8] Ensure tenants table...\n";
if (!$hasTable('tenants')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `tenants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_name` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `remark` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_code` (`tenant_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant registry';
SQL);
    echo "  - tenants created\n";
} else {
    echo "  - tenants exists\n";
    if (!$hasColumn('tenants', 'remark')) {
        $pdo->exec("ALTER TABLE `tenants` ADD COLUMN `remark` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `status`");
        echo "  - tenants.remark added\n";
    }
}

$tenantExists = (int) $pdo->query("SELECT COUNT(*) FROM `tenants` WHERE `id` = 1")->fetchColumn();
if ($tenantExists <= 0) {
    $pdo->exec("INSERT INTO `tenants` (`id`,`tenant_code`,`tenant_name`,`status`,`remark`) VALUES (1,'default','Default Tenant',1,'Seeded by migration')");
    echo "  - tenant #1 seeded\n";
}

echo "[2/8] Backfill tenant_id across business tables...\n";
$tenantTables = [
    ['admin_users', 'id'],
    ['categories', 'id'],
    ['products', 'id'],
    ['product_links', 'id'],
    ['videos', 'id'],
    ['influencers', 'id'],
    ['message_templates', 'id'],
    ['outreach_logs', 'id'],
    ['influencer_status_logs', 'id'],
    ['influencer_outreach_tasks', 'id'],
    ['sample_shipments', 'id'],
    ['offline_orders', 'id'],
    ['product_style_items', 'id'],
    ['product_style_search', 'id'],
    ['product_style_is_queue', 'id'],
    ['product_style_import_tasks', 'id'],
    ['influencer_import_tasks', 'id'],
    ['growth_industry_metrics', 'id'],
    ['growth_competitors', 'id'],
    ['growth_competitor_metrics', 'id'],
    ['growth_ad_creatives', 'id'],
    ['growth_ad_metrics', 'id'],
    ['data_sources', 'id'],
    ['import_jobs', 'id'],
    ['import_job_logs', 'id'],
    ['growth_profit_stores', 'id'],
    ['growth_profit_accounts', 'id'],
    ['growth_profit_daily_entries', 'id'],
    ['growth_fx_rates', 'id'],
    ['growth_store_product_catalog', 'id'],
    ['growth_live_sessions', 'id'],
    ['growth_live_product_metrics', 'id'],
    ['growth_live_style_agg', 'id'],
    ['auto_dm_campaigns', 'id'],
    ['auto_dm_tasks', 'id'],
    ['auto_dm_events', 'id'],
    ['auto_dm_reply_reviews', 'id'],
    ['contact_policies', 'id'],
    ['mobile_devices', 'id'],
    ['mobile_action_tasks', 'id'],
    ['mobile_action_logs', 'id'],
    ['platforms', 'id'],
    ['devices', 'id'],
    ['app_licenses', 'id'],
    ['app_versions', 'id'],
    ['download_logs', 'id'],
];

foreach ($tenantTables as [$table, $after]) {
    $addTenantColumn($table, $after);
}

echo "[3/8] Ensure tenant module effective table...\n";
if (!$hasTable('tenant_module_subscriptions')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `tenant_module_subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `module_name` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_module` (`tenant_id`,`module_name`),
  KEY `idx_tenant_enabled` (`tenant_id`,`is_enabled`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant module effective subscriptions';
SQL);
    echo "  - tenant_module_subscriptions created\n";
} else {
    echo "  - tenant_module_subscriptions exists\n";
    if (!$hasColumn('tenant_module_subscriptions', 'expires_at')) {
        $pdo->exec("ALTER TABLE `tenant_module_subscriptions` ADD COLUMN `expires_at` DATETIME DEFAULT NULL AFTER `is_enabled`");
        echo "  - tenant_module_subscriptions.expires_at added\n";
    }
}

echo "[4/8] Ensure tenant_packages table...\n";
if (!$hasTable('tenant_packages')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `tenant_packages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `package_name` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_package_code` (`package_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant package definitions';
SQL);
    echo "  - tenant_packages created\n";
} else {
    echo "  - tenant_packages exists\n";
}

echo "[5/8] Ensure tenant_package_modules table...\n";
if (!$hasTable('tenant_package_modules')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `tenant_package_modules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `package_id` INT UNSIGNED NOT NULL,
  `module_name` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_optional` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `default_enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_package_module` (`package_id`,`module_name`),
  KEY `idx_module_name` (`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Modules in package';
SQL);
    echo "  - tenant_package_modules created\n";
} else {
    echo "  - tenant_package_modules exists\n";
}

echo "[6/8] Ensure tenant_subscriptions table...\n";
if (!$hasTable('tenant_subscriptions')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `tenant_subscriptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `package_id` INT UNSIGNED NOT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `expires_at` DATETIME DEFAULT NULL,
  `addon_modules_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disabled_modules_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_subscription` (`tenant_id`),
  KEY `idx_package_status` (`package_id`,`status`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant package subscriptions';
SQL);
    echo "  - tenant_subscriptions created\n";
} else {
    echo "  - tenant_subscriptions exists\n";
}

echo "[7/8] Seed default package/subscription/effective modules...\n";
$moduleNames = [];
if ($hasTable('extensions')) {
    $rows = $pdo->query("SELECT `name` FROM `extensions` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
        $name = trim((string) ($row['name'] ?? ''));
        if ($name !== '') {
            $moduleNames[$name] = true;
        }
    }
}
if ($moduleNames === []) {
    foreach (['overview','product_search','growth_hub','industry_trend','competitor_analysis','ad_insight','data_import','profit_center','creator_crm','category','influencer','outreach_workspace','auto_dm_campaign','sample_management','message_template','distribute','material_distribution','terminal_devices','system_ops','platform_ops'] as $fallbackModule) {
        $moduleNames[$fallbackModule] = true;
    }
}
$moduleList = array_keys($moduleNames);
sort($moduleList);

$defaultPackageCode = 'default_full';
$defaultPackageName = 'Default Full Package';
$pkgStmt = $pdo->prepare("SELECT `id` FROM `tenant_packages` WHERE `package_code` = ? LIMIT 1");
$pkgStmt->execute([$defaultPackageCode]);
$defaultPackageId = (int) $pkgStmt->fetchColumn();
if ($defaultPackageId <= 0) {
    $insPkg = $pdo->prepare("INSERT INTO `tenant_packages` (`package_code`,`package_name`,`description`,`status`) VALUES (?, ?, ?, 1)");
    $insPkg->execute([$defaultPackageCode, $defaultPackageName, 'Seeded full package']);
    $defaultPackageId = (int) $pdo->lastInsertId();
    echo "  - tenant_packages seeded: {$defaultPackageCode}\n";
}

$upPkgMod = $pdo->prepare("INSERT INTO `tenant_package_modules` (`package_id`,`module_name`,`is_optional`,`default_enabled`) VALUES (?, ?, 0, 1) ON DUPLICATE KEY UPDATE `default_enabled` = VALUES(`default_enabled`), `updated_at` = CURRENT_TIMESTAMP");
foreach ($moduleList as $moduleName) {
    $upPkgMod->execute([$defaultPackageId, $moduleName]);
}

$tenantIds = [1];
if ($hasTable('tenants')) {
    $tenantRows = $pdo->query("SELECT `id` FROM `tenants` ORDER BY `id` ASC")->fetchAll(PDO::FETCH_ASSOC);
    $tenantIds = [];
    foreach ($tenantRows as $row) {
        $tid = (int) ($row['id'] ?? 0);
        if ($tid > 0) {
            $tenantIds[$tid] = true;
        }
    }
    $tenantIds = array_keys($tenantIds);
    if ($tenantIds === []) {
        $tenantIds = [1];
    }
}

$selSub = $pdo->prepare("SELECT `id`, `expires_at`, `addon_modules_json`, `disabled_modules_json`, `status` FROM `tenant_subscriptions` WHERE `tenant_id` = ? LIMIT 1");
$insSub = $pdo->prepare("INSERT INTO `tenant_subscriptions` (`tenant_id`,`package_id`,`status`,`expires_at`,`addon_modules_json`,`disabled_modules_json`) VALUES (?, ?, 1, NULL, '[]', '[]')");
$upEff = $pdo->prepare("INSERT INTO `tenant_module_subscriptions` (`tenant_id`,`module_name`,`is_enabled`,`expires_at`) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `is_enabled` = VALUES(`is_enabled`), `expires_at` = VALUES(`expires_at`), `updated_at` = CURRENT_TIMESTAMP");

foreach ($tenantIds as $tenantId) {
    $tenantId = (int) $tenantId;
    if ($tenantId <= 0) {
        continue;
    }

    $selSub->execute([$tenantId]);
    $sub = $selSub->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$sub) {
        $insSub->execute([$tenantId, $defaultPackageId]);
        $expiresAt = null;
        $addonModules = [];
        $disabledModules = [];
        $subStatus = 1;
    } else {
        $expiresAt = !empty($sub['expires_at']) ? (string) $sub['expires_at'] : null;
        $addonModules = json_decode((string) ($sub['addon_modules_json'] ?? '[]'), true);
        if (!is_array($addonModules)) {
            $addonModules = [];
        }
        $disabledModules = json_decode((string) ($sub['disabled_modules_json'] ?? '[]'), true);
        if (!is_array($disabledModules)) {
            $disabledModules = [];
        }
        $subStatus = (int) ($sub['status'] ?? 1) === 1 ? 1 : 0;
    }

    $effective = [];
    if ($subStatus === 1) {
        foreach ($moduleList as $moduleName) {
            $effective[$moduleName] = true;
        }
        foreach ($disabledModules as $disabledName) {
            $disabledName = trim((string) $disabledName);
            if ($disabledName !== '') {
                unset($effective[$disabledName]);
            }
        }
        foreach ($addonModules as $addonName) {
            $addonName = trim((string) $addonName);
            if ($addonName !== '') {
                $effective[$addonName] = true;
            }
        }
    }

    foreach ($moduleList as $moduleName) {
        $enabled = isset($effective[$moduleName]) ? 1 : 0;
        $upEff->execute([$tenantId, $moduleName, $enabled, $expiresAt]);
    }
}

echo "[8/8] Integrity summary...\n";
$tenantCount = $hasTable('tenants') ? (int) $pdo->query("SELECT COUNT(*) FROM `tenants`")->fetchColumn() : 0;
$packageCount = $hasTable('tenant_packages') ? (int) $pdo->query("SELECT COUNT(*) FROM `tenant_packages`")->fetchColumn() : 0;
$subCount = $hasTable('tenant_subscriptions') ? (int) $pdo->query("SELECT COUNT(*) FROM `tenant_subscriptions`")->fetchColumn() : 0;
$effCount = $hasTable('tenant_module_subscriptions') ? (int) $pdo->query("SELECT COUNT(*) FROM `tenant_module_subscriptions`")->fetchColumn() : 0;

echo "  - tenants: {$tenantCount}\n";
echo "  - tenant_packages: {$packageCount}\n";
echo "  - tenant_subscriptions: {$subCount}\n";
echo "  - tenant_module_subscriptions: {$effCount}\n";
echo "Done.\n";
