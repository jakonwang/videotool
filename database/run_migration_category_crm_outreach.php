<?php
/**
 * 分类管理 + 达人 CRM + 话术增强迁移
 *
 * Windows: php database\run_migration_category_crm_outreach.php
 * Linux:   php database/run_migration_category_crm_outreach.php
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
    if (!$hasTable($pdo, $dbName, 'categories')) {
        $pdo->exec("
            CREATE TABLE categories (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '分类名',
                type VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'product|influencer',
                sort_order INT NOT NULL DEFAULT 0,
                status TINYINT UNSIGNED NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_type_name (type, name),
                KEY idx_type_status_sort (type, status, sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品/达人分类';
        ");
        echo "已创建 categories 表。\n";
    } else {
        echo "categories 表已存在，跳过创建。\n";
    }

    if ($hasTable($pdo, $dbName, 'products')) {
        if (!$hasColumn($pdo, $dbName, 'products', 'category_id')) {
            $pdo->exec("ALTER TABLE products ADD COLUMN category_id INT UNSIGNED NULL DEFAULT NULL COMMENT '分类ID' AFTER category_name");
            echo "已添加 products.category_id。\n";
        } else {
            echo "products.category_id 已存在，跳过。\n";
        }
        if (!$hasIndex($pdo, $dbName, 'products', 'idx_category_id')) {
            $pdo->exec("ALTER TABLE products ADD INDEX idx_category_id (category_id)");
            echo "已添加 products.idx_category_id。\n";
        } else {
            echo "products.idx_category_id 已存在，跳过。\n";
        }
    }

    if ($hasTable($pdo, $dbName, 'influencers')) {
        if (!$hasColumn($pdo, $dbName, 'influencers', 'category_id')) {
            $pdo->exec("ALTER TABLE influencers ADD COLUMN category_id INT UNSIGNED NULL DEFAULT NULL COMMENT '分类ID' AFTER category_name");
            echo "已添加 influencers.category_id。\n";
        } else {
            echo "influencers.category_id 已存在，跳过。\n";
        }
        if (!$hasColumn($pdo, $dbName, 'influencers', 'sample_tracking_no')) {
            $pdo->exec("ALTER TABLE influencers ADD COLUMN sample_tracking_no VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '寄样快递单号' AFTER status");
            echo "已添加 influencers.sample_tracking_no。\n";
        } else {
            echo "influencers.sample_tracking_no 已存在，跳过。\n";
        }
        if (!$hasColumn($pdo, $dbName, 'influencers', 'sample_status')) {
            $pdo->exec("ALTER TABLE influencers ADD COLUMN sample_status TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '寄样状态 0未寄 1已寄 2已签收' AFTER sample_tracking_no");
            echo "已添加 influencers.sample_status。\n";
        } else {
            echo "influencers.sample_status 已存在，跳过。\n";
        }
        if (!$hasColumn($pdo, $dbName, 'influencers', 'tags_json')) {
            $pdo->exec("ALTER TABLE influencers ADD COLUMN tags_json TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '标签JSON' AFTER sample_status");
            echo "已添加 influencers.tags_json。\n";
        } else {
            echo "influencers.tags_json 已存在，跳过。\n";
        }
        if (!$hasColumn($pdo, $dbName, 'influencers', 'last_contacted_at')) {
            $pdo->exec("ALTER TABLE influencers ADD COLUMN last_contacted_at DATETIME NULL DEFAULT NULL COMMENT '最后联系时间' AFTER tags_json");
            echo "已添加 influencers.last_contacted_at。\n";
        } else {
            echo "influencers.last_contacted_at 已存在，跳过。\n";
        }
        if (!$hasIndex($pdo, $dbName, 'influencers', 'idx_category_id')) {
            $pdo->exec("ALTER TABLE influencers ADD INDEX idx_category_id (category_id)");
            echo "已添加 influencers.idx_category_id。\n";
        } else {
            echo "influencers.idx_category_id 已存在，跳过。\n";
        }
        if (!$hasIndex($pdo, $dbName, 'influencers', 'idx_last_contacted_at')) {
            $pdo->exec("ALTER TABLE influencers ADD INDEX idx_last_contacted_at (last_contacted_at)");
            echo "已添加 influencers.idx_last_contacted_at。\n";
        } else {
            echo "influencers.idx_last_contacted_at 已存在，跳过。\n";
        }
    }

    if ($hasTable($pdo, $dbName, 'message_templates')) {
        if (!$hasColumn($pdo, $dbName, 'message_templates', 'template_key')) {
            $pdo->exec("ALTER TABLE message_templates ADD COLUMN template_key VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '多语言模板分组键' AFTER name");
            echo "已添加 message_templates.template_key。\n";
        } else {
            echo "message_templates.template_key 已存在，跳过。\n";
        }
        if (!$hasColumn($pdo, $dbName, 'message_templates', 'lang')) {
            $pdo->exec("ALTER TABLE message_templates ADD COLUMN lang VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'zh' COMMENT '语言 zh/en/vi' AFTER template_key");
            echo "已添加 message_templates.lang。\n";
        } else {
            echo "message_templates.lang 已存在，跳过。\n";
        }
        if (!$hasIndex($pdo, $dbName, 'message_templates', 'idx_template_lang')) {
            $pdo->exec("ALTER TABLE message_templates ADD INDEX idx_template_lang (template_key, lang, status)");
            echo "已添加 message_templates.idx_template_lang。\n";
        } else {
            echo "message_templates.idx_template_lang 已存在，跳过。\n";
        }
        $pdo->exec("UPDATE message_templates SET template_key = CONCAT('tpl_', id) WHERE template_key = '' OR template_key IS NULL");
        echo "已完成 message_templates.template_key 数据回填。\n";
    }

    if (!$hasTable($pdo, $dbName, 'outreach_logs')) {
        $pdo->exec("
            CREATE TABLE outreach_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                influencer_id INT UNSIGNED NOT NULL,
                template_id INT UNSIGNED NOT NULL DEFAULT 0,
                template_name VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                template_lang VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'zh',
                product_id INT UNSIGNED NULL DEFAULT NULL,
                product_name VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                channel VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'render',
                rendered_body MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_inf_created (influencer_id, created_at),
                KEY idx_tpl (template_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='达人外联历史记录';
        ");
        echo "已创建 outreach_logs 表。\n";
    } else {
        echo "outreach_logs 表已存在，跳过创建。\n";
    }

    // 根据历史 category_name 自动沉淀分类并回填 category_id
    $pdo->exec("
        INSERT IGNORE INTO categories(name, type, sort_order, status)
        SELECT DISTINCT category_name, 'product', 0, 1
        FROM products
        WHERE category_name IS NOT NULL AND category_name <> ''
    ");
    $pdo->exec("
        INSERT IGNORE INTO categories(name, type, sort_order, status)
        SELECT DISTINCT category_name, 'influencer', 0, 1
        FROM influencers
        WHERE category_name IS NOT NULL AND category_name <> ''
    ");
    $pdo->exec("
        UPDATE products p
        JOIN categories c ON c.type = 'product' AND c.name = p.category_name
        SET p.category_id = c.id
        WHERE (p.category_id IS NULL OR p.category_id = 0)
          AND p.category_name IS NOT NULL AND p.category_name <> ''
    ");
    $pdo->exec("
        UPDATE influencers i
        JOIN categories c ON c.type = 'influencer' AND c.name = i.category_name
        SET i.category_id = c.id
        WHERE (i.category_id IS NULL OR i.category_id = 0)
          AND i.category_name IS NOT NULL AND i.category_name <> ''
    ");
    echo "已完成分类历史数据回填。\n";
} catch (PDOException $e) {
    fwrite(STDERR, '迁移失败: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "分类/达人CRM/话术增强迁移完成。\n";

