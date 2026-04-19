<?php
/**
 * Profit center browser plugin bridge migration.
 *
 * Windows: php database\run_migration_profit_plugin.php
 * Linux:   php database/run_migration_profit_plugin.php
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

echo "[1/4] growth_profit_plugin_tokens ...\n";
if (!$hasTable('growth_profit_plugin_tokens')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_profit_plugin_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `token_name` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TikTok Plugin',
  `token_prefix` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` CHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'profit_ingest',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `expires_at` DATETIME NULL DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `last_used_at` DATETIME NULL DEFAULT NULL,
  `last_used_ip` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `revoked_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_tenant_status` (`tenant_id`, `status`),
  KEY `idx_tenant_scope` (`tenant_id`, `scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profit plugin tokens';
SQL);
    echo "  - growth_profit_plugin_tokens created\n";
} else {
    echo "  - growth_profit_plugin_tokens exists\n";
}
if ($hasTable('growth_profit_plugin_tokens') && !$hasIndex('growth_profit_plugin_tokens', 'idx_tenant_status')) {
    $pdo->exec("ALTER TABLE `growth_profit_plugin_tokens` ADD INDEX `idx_tenant_status` (`tenant_id`, `status`)");
    echo "  - growth_profit_plugin_tokens.idx_tenant_status added\n";
}
if ($hasTable('growth_profit_plugin_tokens') && !$hasIndex('growth_profit_plugin_tokens', 'idx_tenant_scope')) {
    $pdo->exec("ALTER TABLE `growth_profit_plugin_tokens` ADD INDEX `idx_tenant_scope` (`tenant_id`, `scope`)");
    echo "  - growth_profit_plugin_tokens.idx_tenant_scope added\n";
}

echo "[2/4] growth_profit_plugin_ingest_logs ...\n";
if (!$hasTable('growth_profit_plugin_ingest_logs')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_profit_plugin_ingest_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `token_id` BIGINT UNSIGNED DEFAULT NULL,
  `trace_id` VARCHAR(96) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `row_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `saved_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `source_page` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `request_date` DATE DEFAULT NULL,
  `request_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `result_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_created` (`tenant_id`, `created_at`),
  KEY `idx_trace_id` (`trace_id`),
  KEY `idx_tenant_status` (`tenant_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profit plugin ingest logs';
SQL);
    echo "  - growth_profit_plugin_ingest_logs created\n";
} else {
    echo "  - growth_profit_plugin_ingest_logs exists\n";
}
if ($hasTable('growth_profit_plugin_ingest_logs') && !$hasIndex('growth_profit_plugin_ingest_logs', 'idx_tenant_created')) {
    $pdo->exec("ALTER TABLE `growth_profit_plugin_ingest_logs` ADD INDEX `idx_tenant_created` (`tenant_id`, `created_at`)");
    echo "  - growth_profit_plugin_ingest_logs.idx_tenant_created added\n";
}
if ($hasTable('growth_profit_plugin_ingest_logs') && !$hasIndex('growth_profit_plugin_ingest_logs', 'idx_tenant_status')) {
    $pdo->exec("ALTER TABLE `growth_profit_plugin_ingest_logs` ADD INDEX `idx_tenant_status` (`tenant_id`, `status`)");
    echo "  - growth_profit_plugin_ingest_logs.idx_tenant_status added\n";
}

echo "[3/4] growth_profit_plugin_store_maps ...\n";
if (!$hasTable('growth_profit_plugin_store_maps')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_profit_plugin_store_maps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `store_alias` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `store_id` INT UNSIGNED NOT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_store_alias` (`tenant_id`, `store_alias`),
  KEY `idx_tenant_store` (`tenant_id`, `store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profit plugin store alias mappings';
SQL);
    echo "  - growth_profit_plugin_store_maps created\n";
} else {
    echo "  - growth_profit_plugin_store_maps exists\n";
}
if ($hasTable('growth_profit_plugin_store_maps') && !$hasIndex('growth_profit_plugin_store_maps', 'idx_tenant_store')) {
    $pdo->exec("ALTER TABLE `growth_profit_plugin_store_maps` ADD INDEX `idx_tenant_store` (`tenant_id`, `store_id`)");
    echo "  - growth_profit_plugin_store_maps.idx_tenant_store added\n";
}

echo "[4/4] growth_profit_plugin_account_maps ...\n";
if (!$hasTable('growth_profit_plugin_account_maps')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `growth_profit_plugin_account_maps` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `account_alias` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `account_id` INT UNSIGNED NOT NULL,
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_account_alias` (`tenant_id`, `account_alias`),
  KEY `idx_tenant_account` (`tenant_id`, `account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Profit plugin account alias mappings';
SQL);
    echo "  - growth_profit_plugin_account_maps created\n";
} else {
    echo "  - growth_profit_plugin_account_maps exists\n";
}
if ($hasTable('growth_profit_plugin_account_maps') && !$hasIndex('growth_profit_plugin_account_maps', 'idx_tenant_account')) {
    $pdo->exec("ALTER TABLE `growth_profit_plugin_account_maps` ADD INDEX `idx_tenant_account` (`tenant_id`, `account_id`)");
    echo "  - growth_profit_plugin_account_maps.idx_tenant_account added\n";
}

if ($hasTable('growth_profit_plugin_tokens') && !$hasColumn('growth_profit_plugin_tokens', 'token_name')) {
    $pdo->exec("ALTER TABLE `growth_profit_plugin_tokens` ADD COLUMN `token_name` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'TikTok Plugin' AFTER `tenant_id`");
    echo "  - growth_profit_plugin_tokens.token_name added\n";
}

echo "Done.\n";

