<?php
/**
 * 后台管理员账号表增量更新（可重复执行）
 *
 * 使用项目 config/database.php 中的 MySQL 连接信息。
 *
 * Windows（PowerShell，在项目根目录 videotool 下）:
 *   php database\run_migration_admin_users.php
 *
 * Linux:
 *   php database/run_migration_admin_users.php
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

echo "[1/2] 创建表 admin_users（若不存在）…\n";
$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
    password_hash VARCHAR(255) NOT NULL COMMENT '密码哈希',
    status TINYINT(1) DEFAULT 1 COMMENT '状态 1启用 0禁用',
    last_login_at TIMESTAMP NULL DEFAULT NULL COMMENT '最后登录时间',
    last_login_ip VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '最后登录IP',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台管理员'
SQL);
echo "      完成。\n";

echo "[2/2] 初始化默认管理员账号（若不存在）…\n";
$defaultUser = 'admin';
$defaultPass = 'admin123';
$hash = password_hash($defaultPass, PASSWORD_BCRYPT);
$st = $pdo->prepare('INSERT IGNORE INTO admin_users (username, password_hash, status) VALUES (?, ?, 1)');
$st->execute([$defaultUser, $hash]);
echo "      完成（默认账号：admin / admin123；请登录后尽快修改）。\n";

echo "\n全部处理完毕。\n";

