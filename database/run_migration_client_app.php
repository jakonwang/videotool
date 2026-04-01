<?php
/**
 * 桌面端授权码 / 版本发布表（可重复执行：已存在的表会跳过）
 *
 * Windows（PowerShell，项目根目录 videotool）:
 *   php database\run_migration_client_app.php
 *
 * Linux:
 *   php database/run_migration_client_app.php
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

echo "[1/2] 创建表 app_licenses（若不存在）…\n";
if (!$hasTable($pdo, $dbName, 'app_licenses')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE app_licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '授权码',
    machine_id VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '绑定机器标识',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    expire_time DATETIME DEFAULT NULL COMMENT '到期时间，NULL 表示不限',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_license_key (license_key),
    INDEX idx_status (status),
    INDEX idx_machine (machine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='桌面端授权码'
SQL);
    echo "      已创建。\n";
} else {
    echo "      已存在，跳过。\n";
}

echo "[2/2] 创建表 app_versions（若不存在）…\n";
if (!$hasTable($pdo, $dbName, 'app_versions')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE app_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    version VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '版本号，如 1.0.1',
    release_notes TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '更新说明',
    download_url VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '安装包直链',
    is_mandatory TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否强制更新 1是 0否',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1发布 0下线',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_version (version),
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='桌面端版本发布'
SQL);
    echo "      已创建。\n";
} else {
    echo "      已存在，跳过。\n";
}

echo "\n全部处理完毕。后台可使用「发卡」「版本」菜单；公开下载页：/index.php/download\n";
