<?php
/**
 * Profit center store default GMV currency migration.
 *
 * Windows: php database\run_migration_profit_store_currency.php
 * Linux:   php database/run_migration_profit_store_currency.php
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

echo "[1/3] growth_profit_stores.default_gmv_currency ...\n";
if (!$hasTable('growth_profit_stores')) {
    echo "  - growth_profit_stores missing, skip\n";
    echo "Done.\n";
    exit(0);
}

if (!$hasColumn('growth_profit_stores', 'default_gmv_currency')) {
    $afterCol = $hasColumn('growth_profit_stores', 'default_timezone') ? 'default_timezone' : 'default_live_wage_hourly_cny';
    $pdo->exec(
        "ALTER TABLE `growth_profit_stores` " .
        "ADD COLUMN `default_gmv_currency` CHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci " .
        "NOT NULL DEFAULT 'VND' AFTER `{$afterCol}`"
    );
    echo "  - column added\n";
} else {
    echo "  - column exists\n";
}

echo "[2/3] normalize store default currency ...\n";
$pdo->exec(
    "UPDATE `growth_profit_stores` " .
    "SET `default_gmv_currency` = UPPER(TRIM(`default_gmv_currency`)) " .
    "WHERE `default_gmv_currency` IS NOT NULL"
);
$pdo->exec(
    "UPDATE `growth_profit_stores` " .
    "SET `default_gmv_currency` = 'VND' " .
    "WHERE `default_gmv_currency` IS NULL " .
    "   OR TRIM(`default_gmv_currency`) = '' " .
    "   OR CHAR_LENGTH(TRIM(`default_gmv_currency`)) <> 3 " .
    "   OR UPPER(TRIM(`default_gmv_currency`)) NOT IN ('CNY','USD','VND')"
);
echo "  - normalized\n";

echo "[3/3] backfill from existing store account currency ...\n";
if ($hasTable('growth_profit_accounts')) {
    $sourceCol = '';
    if ($hasColumn('growth_profit_accounts', 'default_gmv_currency')) {
        $sourceCol = 'default_gmv_currency';
    } elseif ($hasColumn('growth_profit_accounts', 'account_currency')) {
        $sourceCol = 'account_currency';
    }

    if ($sourceCol !== '' && $hasColumn('growth_profit_accounts', 'tenant_id') && $hasColumn('growth_profit_accounts', 'store_id')) {
        $pdo->exec(
            "UPDATE `growth_profit_stores` s " .
            "LEFT JOIN ( " .
            "  SELECT tenant_id, store_id, " .
            "         SUBSTRING_INDEX(GROUP_CONCAT(UPPER(TRIM(`{$sourceCol}`)) ORDER BY id DESC SEPARATOR ','), ',', 1) AS src_currency " .
            "  FROM `growth_profit_accounts` " .
            "  WHERE `{$sourceCol}` IS NOT NULL AND TRIM(`{$sourceCol}`) <> '' " .
            "  GROUP BY tenant_id, store_id " .
            ") a ON a.tenant_id = s.tenant_id AND a.store_id = s.id " .
            "SET s.default_gmv_currency = CASE " .
            "  WHEN a.src_currency IN ('CNY','USD','VND') THEN a.src_currency " .
            "  ELSE 'VND' " .
            "END"
        );
        echo "  - backfilled from growth_profit_accounts.{$sourceCol}\n";
    } else {
        echo "  - account source columns missing, fallback VND kept\n";
    }
} else {
    echo "  - growth_profit_accounts missing, fallback VND kept\n";
}

echo "Done.\n";
