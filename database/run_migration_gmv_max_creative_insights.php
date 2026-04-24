<?php
/**
 * GMV Max creative insight history migration.
 *
 * Windows: php database\run_migration_gmv_max_creative_insights.php
 * Linux:   php database/run_migration_gmv_max_creative_insights.php
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

echo "[1/3] gmv_max_creative_daily ...\n";
if (!$hasTable('gmv_max_creative_daily')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `gmv_max_creative_daily` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `store_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `campaign_id` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `campaign_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `metric_date` DATE NOT NULL,
  `date_range` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_id` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tiktok_account` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_text` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `sku_orders` INT UNSIGNED NOT NULL DEFAULT 0,
  `cost_per_order` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `gross_revenue` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `roi` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `product_ad_impressions` INT UNSIGNED NOT NULL DEFAULT 0,
  `product_ad_clicks` INT UNSIGNED NOT NULL DEFAULT 0,
  `product_ad_click_rate` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `ad_conversion_rate` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `view_rate_2s` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `view_rate_6s` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `view_rate_25` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `view_rate_50` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `view_rate_75` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `view_rate_100` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `hook_score` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `retention_score` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `conversion_score` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `material_type` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `problem_position` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diagnosis_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `raw_metrics_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_page` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_store_campaign_date_video` (`tenant_id`,`store_id`,`campaign_id`,`metric_date`,`video_id`),
  KEY `idx_tenant_store_date` (`tenant_id`,`store_id`,`metric_date`),
  KEY `idx_tenant_video` (`tenant_id`,`video_id`),
  KEY `idx_tenant_material` (`tenant_id`,`store_id`,`material_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GMV Max creative daily metrics';
SQL);
    echo "  - gmv_max_creative_daily created\n";
} else {
    echo "  - gmv_max_creative_daily exists\n";
}

echo "[2/3] gmv_max_store_baselines ...\n";
if (!$hasTable('gmv_max_store_baselines')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `gmv_max_store_baselines` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `store_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `window_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `metric_date` DATE NOT NULL,
  `sample_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_cost` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `total_orders` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_revenue` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `avg_roi` DECIMAL(12,4) NOT NULL DEFAULT 0.0000,
  `avg_ctr` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `avg_cvr` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  `p50_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `p70_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `p90_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_store_window_date` (`tenant_id`,`store_id`,`window_days`,`metric_date`),
  KEY `idx_tenant_store` (`tenant_id`,`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GMV Max store metric baselines';
SQL);
    echo "  - gmv_max_store_baselines created\n";
} else {
    echo "  - gmv_max_store_baselines exists\n";
}

echo "[3/3] gmv_max_recommendation_snapshots ...\n";
if (!$hasTable('gmv_max_recommendation_snapshots')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `gmv_max_recommendation_snapshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `store_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `campaign_id` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `campaign_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `snapshot_date` DATE NOT NULL,
  `baseline_mode` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'regional_default',
  `sample_level` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'insufficient',
  `stage` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cold_start',
  `main_problem` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'insufficient_data',
  `action_level` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'observe',
  `recommendation_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_store_campaign_date` (`tenant_id`,`store_id`,`campaign_id`,`snapshot_date`),
  KEY `idx_tenant_store_date` (`tenant_id`,`store_id`,`snapshot_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GMV Max recommendation snapshots';
SQL);
    echo "  - gmv_max_recommendation_snapshots created\n";
} else {
    echo "  - gmv_max_recommendation_snapshots exists\n";
}

$ensureIndex = static function (string $table, string $name, string $sql) use ($pdo, $hasTable, $hasIndex): void {
    if ($hasTable($table) && !$hasIndex($table, $name)) {
        $pdo->exec($sql);
        echo "  - {$table}.{$name} added\n";
    }
};

$ensureIndex('gmv_max_creative_daily', 'idx_tenant_store_date', 'ALTER TABLE `gmv_max_creative_daily` ADD INDEX `idx_tenant_store_date` (`tenant_id`,`store_id`,`metric_date`)');
$ensureIndex('gmv_max_store_baselines', 'idx_tenant_store', 'ALTER TABLE `gmv_max_store_baselines` ADD INDEX `idx_tenant_store` (`tenant_id`,`store_id`)');
$ensureIndex('gmv_max_recommendation_snapshots', 'idx_tenant_store_date', 'ALTER TABLE `gmv_max_recommendation_snapshots` ADD INDEX `idx_tenant_store_date` (`tenant_id`,`store_id`,`snapshot_date`)');

if ($hasTable('gmv_max_creative_daily') && !$hasColumn('gmv_max_creative_daily', 'source_page')) {
    $pdo->exec("ALTER TABLE `gmv_max_creative_daily` ADD COLUMN `source_page` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `raw_metrics_json`");
    echo "  - gmv_max_creative_daily.source_page added\n";
}

echo "Done.\n";
