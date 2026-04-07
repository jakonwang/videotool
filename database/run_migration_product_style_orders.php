<?php
/**
 * Product style search migration:
 * - add wholesale_price / min_order_qty
 * - create offline_orders
 *
 * Windows: php database\run_migration_product_style_orders.php
 * Linux:   php database/run_migration_product_style_orders.php
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

echo "[1/2] Extend style table columns...\n";
$styleTables = ['product_style_items', 'product_style_search'];
$touched = 0;
foreach ($styleTables as $table) {
    if (!$hasTable($pdo, $dbName, $table)) {
        continue;
    }
    ++$touched;
    if (!$hasColumn($pdo, $dbName, $table, 'wholesale_price')) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `wholesale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Wholesale price' AFTER `hot_type`");
        echo "  - {$table}.wholesale_price added\n";
    } else {
        echo "  - {$table}.wholesale_price exists\n";
    }
    if (!$hasColumn($pdo, $dbName, $table, 'min_order_qty')) {
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `min_order_qty` INT NOT NULL DEFAULT 1 COMMENT 'Min order quantity' AFTER `wholesale_price`");
        echo "  - {$table}.min_order_qty added\n";
    } else {
        echo "  - {$table}.min_order_qty exists\n";
    }
}
if ($touched === 0) {
    echo "  - no style table found (product_style_items/product_style_search)\n";
}

echo "[2/2] Create offline_orders table if missing...\n";
if (!$hasTable($pdo, $dbName, 'offline_orders')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `offline_orders` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique order number',
  `customer_info` JSON NOT NULL COMMENT 'Customer info JSON',
  `items_json` JSON NOT NULL COMMENT 'Order items JSON',
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Order total amount',
  `status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 pending, 1 confirmed, 2 cancelled',
  `remark` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Remark',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Offline reservation orders';
SQL);
    echo "  - offline_orders created\n";
} else {
    echo "  - offline_orders exists\n";
}

echo "Done.\n";
