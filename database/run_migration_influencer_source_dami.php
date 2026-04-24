<?php
/**
 * 达秘协同（CSV导入）迁移：
 * - influencers 增加外部源字段
 * - 新增 influencer_source_import_batches 批次审计表
 *
 * Windows: php database\run_migration_influencer_source_dami.php
 * Linux:   php database/run_migration_influencer_source_dami.php
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

echo "[1/2] Extend influencers with source fields...\n";
if (!$hasTable('influencers')) {
    echo "  - influencers table missing, skip\n";
} else {
    if (!$hasColumn('influencers', 'profile_url')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `profile_url` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");
        echo "  - added influencers.profile_url\n";
    }
    if (!$hasColumn('influencers', 'data_source')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `data_source` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");
        echo "  - added influencers.data_source\n";
    }
    if (!$hasColumn('influencers', 'source_system')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `source_system` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");
        echo "  - added influencers.source_system\n";
    }
    if (!$hasColumn('influencers', 'source_influencer_id')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `source_influencer_id` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");
        echo "  - added influencers.source_influencer_id\n";
    }
    if (!$hasColumn('influencers', 'source_sync_at')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `source_sync_at` DATETIME DEFAULT NULL");
        echo "  - added influencers.source_sync_at\n";
    }
    if (!$hasColumn('influencers', 'source_hash')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `source_hash` CHAR(32) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL");
        echo "  - added influencers.source_hash\n";
    }
    if (!$hasColumn('influencers', 'last_crawled_at')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `last_crawled_at` DATETIME DEFAULT NULL");
        echo "  - added influencers.last_crawled_at\n";
    }
    if (!$hasColumn('influencers', 'source_batch_id')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `source_batch_id` BIGINT UNSIGNED DEFAULT NULL");
        echo "  - added influencers.source_batch_id\n";
    }
    if (!$hasIndex('influencers', 'idx_source_system')) {
        $pdo->exec("ALTER TABLE `influencers` ADD INDEX `idx_source_system` (`source_system`, `source_sync_at`)");
        echo "  - added influencers.idx_source_system\n";
    }
    if (!$hasIndex('influencers', 'uk_source_influencer')) {
        if ($hasColumn('influencers', 'tenant_id')) {
            $pdo->exec("ALTER TABLE `influencers` ADD UNIQUE KEY `uk_source_influencer` (`tenant_id`, `source_system`, `source_influencer_id`)");
        } else {
            $pdo->exec("ALTER TABLE `influencers` ADD UNIQUE KEY `uk_source_influencer` (`source_system`, `source_influencer_id`)");
        }
        echo "  - added influencers.uk_source_influencer\n";
    }
    if (!$hasIndex('influencers', 'idx_source_batch_id')) {
        $pdo->exec("ALTER TABLE `influencers` ADD INDEX `idx_source_batch_id` (`source_batch_id`)");
        echo "  - added influencers.idx_source_batch_id\n";
    }
}

echo "[2/2] Create influencer_source_import_batches table...\n";
if (!$hasTable('influencer_source_import_batches')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `influencer_source_import_batches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `batch_no` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `source_system` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'dami',
  `file_name` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `mapping_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `inserted_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_rows_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_batch_no` (`tenant_id`, `batch_no`),
  KEY `idx_tenant_source_created` (`tenant_id`, `source_system`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='达人外部来源导入批次';
SQL);
    echo "  - created influencer_source_import_batches\n";
} else {
    echo "  - influencer_source_import_batches exists\n";
}

echo "Done.\n";
