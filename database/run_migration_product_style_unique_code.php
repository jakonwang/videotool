<?php
/**
 * 为 product_style_items.product_code 添加唯一索引（可重复执行）
 *
 * 若库内已有重复编号，本脚本会中止并提示先清理数据。
 *
 * Windows: php database\run_migration_product_style_unique_code.php
 * Linux:   php database/run_migration_product_style_unique_code.php
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "请使用命令行执行本脚本。\n");
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
    fwrite(STDERR, "未找到 mysql 连接配置。\n");
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
    fwrite(STDERR, '数据库连接失败: ' . $e->getMessage() . "\n");
    exit(1);
}

$pdo->exec('SET NAMES utf8mb4');

$dbName = (string) $mysql['database'];

$hasIndex = static function (PDO $pdo, string $db, string $table, string $indexName): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $st->execute([$db, $table, $indexName]);

    return (int) $st->fetchColumn() > 0;
};

$table = 'product_style_items';
$stTable = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
);
$stTable->execute([$dbName, $table]);
if ((int) $stTable->fetchColumn() === 0) {
    fwrite(STDERR, "表 {$table} 不存在，请先执行 run_migration_product_style_search.php。\n");
    exit(1);
}

if ($hasIndex($pdo, $dbName, $table, 'uk_product_code')) {
    echo "唯一索引 uk_product_code 已存在，跳过。\n";
    exit(0);
}

$dupSt = $pdo->query(
    'SELECT product_code, COUNT(*) AS c FROM product_style_items GROUP BY product_code HAVING c > 1 LIMIT 5'
);
$dups = $dupSt ? $dupSt->fetchAll(PDO::FETCH_ASSOC) : [];
if ($dups !== []) {
    fwrite(STDERR, "存在重复的 product_code，无法添加唯一索引。请先合并或删除重复行（示例编号）：\n");
    foreach ($dups as $r) {
        fwrite(STDERR, '  - ' . (string) ($r['product_code'] ?? '') . "\n");
    }
    exit(1);
}

try {
    $pdo->exec('ALTER TABLE product_style_items ADD UNIQUE KEY uk_product_code (product_code)');
    echo "已添加 UNIQUE KEY uk_product_code (product_code)。\n";
} catch (PDOException $e) {
    fwrite(STDERR, '添加唯一索引失败: ' . $e->getMessage() . "\n");
    exit(1);
}

if ($hasIndex($pdo, $dbName, $table, 'idx_code')) {
    try {
        $pdo->exec('ALTER TABLE product_style_items DROP INDEX idx_code');
        echo "已移除冗余索引 idx_code。\n";
    } catch (PDOException $e) {
        echo "注意：未能删除 idx_code（可手工处理）：" . $e->getMessage() . "\n";
    }
}

echo "完成。\n";
