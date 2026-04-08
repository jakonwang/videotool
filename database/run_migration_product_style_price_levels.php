<?php
/**
 * Product style level pricing migration:
 * - add price_levels_json column for level-based wholesale pricing.
 *
 * Windows: php database\run_migration_product_style_price_levels.php
 * Linux:   php database/run_migration_product_style_price_levels.php
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

$hasTable = static function (PDO $pdo, string $db, string $table): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $st->execute([$db, $table]);
    return (int) $st->fetchColumn() > 0;
};

$hasColumn = static function (PDO $pdo, string $db, string $table, string $column): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$db, $table, $column]);
    return (int) $st->fetchColumn() > 0;
};

echo "[1/1] Extend style tables with price_levels_json...\n";
$tables = ['product_style_items', 'product_style_search'];
$touched = 0;
foreach ($tables as $table) {
    if (!$hasTable($pdo, $dbName, $table)) {
        continue;
    }
    ++$touched;
    if ($hasColumn($pdo, $dbName, $table, 'price_levels_json')) {
        echo "  - {$table}.price_levels_json exists\n";
        continue;
    }
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `price_levels_json` TEXT NULL COMMENT 'Level pricing JSON' AFTER `min_order_qty`");
    echo "  - {$table}.price_levels_json added\n";
}
if ($touched === 0) {
    echo "  - no style table found (product_style_items/product_style_search)\n";
}

echo "Done.\n";
