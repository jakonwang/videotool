<?php
/**
 * Auto DM hotfix migration.
 * Fixes missing influencer columns on legacy databases where history was marked
 * applied but schema is incomplete.
 *
 * Windows: php database\run_migration_auto_dm_hotfix.php
 * Linux:   php database/run_migration_auto_dm_hotfix.php
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

echo "[1/2] Check influencers auto_dm columns...\n";
if (!$hasTable('influencers')) {
    echo "  - influencers table not found, skip\n";
} else {
    if (!$hasColumn('influencers', 'do_not_contact')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `do_not_contact` TINYINT UNSIGNED NOT NULL DEFAULT 0");
        echo "  - added influencers.do_not_contact\n";
    } else {
        echo "  - influencers.do_not_contact exists\n";
    }

    if (!$hasColumn('influencers', 'last_auto_dm_at')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `last_auto_dm_at` DATETIME DEFAULT NULL");
        echo "  - added influencers.last_auto_dm_at\n";
    } else {
        echo "  - influencers.last_auto_dm_at exists\n";
    }

    if (!$hasColumn('influencers', 'auto_dm_fail_count')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `auto_dm_fail_count` INT UNSIGNED NOT NULL DEFAULT 0");
        echo "  - added influencers.auto_dm_fail_count\n";
    } else {
        echo "  - influencers.auto_dm_fail_count exists\n";
    }

    if (!$hasColumn('influencers', 'cooldown_until')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `cooldown_until` DATETIME DEFAULT NULL");
        echo "  - added influencers.cooldown_until\n";
    } else {
        echo "  - influencers.cooldown_until exists\n";
    }

    if (!$hasIndex('influencers', 'idx_last_auto_dm_at')) {
        $pdo->exec("ALTER TABLE `influencers` ADD INDEX `idx_last_auto_dm_at` (`last_auto_dm_at`)");
        echo "  - added influencers.idx_last_auto_dm_at\n";
    }
    if (!$hasIndex('influencers', 'idx_do_not_contact')) {
        $pdo->exec("ALTER TABLE `influencers` ADD INDEX `idx_do_not_contact` (`do_not_contact`)");
        echo "  - added influencers.idx_do_not_contact\n";
    }
    if (!$hasIndex('influencers', 'idx_cooldown_until')) {
        $pdo->exec("ALTER TABLE `influencers` ADD INDEX `idx_cooldown_until` (`cooldown_until`)");
        echo "  - added influencers.idx_cooldown_until\n";
    }
}

echo "[2/2] Check auto_dm tables (safety)...\n";
if (!$hasTable('auto_dm_campaigns') || !$hasTable('auto_dm_tasks') || !$hasTable('auto_dm_events')) {
    echo "  - warning: some auto_dm tables are missing, please run run_migration_auto_dm_v1.php and run_migration_auto_dm_v2.php\n";
} else {
    echo "  - auto_dm tables exist\n";
}

echo "Done.\n";

