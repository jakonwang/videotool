<?php
/**
 * 模块管理（extensions）迁移
 *
 * Windows: php database\run_migration_extensions.php
 * Linux:   php database/run_migration_extensions.php
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

try {
    if (!$hasTable($pdo, $dbName, 'extensions')) {
        $pdo->exec("
            CREATE TABLE extensions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '模块唯一标识',
                title VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '显示名称',
                version VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '1.0.0' COMMENT '版本号',
                is_enabled TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '是否启用 1是 0否',
                config_json TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '扩展配置JSON',
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_extension_name (name),
                KEY idx_extension_enabled (is_enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='模块扩展表';
        ");
        echo "已创建 extensions 表。\n";
    } else {
        echo "extensions 表已存在，跳过创建。\n";
    }

    $seedRows = [
        ['overview', '概览', '1.0.0', 1, '{"can_uninstall":0}'],
        ['style_search', '寻款', '1.0.0', 1, '{"can_uninstall":0}'],
        ['creator_crm', '达人CRM', '1.0.0', 1, '{"can_uninstall":0}'],
        ['material_distribution', '素材分发', '1.0.0', 1, '{"can_uninstall":0}'],
        ['terminal_devices', '终端设备', '1.0.0', 1, '{"can_uninstall":0}'],
        ['system_ops', '系统管理', '1.0.0', 1, '{"can_uninstall":0}'],
    ];
    $ins = $pdo->prepare("
        INSERT INTO extensions(name, title, version, is_enabled, config_json)
        VALUES(?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            version = VALUES(version),
            updated_at = CURRENT_TIMESTAMP
    ");
    foreach ($seedRows as $r) {
        $ins->execute($r);
    }
    echo "已完成内置模块初始化。\n";
} catch (PDOException $e) {
    fwrite(STDERR, '迁移失败: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "模块管理迁移完成。\n";

