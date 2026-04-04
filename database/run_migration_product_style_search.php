<?php
/**
 * 图片搜款式表（可重复执行）
 *
 * Windows: php database\run_migration_product_style_search.php
 * Linux:   php database/run_migration_product_style_search.php
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

$hasTable = static function (PDO $pdo, string $db, string $table): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $st->execute([$db, $table]);

    return (int) $st->fetchColumn() > 0;
};

$dbName = (string) $mysql['database'];

echo "[1/1] 创建表 product_style_items（若不存在）…\n";
if (!$hasTable($pdo, $dbName, 'product_style_items')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE product_style_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_code VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '产品编号',
    image_ref VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '参考图 URL 或站内路径',
    hot_type VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '爆款类型',
    embedding MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '特征向量 JSON 数组',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1有效 0停用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_product_code (product_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='款式图搜索引'
SQL);
    echo "      已创建。\n";
} else {
    echo "      已存在，跳过。\n";
}

echo "\n完成。请安装 Python 依赖后使用后台「寻款」导入 CSV。\n";
