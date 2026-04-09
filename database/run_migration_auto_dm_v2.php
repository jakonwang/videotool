<?php
/**
 * Auto DM V2 migration (sequence outreach + reply review queue).
 *
 * Windows: php database\run_migration_auto_dm_v2.php
 * Linux:   php database/run_migration_auto_dm_v2.php
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "Please run this script in CLI.\n");
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
    fwrite(STDERR, "Missing mysql connection config.\n");
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
    fwrite(STDERR, 'DB connect failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$pdo->exec('SET NAMES utf8mb4');
$dbName = (string) $mysql['database'];

$hasTable = static function (string $table) use ($pdo, $dbName): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    $st->execute([$dbName, $table]);
    return (int) $st->fetchColumn() > 0;
};

$hasColumn = static function (string $table, string $column) use ($pdo, $dbName): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $st->execute([$dbName, $table, $column]);
    return (int) $st->fetchColumn() > 0;
};

$hasIndex = static function (string $table, string $index) use ($pdo, $dbName): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $st->execute([$dbName, $table, $index]);
    return (int) $st->fetchColumn() > 0;
};

echo "[1/4] Extend auto_dm_campaigns...\n";
if ($hasTable('auto_dm_campaigns')) {
    if (!$hasColumn('auto_dm_campaigns', 'ab_config_json')) {
        $pdo->exec("ALTER TABLE `auto_dm_campaigns` ADD COLUMN `ab_config_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");
        echo "  - auto_dm_campaigns.ab_config_json added\n";
    } else {
        echo "  - auto_dm_campaigns.ab_config_json exists\n";
    }
    if (!$hasColumn('auto_dm_campaigns', 'sequence_config_json')) {
        $pdo->exec("ALTER TABLE `auto_dm_campaigns` ADD COLUMN `sequence_config_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");
        echo "  - auto_dm_campaigns.sequence_config_json added\n";
    } else {
        echo "  - auto_dm_campaigns.sequence_config_json exists\n";
    }
    if (!$hasColumn('auto_dm_campaigns', 'stop_on_reply')) {
        $pdo->exec("ALTER TABLE `auto_dm_campaigns` ADD COLUMN `stop_on_reply` TINYINT UNSIGNED NOT NULL DEFAULT 1");
        echo "  - auto_dm_campaigns.stop_on_reply added\n";
    } else {
        echo "  - auto_dm_campaigns.stop_on_reply exists\n";
    }
    if (!$hasColumn('auto_dm_campaigns', 'reply_confirm_mode')) {
        $pdo->exec("ALTER TABLE `auto_dm_campaigns` ADD COLUMN `reply_confirm_mode` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual'");
        echo "  - auto_dm_campaigns.reply_confirm_mode added\n";
    } else {
        echo "  - auto_dm_campaigns.reply_confirm_mode exists\n";
    }
    if (!$hasIndex('auto_dm_campaigns', 'idx_campaign_reply_mode')) {
        $pdo->exec("ALTER TABLE `auto_dm_campaigns` ADD INDEX `idx_campaign_reply_mode` (`campaign_status`, `reply_confirm_mode`)");
        echo "  - auto_dm_campaigns.idx_campaign_reply_mode added\n";
    }

    $defaultAb = json_encode([
        'enabled' => false,
        'variants' => [
            ['template_id' => 0, 'weight' => 100, 'code' => 'A'],
        ],
        'stable_bucket' => true,
    ], JSON_UNESCAPED_UNICODE);
    if (!is_string($defaultAb) || $defaultAb === '') {
        $defaultAb = '{}';
    }

    $defaultSeq = json_encode([
        'steps' => [
            ['step_no' => 0, 'delay_hours' => 0],
            ['step_no' => 1, 'delay_hours' => 24],
            ['step_no' => 2, 'delay_hours' => 72],
        ],
    ], JSON_UNESCAPED_UNICODE);
    if (!is_string($defaultSeq) || $defaultSeq === '') {
        $defaultSeq = '{}';
    }

    $pdo->exec("UPDATE `auto_dm_campaigns` SET `ab_config_json` = " . $pdo->quote($defaultAb) . " WHERE `ab_config_json` IS NULL OR `ab_config_json` = ''");
    $pdo->exec("UPDATE `auto_dm_campaigns` SET `sequence_config_json` = " . $pdo->quote($defaultSeq) . " WHERE `sequence_config_json` IS NULL OR `sequence_config_json` = ''");
} else {
    echo "  - auto_dm_campaigns not found, skip\n";
}

echo "[2/4] Extend auto_dm_tasks...\n";
if ($hasTable('auto_dm_tasks')) {
    if (!$hasColumn('auto_dm_tasks', 'step_no')) {
        $pdo->exec("ALTER TABLE `auto_dm_tasks` ADD COLUMN `step_no` TINYINT UNSIGNED NOT NULL DEFAULT 0");
        echo "  - auto_dm_tasks.step_no added\n";
    } else {
        echo "  - auto_dm_tasks.step_no exists\n";
    }
    if (!$hasColumn('auto_dm_tasks', 'variant_template_id')) {
        $pdo->exec("ALTER TABLE `auto_dm_tasks` ADD COLUMN `variant_template_id` INT UNSIGNED DEFAULT NULL");
        echo "  - auto_dm_tasks.variant_template_id added\n";
    } else {
        echo "  - auto_dm_tasks.variant_template_id exists\n";
    }
    if (!$hasColumn('auto_dm_tasks', 'reply_state')) {
        $pdo->exec("ALTER TABLE `auto_dm_tasks` ADD COLUMN `reply_state` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 none,1 detected,2 reviewed'");
        echo "  - auto_dm_tasks.reply_state added\n";
    } else {
        echo "  - auto_dm_tasks.reply_state exists\n";
    }
    if (!$hasColumn('auto_dm_tasks', 'reply_text')) {
        $pdo->exec("ALTER TABLE `auto_dm_tasks` ADD COLUMN `reply_text` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");
        echo "  - auto_dm_tasks.reply_text added\n";
    } else {
        echo "  - auto_dm_tasks.reply_text exists\n";
    }
    if (!$hasColumn('auto_dm_tasks', 'reply_at')) {
        $pdo->exec("ALTER TABLE `auto_dm_tasks` ADD COLUMN `reply_at` DATETIME DEFAULT NULL");
        echo "  - auto_dm_tasks.reply_at added\n";
    } else {
        echo "  - auto_dm_tasks.reply_at exists\n";
    }
    if (!$hasColumn('auto_dm_tasks', 'next_execute_at')) {
        $pdo->exec("ALTER TABLE `auto_dm_tasks` ADD COLUMN `next_execute_at` DATETIME DEFAULT NULL");
        echo "  - auto_dm_tasks.next_execute_at added\n";
    } else {
        echo "  - auto_dm_tasks.next_execute_at exists\n";
    }
    if (!$hasColumn('auto_dm_tasks', 'delivery_status')) {
        $pdo->exec("ALTER TABLE `auto_dm_tasks` ADD COLUMN `delivery_status` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");
        echo "  - auto_dm_tasks.delivery_status added\n";
    } else {
        echo "  - auto_dm_tasks.delivery_status exists\n";
    }
    if (!$hasColumn('auto_dm_tasks', 'conversation_snippet')) {
        $pdo->exec("ALTER TABLE `auto_dm_tasks` ADD COLUMN `conversation_snippet` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL");
        echo "  - auto_dm_tasks.conversation_snippet added\n";
    } else {
        echo "  - auto_dm_tasks.conversation_snippet exists\n";
    }

    if (!$hasIndex('auto_dm_tasks', 'idx_campaign_step_status')) {
        $pdo->exec("ALTER TABLE `auto_dm_tasks` ADD INDEX `idx_campaign_step_status` (`campaign_id`, `step_no`, `task_status`, `id`)");
        echo "  - auto_dm_tasks.idx_campaign_step_status added\n";
    }
    if (!$hasIndex('auto_dm_tasks', 'idx_reply_state')) {
        $pdo->exec("ALTER TABLE `auto_dm_tasks` ADD INDEX `idx_reply_state` (`reply_state`, `reply_at`)");
        echo "  - auto_dm_tasks.idx_reply_state added\n";
    }
    if (!$hasIndex('auto_dm_tasks', 'idx_next_execute_at')) {
        $pdo->exec("ALTER TABLE `auto_dm_tasks` ADD INDEX `idx_next_execute_at` (`next_execute_at`)");
        echo "  - auto_dm_tasks.idx_next_execute_at added\n";
    }
} else {
    echo "  - auto_dm_tasks not found, skip\n";
}

echo "[3/4] Create auto_dm_reply_reviews...\n";
if (!$hasTable('auto_dm_reply_reviews')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `auto_dm_reply_reviews` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `influencer_id` INT UNSIGNED NOT NULL,
  `channel` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'zalo',
  `step_no` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `reply_text` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rule_category` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `confirm_category` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `transition_target_status` TINYINT UNSIGNED DEFAULT NULL,
  `confirm_note` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confirmed_by` INT UNSIGNED DEFAULT NULL,
  `confirmed_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_reply_review_task` (`tenant_id`, `task_id`),
  KEY `idx_campaign_confirmed` (`campaign_id`, `confirmed_at`),
  KEY `idx_influencer_confirmed` (`influencer_id`, `confirmed_at`),
  KEY `idx_confirm_category` (`confirm_category`, `confirmed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auto DM reply review decisions';
SQL);
    echo "  - auto_dm_reply_reviews created\n";
} else {
    echo "  - auto_dm_reply_reviews exists\n";
}

echo "[4/4] Ensure outreach_logs action_type index...\n";
if ($hasTable('outreach_logs')) {
    if (!$hasColumn('outreach_logs', 'action_type')) {
        $pdo->exec("ALTER TABLE `outreach_logs` ADD COLUMN `action_type` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''");
        echo "  - outreach_logs.action_type added\n";
    } else {
        echo "  - outreach_logs.action_type exists\n";
    }
    if (!$hasIndex('outreach_logs', 'idx_action_type_created')) {
        $pdo->exec("ALTER TABLE `outreach_logs` ADD INDEX `idx_action_type_created` (`action_type`, `created_at`)");
        echo "  - outreach_logs.idx_action_type_created added\n";
    }
} else {
    echo "  - outreach_logs not found, skip\n";
}

echo "Done.\n";
