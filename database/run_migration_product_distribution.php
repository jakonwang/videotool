<?php
/**
 * 商品 / 达人链 表结构增量更新（可重复执行：已存在的表、列、索引、外键会跳过）
 *
 * 使用项目 config/database.php 中的 MySQL 连接信息。
 *
 * Windows（PowerShell，在项目根目录 videotool 下）:
 *   php database\run_migration_product_distribution.php
 *
 * Linux:
 *   php database/run_migration_product_distribution.php
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "请使用命令行执行本脚本。\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

if (!function_exists('env')) {
    /**
     * 独立运行时无框架 env，使用配置中的默认值（与 config/database.php 中 default 一致）
     */
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

$hasColumn = static function (PDO $pdo, string $db, string $table, string $column): bool {
    $sql = 'SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?';
    $st = $pdo->prepare($sql);
    $st->execute([$db, $table, $column]);

    return (int) $st->fetchColumn() > 0;
};

$hasIndex = static function (PDO $pdo, string $db, string $table, string $indexName): bool {
    $sql = 'SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?';
    $st = $pdo->prepare($sql);
    $st->execute([$db, $table, $indexName]);

    return (int) $st->fetchColumn() > 0;
};

$hasForeignKey = static function (PDO $pdo, string $db, string $table, string $fkName): bool {
    $sql = 'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
            AND CONSTRAINT_TYPE = ?';
    $st = $pdo->prepare($sql);
    $st->execute([$db, $table, $fkName, 'FOREIGN KEY']);

    return (int) $st->fetchColumn() > 0;
};

$hasTable = static function (PDO $pdo, string $db, string $table): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );
    $st->execute([$db, $table]);

    return (int) $st->fetchColumn() > 0;
};

echo "[1/8] 创建表 products（若不存在）…\n";
$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商品名称',
    goods_url VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商品页外链',
    status TINYINT(1) DEFAULT 1 COMMENT '1启用 0禁用',
    sort_order INT DEFAULT 0 COMMENT '排序，越小越前',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品表'
SQL);
echo "      完成。\n";

echo "[2/8] 创建表 product_links（若不存在）…\n";
$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS product_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL COMMENT '商品ID',
    token VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '达人页令牌',
    label VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
    status TINYINT(1) DEFAULT 1 COMMENT '1启用 0禁用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_token (token),
    INDEX idx_product (product_id),
    CONSTRAINT fk_pl_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='达人分发链接'
SQL);
echo "      完成。\n";

echo "[3/8] videos 表增加 product_id…\n";
if (!$hasColumn($pdo, $dbName, 'videos', 'product_id')) {
    $pdo->exec("ALTER TABLE videos ADD COLUMN product_id INT NULL COMMENT '所属商品ID' AFTER device_id");
    echo "      已添加列 product_id。\n";
} else {
    echo "      已存在，跳过。\n";
}

echo "[4/8] videos 表增加索引 idx_product_downloaded…\n";
if (!$hasIndex($pdo, $dbName, 'videos', 'idx_product_downloaded')) {
    $pdo->exec('ALTER TABLE videos ADD INDEX idx_product_downloaded (product_id, is_downloaded)');
    echo "      已添加索引。\n";
} else {
    echo "      已存在，跳过。\n";
}

echo "[5/8] videos 表外键 fk_videos_product…\n";
if (!$hasForeignKey($pdo, $dbName, 'videos', 'fk_videos_product')) {
    $pdo->exec(
        'ALTER TABLE videos ADD CONSTRAINT fk_videos_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL'
    );
    echo "      已添加外键。\n";
} else {
    echo "      已存在，跳过。\n";
}

echo "[6/8] videos.device_id 允许为空（达人素材可不选设备）…\n";
$stNull = $pdo->prepare(
    'SELECT IS_NULLABLE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
$stNull->execute([$dbName, 'videos', 'device_id']);
$isNullable = $stNull->fetchColumn();
if ($isNullable === 'YES') {
    echo "      已为可空，跳过。\n";
} else {
    $pdo->exec("ALTER TABLE videos MODIFY device_id INT NULL COMMENT '设备ID，达人素材可不绑定'");
    echo "      已修改为可空。\n";
}

echo "[7/8] 创建表 system_settings（若不存在）…\n";
if (!$hasTable($pdo, $dbName, 'system_settings')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    skey VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '键',
    svalue TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '值',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_skey (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置'
SQL);
    echo "      已创建。\n";
} else {
    echo "      已存在，跳过。\n";
}

echo "[8/8] 初始化系统设置默认值…\n";
$pdo->exec("INSERT IGNORE INTO system_settings (skey, svalue) VALUES
    ('storage', 'qiniu'),
    ('default_cover_url', ''),
    ('site_name', '')");
echo "      完成。\n";

echo "\n全部处理完毕。可在后台使用「商品」「达人链」「设置」等功能。\n";
