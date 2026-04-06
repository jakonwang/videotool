<?php
/**
 * products / product_style_items 增加 ai_description（可重复执行，已存在则跳过）
 *
 * Windows: php database\run_migration_openai_vision_columns.php
 * Linux:   php database/run_migration_openai_vision_columns.php
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

$hasColumn = static function (PDO $pdo, string $table, string $col): bool {
    $t = str_replace(['`', ';'], '', $table);
    $st = $pdo->query('SHOW COLUMNS FROM `' . $t . '` LIKE ' . $pdo->quote($col));

    return $st !== false && $st->fetch(PDO::FETCH_ASSOC) !== false;
};

if (!$hasColumn($pdo, 'products', 'ai_description')) {
    $pdo->exec(
        "ALTER TABLE products ADD COLUMN ai_description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'OpenAI Vision 耳环视觉特征' AFTER goods_url"
    );
    echo "products.ai_description 已添加。\n";
} else {
    echo "products.ai_description 已存在，跳过。\n";
}

if (!$hasColumn($pdo, 'product_style_items', 'ai_description')) {
    $pdo->exec(
        "ALTER TABLE product_style_items ADD COLUMN ai_description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'OpenAI Vision 视觉特征描述' AFTER hot_type"
    );
    echo "product_style_items.ai_description 已添加。\n";
} else {
    echo "product_style_items.ai_description 已存在，跳过。\n";
}

echo "完成。\n";
