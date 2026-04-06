<?php
/**
 * 商品/达人分类字段迁移
 *
 * Windows: php database\run_migration_product_influencer_category.php
 * Linux:   php database/run_migration_product_influencer_category.php
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

$hasTable = static function (PDO $pdo, string $db, string $table): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $st->execute([$db, $table]);
    return (int) $st->fetchColumn() > 0;
};

$hasColumn = static function (PDO $pdo, string $db, string $table, string $col): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$db, $table, $col]);
    return (int) $st->fetchColumn() > 0;
};

$hasIndex = static function (PDO $pdo, string $db, string $table, string $idx): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $st->execute([$db, $table, $idx]);
    return (int) $st->fetchColumn() > 0;
};

try {
    if ($hasTable($pdo, $dbName, 'products')) {
        if (!$hasColumn($pdo, $dbName, 'products', 'category_name')) {
            $pdo->exec("ALTER TABLE products ADD COLUMN category_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商品分类' AFTER name");
            echo "已添加 products.category_name。\n";
        } else {
            echo "products.category_name 已存在，跳过。\n";
        }
        if (!$hasIndex($pdo, $dbName, 'products', 'idx_category_name')) {
            $pdo->exec("ALTER TABLE products ADD INDEX idx_category_name (category_name)");
            echo "已添加 products.idx_category_name。\n";
        } else {
            echo "products.idx_category_name 已存在，跳过。\n";
        }
    } else {
        echo "表 products 不存在，跳过。\n";
    }

    if ($hasTable($pdo, $dbName, 'influencers')) {
        if (!$hasColumn($pdo, $dbName, 'influencers', 'category_name')) {
            $pdo->exec("ALTER TABLE influencers ADD COLUMN category_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '达人分类' AFTER tiktok_id");
            echo "已添加 influencers.category_name。\n";
        } else {
            echo "influencers.category_name 已存在，跳过。\n";
        }
        if (!$hasIndex($pdo, $dbName, 'influencers', 'idx_category_name')) {
            $pdo->exec("ALTER TABLE influencers ADD INDEX idx_category_name (category_name)");
            echo "已添加 influencers.idx_category_name。\n";
        } else {
            echo "influencers.idx_category_name 已存在，跳过。\n";
        }
    } else {
        echo "表 influencers 不存在，跳过。\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, '迁移失败: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "分类字段迁移完成。\n";
