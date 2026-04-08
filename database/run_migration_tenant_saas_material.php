<?php
/**
 * Tenant + material deep optimization migration.
 *
 * Windows: php database\run_migration_tenant_saas_material.php
 * Linux:   php database/run_migration_tenant_saas_material.php
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

echo "[1/7] Create tenants table...\n";
if (!$hasTable('tenants')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `tenants` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_name` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
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
}
$tenantExists = (int) $pdo->query("SELECT COUNT(*) FROM `tenants` WHERE `id` = 1")->fetchColumn();
if ($tenantExists <= 0) {
    $pdo->exec("INSERT INTO `tenants` (`id`,`tenant_code`,`tenant_name`,`status`) VALUES (1,'default','Default Tenant',1)");
    echo "  - tenants#1 seeded\n";
}

echo "[2/7] Add tenant_id to business tables...\n";
$tables = [
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
];
foreach ($tables as [$table, $after]) {
    $addTenantColumn($table, $after);
}

echo "[3/7] Create tenant module subscriptions...\n";
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tenant module subscription';
SQL);
    echo "  - tenant_module_subscriptions created\n";
} else {
    echo "  - tenant_module_subscriptions exists\n";
}
if ($hasTable('extensions') && $hasTable('tenant_module_subscriptions')) {
    $seeded = (int) $pdo->query("SELECT COUNT(*) FROM `tenant_module_subscriptions` WHERE `tenant_id` = 1")->fetchColumn();
    if ($seeded <= 0) {
        $rows = $pdo->query("SELECT `name`, `is_enabled` FROM `extensions`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $enabled = (int) ($row['is_enabled'] ?? 1) === 1 ? 1 : 0;
            $st = $pdo->prepare("INSERT INTO `tenant_module_subscriptions` (`tenant_id`,`module_name`,`is_enabled`) VALUES (1, ?, ?)");
            $st->execute([$name, $enabled]);
        }
        echo "  - tenant_module_subscriptions seeded from extensions\n";
    }
}

echo "[4/7] Create admin audit logs table...\n";
if (!$hasTable('admin_logs')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `admin_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `admin_user_id` INT UNSIGNED DEFAULT NULL,
  `admin_username` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `admin_role` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `action` VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_table` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_id` BIGINT DEFAULT NULL,
  `request_path` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_method` VARCHAR(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hardware_fingerprint` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fingerprint_hash` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_admin_created` (`admin_user_id`,`created_at`),
  KEY `idx_action_created` (`action`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Admin audit logs';
SQL);
    echo "  - admin_logs created\n";
} else {
    echo "  - admin_logs exists\n";
}

echo "[5/7] Material columns and indexes...\n";
if ($hasTable('videos')) {
    if (!$hasColumn('videos', 'video_md5')) {
        $pdo->exec("ALTER TABLE `videos` ADD COLUMN `video_md5` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `video_url`");
        echo "  - videos.video_md5 added\n";
    } else {
        echo "  - videos.video_md5 exists\n";
    }
    if (!$hasColumn('videos', 'ad_creative_code')) {
        $pdo->exec("ALTER TABLE `videos` ADD COLUMN `ad_creative_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `video_md5`");
        echo "  - videos.ad_creative_code added\n";
    } else {
        echo "  - videos.ad_creative_code exists\n";
    }
    if (!$hasIndex('videos', 'idx_tenant_video_md5')) {
        $pdo->exec("ALTER TABLE `videos` ADD INDEX `idx_tenant_video_md5` (`tenant_id`, `video_md5`)");
        echo "  - videos.idx_tenant_video_md5 added\n";
    }
    if (!$hasIndex('videos', 'idx_ad_creative_code')) {
        $pdo->exec("ALTER TABLE `videos` ADD INDEX `idx_ad_creative_code` (`ad_creative_code`)");
        echo "  - videos.idx_ad_creative_code added\n";
    }
}

echo "[6/7] Add est_gmv to growth ad metrics...\n";
if ($hasTable('growth_ad_metrics')) {
    if (!$hasColumn('growth_ad_metrics', 'est_gmv')) {
        $pdo->exec("ALTER TABLE `growth_ad_metrics` ADD COLUMN `est_gmv` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `est_spend`");
        echo "  - growth_ad_metrics.est_gmv added\n";
    } else {
        echo "  - growth_ad_metrics.est_gmv exists\n";
    }
    if (!$hasIndex('growth_ad_metrics', 'idx_est_gmv')) {
        $pdo->exec("ALTER TABLE `growth_ad_metrics` ADD INDEX `idx_est_gmv` (`est_gmv`)");
        echo "  - growth_ad_metrics.idx_est_gmv added\n";
    }
}

echo "[7/7] Normalize admin_users tenant defaults...\n";
if ($hasTable('admin_users')) {
    if (!$hasColumn('admin_users', 'tenant_id')) {
        $pdo->exec("ALTER TABLE `admin_users` ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`");
        echo "  - admin_users.tenant_id added\n";
    }
    $pdo->exec("UPDATE `admin_users` SET `tenant_id` = 1 WHERE `tenant_id` IS NULL OR `tenant_id` <= 0");
    if (!$hasIndex('admin_users', 'idx_tenant_id')) {
        $pdo->exec("ALTER TABLE `admin_users` ADD INDEX `idx_tenant_id` (`tenant_id`)");
        echo "  - admin_users.idx_tenant_id added\n";
    }
}

echo "Done.\n";
