<?php
/**
 * 达人外联：message_templates；products.thumb_url / tiktok_shop_url
 *
 * Windows: php database\run_migration_outreach.php
 * Linux:   php database/run_migration_outreach.php
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
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $st->execute([$db, $table]);

    return (int) $st->fetchColumn() > 0;
};

$hasColumn = static function (PDO $pdo, string $db, string $table, string $col): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$db, $table, $col]);

    return (int) $st->fetchColumn() > 0;
};

try {
    if (!$hasTable($pdo, $dbName, 'message_templates')) {
        $pdo->exec(
            "CREATE TABLE message_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '模板名称',
    body MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '正文占位符',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='达人联系话术模板'"
        );
        echo "已创建表 message_templates。\n";
    } else {
        echo "表 message_templates 已存在，跳过创建。\n";
    }

    if ($hasTable($pdo, $dbName, 'products')) {
        if (!$hasColumn($pdo, $dbName, 'products', 'thumb_url')) {
            $pdo->exec(
                "ALTER TABLE products ADD COLUMN thumb_url VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '缩略图' AFTER goods_url"
            );
            echo "已添加 products.thumb_url。\n";
        } else {
            echo "products.thumb_url 已存在，跳过。\n";
        }
        if (!$hasColumn($pdo, $dbName, 'products', 'tiktok_shop_url')) {
            $pdo->exec(
                "ALTER TABLE products ADD COLUMN tiktok_shop_url VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'TikTok商品链' AFTER thumb_url"
            );
            echo "已添加 products.tiktok_shop_url。\n";
        } else {
            echo "products.tiktok_shop_url 已存在，跳过。\n";
        }
    } else {
        echo "表 products 不存在，跳过 ALTER。\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, '迁移失败: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "外联与商品扩展迁移完成。\n";
