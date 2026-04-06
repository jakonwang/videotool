<?php
/**
 * 达人 CRM：influencers、influencer_import_tasks、product_links.influencer_id
 *
 * Windows: php database\run_migration_influencers_crm.php
 * Linux:   php database/run_migration_influencers_crm.php
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
    if (!$hasTable($pdo, $dbName, 'influencers')) {
        $pdo->exec(
            "CREATE TABLE influencers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tiktok_id VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'TikTok 用户名 @handle，唯一',
    nickname VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '昵称',
    avatar_url VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '头像 URL',
    follower_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '粉丝数',
    contact_info TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系方式 JSON 文本',
    region VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '地区',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0待联系 1合作中 2黑名单',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tiktok_id (tiktok_id),
    KEY idx_status (status),
    KEY idx_region (region),
    KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TikTok 达人名录'"
        );
        echo "已创建表 influencers。\n";
    } else {
        echo "表 influencers 已存在，跳过创建。\n";
    }

    if (!$hasTable($pdo, $dbName, 'influencer_import_tasks')) {
        $pdo->exec(
            "CREATE TABLE influencer_import_tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    status VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
    file_path VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
    file_ext VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'csv',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0,
    processed_rows INT UNSIGNED NOT NULL DEFAULT 0,
    line_idx INT UNSIGNED NOT NULL DEFAULT 0,
    header_resolved TINYINT UNSIGNED NOT NULL DEFAULT 0,
    header_json TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    use_default_header TINYINT UNSIGNED NOT NULL DEFAULT 0,
    inserted_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    logs_json MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    error_message VARCHAR(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status (status),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='达人导入任务'"
        );
        echo "已创建表 influencer_import_tasks。\n";
    } else {
        echo "表 influencer_import_tasks 已存在，跳过创建。\n";
    }

    if ($hasTable($pdo, $dbName, 'product_links') && !$hasColumn($pdo, $dbName, 'product_links', 'influencer_id')) {
        $pdo->exec(
            "ALTER TABLE product_links ADD COLUMN influencer_id INT UNSIGNED NULL DEFAULT NULL COMMENT '关联达人 influencers.id' AFTER label"
        );
        $pdo->exec('ALTER TABLE product_links ADD INDEX idx_influencer (influencer_id)');
        echo "已为 product_links 添加 influencer_id。\n";
    } else {
        echo "product_links.influencer_id 已存在或表不存在，跳过 ALTER。\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, '迁移失败: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "达人 CRM 迁移完成。\n";
