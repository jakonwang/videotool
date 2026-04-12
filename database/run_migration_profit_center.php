<?php
/**
 * Profit Center migration (multi-store + multi-currency).
 *
 * Windows: php database\run_migration_profit_center.php
 * Linux:   php database/run_migration_profit_center.php
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

echo "[1/4] growth_profit_stores ...\n";
if (!$hasTable('growth_profit_stores')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_profit_stores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `store_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `store_name` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `default_sale_price_cny` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `default_product_cost_cny` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `default_cancel_rate_live` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
  `default_cancel_rate_video` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
  `default_cancel_rate_influencer` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
  `default_cancel_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
  `default_platform_fee_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
  `default_influencer_commission_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
  `default_live_wage_hourly_cny` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `default_timezone` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Asia/Bangkok',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `notes` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_store_code` (`tenant_id`, `store_code`),
  KEY `idx_tenant_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profit center stores';
SQL);
    echo "  - growth_profit_stores created\n";
} else {
    echo "  - growth_profit_stores exists\n";
}
if ($hasTable('growth_profit_stores') && !$hasIndex('growth_profit_stores', 'idx_tenant_status')) {
    $pdo->exec("ALTER TABLE `growth_profit_stores` ADD INDEX `idx_tenant_status` (`tenant_id`, `status`)");
    echo "  - growth_profit_stores.idx_tenant_status added\n";
}

echo "[2/4] growth_profit_accounts ...\n";
if (!$hasTable('growth_profit_accounts')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_profit_accounts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `store_id` INT UNSIGNED NOT NULL,
  `account_name` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `channel_type` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'video',
  `account_currency` CHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `default_gmv_currency` CHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `notes` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_account_code` (`tenant_id`, `account_code`),
  KEY `idx_tenant_store_status` (`tenant_id`, `store_id`, `status`),
  KEY `idx_tenant_channel` (`tenant_id`, `channel_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profit center accounts';
SQL);
    echo "  - growth_profit_accounts created\n";
} else {
    echo "  - growth_profit_accounts exists\n";
}
if ($hasTable('growth_profit_accounts') && !$hasIndex('growth_profit_accounts', 'idx_tenant_store_status')) {
    $pdo->exec("ALTER TABLE `growth_profit_accounts` ADD INDEX `idx_tenant_store_status` (`tenant_id`, `store_id`, `status`)");
    echo "  - growth_profit_accounts.idx_tenant_store_status added\n";
}
if ($hasTable('growth_profit_accounts') && !$hasIndex('growth_profit_accounts', 'idx_tenant_channel')) {
    $pdo->exec("ALTER TABLE `growth_profit_accounts` ADD INDEX `idx_tenant_channel` (`tenant_id`, `channel_type`)");
    echo "  - growth_profit_accounts.idx_tenant_channel added\n";
}

echo "[3/4] growth_profit_daily_entries ...\n";
if (!$hasTable('growth_profit_daily_entries')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_profit_daily_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `entry_date` DATE NOT NULL,
  `store_id` INT UNSIGNED NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `channel_type` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `sale_price_cny` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `product_cost_cny` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `cancel_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
  `platform_fee_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
  `influencer_commission_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
  `live_hours` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `wage_hourly_cny` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `wage_cost_cny` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `ad_spend_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `ad_spend_currency` CHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CNY',
  `ad_spend_cny` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `gmv_amount` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `gmv_currency` CHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CNY',
  `gmv_cny` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `order_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `fx_snapshot_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fx_status` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'exact',
  `roi` DECIMAL(14,6) NOT NULL DEFAULT 0.000000,
  `net_profit_cny` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `break_even_roi` DECIMAL(14,6) DEFAULT NULL,
  `per_order_profit_cny` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `raw_metrics_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_date_store_account_channel` (`tenant_id`, `entry_date`, `store_id`, `account_id`, `channel_type`),
  KEY `idx_tenant_date` (`tenant_id`, `entry_date`),
  KEY `idx_tenant_store_date` (`tenant_id`, `store_id`, `entry_date`),
  KEY `idx_tenant_channel_date` (`tenant_id`, `channel_type`, `entry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profit center daily entries';
SQL);
    echo "  - growth_profit_daily_entries created\n";
} else {
    echo "  - growth_profit_daily_entries exists\n";
}
if ($hasTable('growth_profit_daily_entries') && !$hasIndex('growth_profit_daily_entries', 'idx_tenant_date')) {
    $pdo->exec("ALTER TABLE `growth_profit_daily_entries` ADD INDEX `idx_tenant_date` (`tenant_id`, `entry_date`)");
    echo "  - growth_profit_daily_entries.idx_tenant_date added\n";
}

echo "[4/4] growth_fx_rates ...\n";
if (!$hasTable('growth_fx_rates')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_fx_rates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `rate_date` DATE NOT NULL,
  `from_currency` CHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_currency` CHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'CNY',
  `rate` DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
  `source` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `is_fallback` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `meta_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_date_pair` (`tenant_id`, `rate_date`, `from_currency`, `to_currency`),
  KEY `idx_tenant_pair_date` (`tenant_id`, `from_currency`, `to_currency`, `rate_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profit center FX rates';
SQL);
    echo "  - growth_fx_rates created\n";
} else {
    echo "  - growth_fx_rates exists\n";
}
if ($hasTable('growth_fx_rates') && !$hasIndex('growth_fx_rates', 'idx_tenant_pair_date')) {
    $pdo->exec("ALTER TABLE `growth_fx_rates` ADD INDEX `idx_tenant_pair_date` (`tenant_id`, `from_currency`, `to_currency`, `rate_date`)");
    echo "  - growth_fx_rates.idx_tenant_pair_date added\n";
}

// Backward-compatible patch for older drafts.
if ($hasTable('growth_profit_accounts') && !$hasColumn('growth_profit_accounts', 'default_gmv_currency')) {
    $pdo->exec("ALTER TABLE `growth_profit_accounts` ADD COLUMN `default_gmv_currency` CHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'VND' AFTER `account_currency`");
    echo "  - growth_profit_accounts.default_gmv_currency added\n";
}
if ($hasTable('growth_profit_daily_entries') && !$hasColumn('growth_profit_daily_entries', 'ad_spend_cny')) {
    $pdo->exec("ALTER TABLE `growth_profit_daily_entries` ADD COLUMN `ad_spend_cny` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `ad_spend_currency`");
    echo "  - growth_profit_daily_entries.ad_spend_cny added\n";
}
if ($hasTable('growth_profit_daily_entries') && !$hasColumn('growth_profit_daily_entries', 'gmv_cny')) {
    $pdo->exec("ALTER TABLE `growth_profit_daily_entries` ADD COLUMN `gmv_cny` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `gmv_currency`");
    echo "  - growth_profit_daily_entries.gmv_cny added\n";
}
if ($hasTable('growth_profit_stores') && !$hasColumn('growth_profit_stores', 'default_cancel_rate_live')) {
    $pdo->exec("ALTER TABLE `growth_profit_stores` ADD COLUMN `default_cancel_rate_live` DECIMAL(8,4) NOT NULL DEFAULT 0.0000 AFTER `default_product_cost_cny`");
    $pdo->exec("UPDATE `growth_profit_stores` SET `default_cancel_rate_live` = `default_cancel_rate`");
    echo "  - growth_profit_stores.default_cancel_rate_live added\n";
}
if ($hasTable('growth_profit_stores') && !$hasColumn('growth_profit_stores', 'default_cancel_rate_video')) {
    $pdo->exec("ALTER TABLE `growth_profit_stores` ADD COLUMN `default_cancel_rate_video` DECIMAL(8,4) NOT NULL DEFAULT 0.0000 AFTER `default_cancel_rate_live`");
    $pdo->exec("UPDATE `growth_profit_stores` SET `default_cancel_rate_video` = `default_cancel_rate`");
    echo "  - growth_profit_stores.default_cancel_rate_video added\n";
}
if ($hasTable('growth_profit_stores') && !$hasColumn('growth_profit_stores', 'default_cancel_rate_influencer')) {
    $pdo->exec("ALTER TABLE `growth_profit_stores` ADD COLUMN `default_cancel_rate_influencer` DECIMAL(8,4) NOT NULL DEFAULT 0.0000 AFTER `default_cancel_rate_video`");
    $pdo->exec("UPDATE `growth_profit_stores` SET `default_cancel_rate_influencer` = `default_cancel_rate`");
    echo "  - growth_profit_stores.default_cancel_rate_influencer added\n";
}

echo "Done.\n";
