<?php
/**
 * 寻款阿里云同步队列表（可重复执行）
 *
 * Windows: php database\run_migration_product_style_is_queue.php
 * Linux:   php database/run_migration_product_style_is_queue.php
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

$sql = file_get_contents($root . '/database/migrations/20260410_product_style_is_queue.sql');
if ($sql === false) {
    fwrite(STDERR, "无法读取 migrations/20260410_product_style_is_queue.sql\n");
    exit(1);
}

try {
    $pdo->exec($sql);
    echo "product_style_is_queue 已就绪。\n";
} catch (PDOException $e) {
    fwrite(STDERR, '执行失败: ' . $e->getMessage() . "\n");
    exit(1);
}
