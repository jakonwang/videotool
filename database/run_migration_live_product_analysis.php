<?php
/**
 * Live product analysis migration (store catalog + live session metrics).
 *
 * Windows: php database\run_migration_live_product_analysis.php
 * Linux:   php database/run_migration_live_product_analysis.php
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

echo "[1/4] growth_store_product_catalog ...\n";
if (!$hasTable('growth_store_product_catalog')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_store_product_catalog` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `store_id` INT UNSIGNED NOT NULL,
  `style_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `notes` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_store_style` (`tenant_id`, `store_id`, `style_code`),
  KEY `idx_tenant_store_status` (`tenant_id`, `store_id`, `status`),
  KEY `idx_tenant_style` (`tenant_id`, `style_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Store product catalog for live analytics';
SQL);
    echo "  - created\n";
} else {
    echo "  - exists\n";
}
if ($hasTable('growth_store_product_catalog') && !$hasColumn('growth_store_product_catalog', 'tenant_id')) {
    $pdo->exec("ALTER TABLE `growth_store_product_catalog` ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`");
    echo "  - tenant_id added\n";
}
if ($hasTable('growth_store_product_catalog') && !$hasIndex('growth_store_product_catalog', 'idx_tenant_store_status')) {
    $pdo->exec("ALTER TABLE `growth_store_product_catalog` ADD INDEX `idx_tenant_store_status` (`tenant_id`, `store_id`, `status`)");
    echo "  - idx_tenant_store_status added\n";
}

echo "[2/4] growth_live_sessions ...\n";
if (!$hasTable('growth_live_sessions')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_live_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `store_id` INT UNSIGNED NOT NULL,
  `session_date` DATE NOT NULL,
  `session_name` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `source_file` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_hash` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `import_job_id` BIGINT UNSIGNED DEFAULT NULL,
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `matched_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `unmatched_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_store_session` (`tenant_id`, `store_id`, `session_date`, `session_name`),
  KEY `idx_tenant_store_date` (`tenant_id`, `store_id`, `session_date`),
  KEY `idx_tenant_job` (`tenant_id`, `import_job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Live session metadata';
SQL);
    echo "  - created\n";
} else {
    echo "  - exists\n";
}
if ($hasTable('growth_live_sessions') && !$hasColumn('growth_live_sessions', 'tenant_id')) {
    $pdo->exec("ALTER TABLE `growth_live_sessions` ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`");
    echo "  - tenant_id added\n";
}
if ($hasTable('growth_live_sessions') && !$hasIndex('growth_live_sessions', 'idx_tenant_store_date')) {
    $pdo->exec("ALTER TABLE `growth_live_sessions` ADD INDEX `idx_tenant_store_date` (`tenant_id`, `store_id`, `session_date`)");
    echo "  - idx_tenant_store_date added\n";
}

echo "[3/4] growth_live_product_metrics ...\n";
if (!$hasTable('growth_live_product_metrics')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_live_product_metrics` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `store_id` INT UNSIGNED NOT NULL,
  `session_date` DATE NOT NULL,
  `session_name` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `product_id` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `extracted_style_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `catalog_id` BIGINT UNSIGNED DEFAULT NULL,
  `catalog_style_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `image_url` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_matched` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `gmv` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `items_sold` INT UNSIGNED NOT NULL DEFAULT 0,
  `customers` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_sku_orders` INT UNSIGNED NOT NULL DEFAULT 0,
  `sku_orders` INT UNSIGNED NOT NULL DEFAULT 0,
  `orders_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `impressions` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `clicks` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `add_to_cart_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `payment_rate` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `ctr` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `add_to_cart_rate` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `pay_cvr` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `ctor_sku` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `ctor` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `watch_gpm` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `aov` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `available_stock` INT NOT NULL DEFAULT 0,
  `raw_payload_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_store_session_product` (`tenant_id`, `store_id`, `session_date`, `session_name`, `product_id`),
  KEY `idx_tenant_session` (`tenant_id`, `session_id`),
  KEY `idx_tenant_store_matched` (`tenant_id`, `store_id`, `is_matched`),
  KEY `idx_tenant_style_date` (`tenant_id`, `catalog_style_code`, `session_date`),
  KEY `idx_tenant_store_date` (`tenant_id`, `store_id`, `session_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Live product metrics per session';
SQL);
    echo "  - created\n";
} else {
    echo "  - exists\n";
}
if ($hasTable('growth_live_product_metrics') && !$hasColumn('growth_live_product_metrics', 'tenant_id')) {
    $pdo->exec("ALTER TABLE `growth_live_product_metrics` ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`");
    echo "  - tenant_id added\n";
}
if ($hasTable('growth_live_product_metrics') && !$hasIndex('growth_live_product_metrics', 'idx_tenant_store_matched')) {
    $pdo->exec("ALTER TABLE `growth_live_product_metrics` ADD INDEX `idx_tenant_store_matched` (`tenant_id`, `store_id`, `is_matched`)");
    echo "  - idx_tenant_store_matched added\n";
}

echo "[4/4] growth_live_style_agg ...\n";
if (!$hasTable('growth_live_style_agg')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_live_style_agg` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `scope` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'store',
  `store_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `window_type` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'session',
  `window_start` DATE DEFAULT NULL,
  `window_end` DATE DEFAULT NULL,
  `anchor_session_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `style_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `product_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `session_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `gmv_sum` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
  `impressions_sum` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `clicks_sum` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `add_to_cart_sum` INT UNSIGNED NOT NULL DEFAULT 0,
  `orders_sum` INT UNSIGNED NOT NULL DEFAULT 0,
  `ctr` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `add_to_cart_rate` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `pay_cvr` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `score` DECIMAL(10,6) NOT NULL DEFAULT 0.000000,
  `tier` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ranking` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_scope_window_style_anchor` (`tenant_id`, `scope`, `store_id`, `window_type`, `window_end`, `anchor_session_id`, `style_code`),
  KEY `idx_tenant_scope_window_rank` (`tenant_id`, `scope`, `store_id`, `window_type`, `window_end`, `ranking`),
  KEY `idx_tenant_style` (`tenant_id`, `style_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='Aggregated live style ranking snapshots';
SQL);
    echo "  - created\n";
} else {
    echo "  - exists\n";
}
if ($hasTable('growth_live_style_agg') && !$hasColumn('growth_live_style_agg', 'tenant_id')) {
    $pdo->exec("ALTER TABLE `growth_live_style_agg` ADD COLUMN `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1 AFTER `id`");
    echo "  - tenant_id added\n";
}
if ($hasTable('growth_live_style_agg') && !$hasIndex('growth_live_style_agg', 'idx_tenant_scope_window_rank')) {
    $pdo->exec("ALTER TABLE `growth_live_style_agg` ADD INDEX `idx_tenant_scope_window_rank` (`tenant_id`, `scope`, `store_id`, `window_type`, `window_end`, `ranking`)");
    echo "  - idx_tenant_scope_window_rank added\n";
}

echo "Done.\n";
