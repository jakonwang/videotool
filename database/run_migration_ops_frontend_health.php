<?php
/**
 * Frontend health log migration.
 *
 * Windows: php database\run_migration_ops_frontend_health.php
 * Linux:   php database/run_migration_ops_frontend_health.php
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

$hasIndex = static function (string $table, string $index) use ($pdo, $dbName): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $st->execute([$dbName, $table, $index]);
    return (int) $st->fetchColumn() > 0;
};

echo "[1/1] ops_frontend_health_logs ...\n";
if (!$hasTable('ops_frontend_health_logs')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `ops_frontend_health_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `admin_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `module` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `page` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `event` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `trace_id` VARCHAR(96) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `detail_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_event_time` (`tenant_id`, `event`, `created_at`),
  KEY `idx_module_page_time` (`module`, `page`, `created_at`),
  KEY `idx_trace_id` (`trace_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Frontend stability health logs';
SQL);
    echo "  - table created\n";
} else {
    echo "  - table exists\n";
}

if ($hasTable('ops_frontend_health_logs') && !$hasIndex('ops_frontend_health_logs', 'idx_tenant_event_time')) {
    $pdo->exec("ALTER TABLE `ops_frontend_health_logs` ADD INDEX `idx_tenant_event_time` (`tenant_id`, `event`, `created_at`)");
    echo "  - idx_tenant_event_time added\n";
}
if ($hasTable('ops_frontend_health_logs') && !$hasIndex('ops_frontend_health_logs', 'idx_module_page_time')) {
    $pdo->exec("ALTER TABLE `ops_frontend_health_logs` ADD INDEX `idx_module_page_time` (`module`, `page`, `created_at`)");
    echo "  - idx_module_page_time added\n";
}
if ($hasTable('ops_frontend_health_logs') && !$hasIndex('ops_frontend_health_logs', 'idx_trace_id')) {
    $pdo->exec("ALTER TABLE `ops_frontend_health_logs` ADD INDEX `idx_trace_id` (`trace_id`)");
    echo "  - idx_trace_id added\n";
}

echo "Done.\n";

