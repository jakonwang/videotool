<?php
/**
 * product_style_items 增加 image_path（Excel 导入实拍图 md5 落盘路径，供豆包等使用）
 *
 * Windows: php database\run_migration_product_style_image_path.php
 * Linux:   php database/run_migration_product_style_image_path.php
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

$hasColumn = static function (PDO $pdo, string $db, string $table, string $col): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$db, $table, $col]);

    return (int) $st->fetchColumn() > 0;
};

$dbName = (string) $mysql['database'];
$table = 'product_style_items';
$col = 'image_path';

if ($hasColumn($pdo, $dbName, $table, $col)) {
    echo "{$table}.{$col} 已存在，跳过。\n";
    exit(0);
}

$pdo->exec(
    "ALTER TABLE `{$table}` ADD COLUMN `{$col}` VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '导入实拍图站内路径（如 /uploads/products/md5.jpg）' AFTER `image_ref`"
);
echo "{$table}.{$col} 已添加。\n";
