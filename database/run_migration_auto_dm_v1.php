<?php
/**
 * Auto DM V1 migration (Zalo / WhatsApp unattended messaging).
 *
 * Windows: php database\run_migration_auto_dm_v1.php
 * Linux:   php database/run_migration_auto_dm_v1.php
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

echo "[1/6] Extend influencers for auto DM...\n";
if ($hasTable('influencers')) {
    if (!$hasColumn('influencers', 'do_not_contact')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `do_not_contact` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `contact_confidence`");
        echo "  - influencers.do_not_contact added\n";
    } else {
        echo "  - influencers.do_not_contact exists\n";
    }
    if (!$hasColumn('influencers', 'last_auto_dm_at')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `last_auto_dm_at` DATETIME DEFAULT NULL AFTER `last_commented_at`");
        echo "  - influencers.last_auto_dm_at added\n";
    } else {
        echo "  - influencers.last_auto_dm_at exists\n";
    }
    if (!$hasColumn('influencers', 'auto_dm_fail_count')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `auto_dm_fail_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_auto_dm_at`");
        echo "  - influencers.auto_dm_fail_count added\n";
    } else {
        echo "  - influencers.auto_dm_fail_count exists\n";
    }
    if (!$hasColumn('influencers', 'cooldown_until')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `cooldown_until` DATETIME DEFAULT NULL AFTER `auto_dm_fail_count`");
        echo "  - influencers.cooldown_until added\n";
    } else {
        echo "  - influencers.cooldown_until exists\n";
    }
    if (!$hasIndex('influencers', 'idx_do_not_contact')) {
        $pdo->exec("ALTER TABLE `influencers` ADD INDEX `idx_do_not_contact` (`do_not_contact`)");
        echo "  - influencers.idx_do_not_contact added\n";
    }
    if (!$hasIndex('influencers', 'idx_last_auto_dm_at')) {
        $pdo->exec("ALTER TABLE `influencers` ADD INDEX `idx_last_auto_dm_at` (`last_auto_dm_at`)");
        echo "  - influencers.idx_last_auto_dm_at added\n";
    }
    if (!$hasIndex('influencers', 'idx_cooldown_until')) {
        $pdo->exec("ALTER TABLE `influencers` ADD INDEX `idx_cooldown_until` (`cooldown_until`)");
        echo "  - influencers.idx_cooldown_until added\n";
    }
} else {
    echo "  - influencers table not found, skip\n";
}

echo "[2/6] Extend outreach_logs with action_type...\n";
if ($hasTable('outreach_logs')) {
    if (!$hasColumn('outreach_logs', 'action_type')) {
        $pdo->exec("ALTER TABLE `outreach_logs` ADD COLUMN `action_type` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' AFTER `channel`");
        echo "  - outreach_logs.action_type added\n";
    } else {
        echo "  - outreach_logs.action_type exists\n";
    }
    if (!$hasIndex('outreach_logs', 'idx_action_type_created')) {
        $pdo->exec("ALTER TABLE `outreach_logs` ADD INDEX `idx_action_type_created` (`action_type`, `created_at`)");
        echo "  - outreach_logs.idx_action_type_created added\n";
    }
} else {
    echo "  - outreach_logs table not found, skip\n";
}

echo "[3/6] Create auto_dm_campaigns...\n";
if (!$hasTable('auto_dm_campaigns')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `auto_dm_campaigns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `campaign_name` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `product_id` INT UNSIGNED DEFAULT NULL,
  `campaign_status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0 paused,1 running,2 completed',
  `preferred_channel` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `daily_limit` INT UNSIGNED NOT NULL DEFAULT 80,
  `time_window_start` CHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '09:00',
  `time_window_end` CHAR(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '21:00',
  `min_interval_sec` INT UNSIGNED NOT NULL DEFAULT 90,
  `fail_fuse_threshold` INT UNSIGNED NOT NULL DEFAULT 3,
  `target_filter_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stats_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_targets` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_sent` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_failed` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_blocked` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_replied` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_unsubscribed` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_run_at` DATETIME DEFAULT NULL,
  `paused_at` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`, `campaign_status`, `updated_at`),
  KEY `idx_template_id` (`template_id`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auto DM campaigns';
SQL);
    echo "  - auto_dm_campaigns created\n";
} else {
    echo "  - auto_dm_campaigns exists\n";
}

echo "[4/6] Create auto_dm_tasks...\n";
if (!$hasTable('auto_dm_tasks')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `auto_dm_tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `influencer_id` INT UNSIGNED NOT NULL,
  `task_type` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'zalo_auto_dm/wa_auto_dm',
  `target_channel` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'zalo',
  `idempotency_key` VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `priority` INT NOT NULL DEFAULT 100,
  `task_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 pending,1 assigned,2 sending,3 sent,4 failed,5 blocked,6 cooling',
  `payload_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rendered_text` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_id` INT UNSIGNED DEFAULT NULL,
  `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_retries` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `scheduled_at` DATETIME DEFAULT NULL,
  `assigned_at` DATETIME DEFAULT NULL,
  `sending_at` DATETIME DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `last_error_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_error_message` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_error_at` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_idempotency` (`tenant_id`, `idempotency_key`),
  KEY `idx_campaign_status` (`campaign_id`, `task_status`, `id`),
  KEY `idx_tenant_status` (`tenant_id`, `task_status`, `priority`, `id`),
  KEY `idx_influencer_status` (`influencer_id`, `task_status`),
  KEY `idx_device_status` (`device_id`, `task_status`),
  KEY `idx_scheduled_at` (`scheduled_at`),
  KEY `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auto DM tasks';
SQL);
    echo "  - auto_dm_tasks created\n";
} else {
    echo "  - auto_dm_tasks exists\n";
}

echo "[5/6] Create auto_dm_events...\n";
if (!$hasTable('auto_dm_events')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `auto_dm_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `campaign_id` BIGINT UNSIGNED NOT NULL,
  `task_id` BIGINT UNSIGNED DEFAULT NULL,
  `device_id` INT UNSIGNED DEFAULT NULL,
  `influencer_id` INT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'event',
  `event_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 info,1 success,2 fail',
  `error_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `screenshot_path` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campaign_created` (`campaign_id`, `created_at`),
  KEY `idx_task_created` (`task_id`, `created_at`),
  KEY `idx_influencer_created` (`influencer_id`, `created_at`),
  KEY `idx_event_status` (`event_type`, `event_status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Auto DM events';
SQL);
    echo "  - auto_dm_events created\n";
} else {
    echo "  - auto_dm_events exists\n";
}

echo "[6/6] Create contact_policies + seed default...\n";
if (!$hasTable('contact_policies')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `contact_policies` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `policy_key` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto_dm_default',
  `is_enabled` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `config_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_policy` (`tenant_id`, `policy_key`),
  KEY `idx_policy_key` (`policy_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Contact compliance policies';
SQL);
    echo "  - contact_policies created\n";
} else {
    echo "  - contact_policies exists\n";
}

$defaultPolicyConfig = json_encode([
    'daily_limit' => 80,
    'time_window_start' => '09:00',
    'time_window_end' => '21:00',
    'min_interval_sec' => 90,
    'cooldown_hours' => 24,
    'fail_fuse_threshold' => 3,
    'retry_backoff_sec' => 300,
    'unsubscribe_keywords' => [
        'stop', 'unsubscribe', 'do not contact', 'dont contact',
        'khong lien he', 'dung gui', 'huy dang ky',
        'khong nhan tin', 'khong can', 'huy'
    ],
], JSON_UNESCAPED_UNICODE);
if (!is_string($defaultPolicyConfig) || $defaultPolicyConfig === '') {
    $defaultPolicyConfig = '{}';
}

$tenantIds = [1];
if ($hasTable('tenants')) {
    try {
        $st = $pdo->query('SELECT id FROM `tenants`');
        if ($st) {
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            $tenantIds = [];
            foreach ($rows as $row) {
                $tid = (int) ($row['id'] ?? 0);
                if ($tid > 0) {
                    $tenantIds[] = $tid;
                }
            }
            if ($tenantIds === []) {
                $tenantIds = [1];
            }
        }
    } catch (\Throwable $e) {
        $tenantIds = [1];
    }
}

$policyExistsStmt = $pdo->prepare('SELECT COUNT(*) FROM `contact_policies` WHERE `tenant_id` = ? AND `policy_key` = ?');
$policyInsertStmt = $pdo->prepare('INSERT INTO `contact_policies` (`tenant_id`,`policy_key`,`is_enabled`,`config_json`,`created_at`,`updated_at`) VALUES (?,?,?,?,NOW(),NOW())');
foreach ($tenantIds as $tenantId) {
    $policyExistsStmt->execute([(int) $tenantId, 'auto_dm_default']);
    $exists = (int) $policyExistsStmt->fetchColumn() > 0;
    if ($exists) {
        continue;
    }
    $policyInsertStmt->execute([(int) $tenantId, 'auto_dm_default', 1, $defaultPolicyConfig]);
    echo "  - seeded contact_policies.auto_dm_default for tenant {$tenantId}\n";
}

echo "Done.\n";
