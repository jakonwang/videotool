<?php
/**
 * 模块治理迁移：安装日志 / 依赖关系 / 角色权限
 *
 * Windows: php database\run_migration_module_governance.php
 * Linux:   php database/run_migration_module_governance.php
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

try {
    if ($hasTable($pdo, $dbName, 'admin_users') && !$hasColumn($pdo, $dbName, 'admin_users', 'role')) {
        $pdo->exec("ALTER TABLE admin_users ADD COLUMN role VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'super_admin' COMMENT '角色 super_admin/operator/viewer' AFTER username");
        $pdo->exec("UPDATE admin_users SET role = 'super_admin' WHERE role IS NULL OR role = ''");
        echo "已添加 admin_users.role。\n";
    } else {
        echo "admin_users.role 已存在或 admin_users 不存在，跳过。\n";
    }

    if (!$hasTable($pdo, $dbName, 'extension_install_logs')) {
        $pdo->exec("
            CREATE TABLE extension_install_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                extension_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                action VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'install/uninstall/toggle',
                operator_id INT UNSIGNED DEFAULT NULL,
                operator_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                result TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1成功 0失败',
                message VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                detail_json MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_ext_created (extension_name, created_at),
                KEY idx_operator_created (operator_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='模块安装卸载日志';
        ");
        echo "已创建 extension_install_logs 表。\n";
    } else {
        echo "extension_install_logs 表已存在，跳过。\n";
    }

    if (!$hasTable($pdo, $dbName, 'extension_dependencies')) {
        $pdo->exec("
            CREATE TABLE extension_dependencies (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                extension_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                depends_on VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_ext_dep (extension_name, depends_on),
                KEY idx_depends_on (depends_on)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='模块依赖关系';
        ");
        echo "已创建 extension_dependencies 表。\n";
    } else {
        echo "extension_dependencies 表已存在，跳过。\n";
    }

    if (!$hasTable($pdo, $dbName, 'extension_role_permissions')) {
        $pdo->exec("
            CREATE TABLE extension_role_permissions (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                role VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'super_admin/operator/viewer',
                extension_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                can_view TINYINT UNSIGNED NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_role_extension (role, extension_name),
                KEY idx_role (role),
                KEY idx_extension (extension_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='模块角色可见权限';
        ");
        echo "已创建 extension_role_permissions 表。\n";
    } else {
        echo "extension_role_permissions 表已存在，跳过。\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, '迁移失败: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "模块治理迁移完成。\n";

