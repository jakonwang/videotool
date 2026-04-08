<?php
/**
 * Mobile outreach (Android + Appium agent) migration.
 *
 * Windows: php database\run_migration_mobile_outreach.php
 * Linux:   php database/run_migration_mobile_outreach.php
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

echo "[1/4] Extend influencers columns...\n";
if ($hasTable('influencers')) {
    if (!$hasColumn('influencers', 'last_commented_at')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `last_commented_at` DATETIME DEFAULT NULL AFTER `last_contacted_at`");
        echo "  - influencers.last_commented_at added\n";
    } else {
        echo "  - influencers.last_commented_at exists\n";
    }
    if (!$hasColumn('influencers', 'quality_score')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `quality_score` DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER `last_commented_at`");
        echo "  - influencers.quality_score added\n";
    } else {
        echo "  - influencers.quality_score exists\n";
    }
    if (!$hasColumn('influencers', 'quality_grade')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `quality_grade` VARCHAR(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'C' AFTER `quality_score`");
        echo "  - influencers.quality_grade added\n";
    } else {
        echo "  - influencers.quality_grade exists\n";
    }
    if (!$hasColumn('influencers', 'contact_confidence')) {
        $pdo->exec("ALTER TABLE `influencers` ADD COLUMN `contact_confidence` DECIMAL(6,2) NOT NULL DEFAULT 0.00 AFTER `quality_grade`");
        echo "  - influencers.contact_confidence added\n";
    } else {
        echo "  - influencers.contact_confidence exists\n";
    }
    if (!$hasIndex('influencers', 'idx_last_commented_at')) {
        $pdo->exec("ALTER TABLE `influencers` ADD INDEX `idx_last_commented_at` (`last_commented_at`)");
        echo "  - influencers.idx_last_commented_at added\n";
    }
    if (!$hasIndex('influencers', 'idx_quality_grade')) {
        $pdo->exec("ALTER TABLE `influencers` ADD INDEX `idx_quality_grade` (`quality_grade`, `quality_score`)");
        echo "  - influencers.idx_quality_grade added\n";
    }
    if ($hasColumn('influencers', 'tenant_id') && !$hasIndex('influencers', 'idx_tenant_last_commented')) {
        $pdo->exec("ALTER TABLE `influencers` ADD INDEX `idx_tenant_last_commented` (`tenant_id`, `last_commented_at`)");
        echo "  - influencers.idx_tenant_last_commented added\n";
    }
} else {
    echo "  - influencers table not found, skip\n";
}

echo "[2/4] Create mobile_devices...\n";
if (!$hasTable('mobile_devices')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `mobile_devices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `device_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_name` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `device_serial` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'android',
  `agent_token` VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_online` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `heartbeat_at` DATETIME DEFAULT NULL,
  `last_seen_at` DATETIME DEFAULT NULL,
  `daily_quota` INT UNSIGNED NOT NULL DEFAULT 120,
  `daily_used` INT UNSIGNED NOT NULL DEFAULT 0,
  `fail_streak` INT UNSIGNED NOT NULL DEFAULT 0,
  `cooldown_until` DATETIME DEFAULT NULL,
  `capability_json` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remark` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_device_code` (`tenant_id`, `device_code`),
  KEY `idx_online_status` (`status`, `is_online`),
  KEY `idx_cooldown` (`cooldown_until`),
  KEY `idx_heartbeat` (`heartbeat_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mobile agent devices';
SQL);
    echo "  - mobile_devices created\n";
} else {
    echo "  - mobile_devices exists\n";
}

echo "[3/4] Create mobile_action_tasks...\n";
if (!$hasTable('mobile_action_tasks')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `mobile_action_tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `influencer_id` INT UNSIGNED NOT NULL,
  `task_type` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'comment_warmup/tiktok_dm/zalo_im/wa_im',
  `target_channel` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'auto',
  `priority` INT NOT NULL DEFAULT 0,
  `task_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 pending,1 assigned,2 prepared,3 done,4 failed,5 skipped,6 canceled',
  `payload_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rendered_text` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_id` INT UNSIGNED DEFAULT NULL,
  `retry_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `max_retries` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `scheduled_at` DATETIME DEFAULT NULL,
  `assigned_at` DATETIME DEFAULT NULL,
  `prepared_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `last_error_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_error_message` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_error_at` DATETIME DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_priority` (`task_status`, `priority`, `id`),
  KEY `idx_influencer_status` (`influencer_id`, `task_status`),
  KEY `idx_device_status` (`device_id`, `task_status`),
  KEY `idx_channel_status` (`target_channel`, `task_status`),
  KEY `idx_tenant_created` (`tenant_id`, `created_at`),
  KEY `idx_schedule` (`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mobile outreach action tasks';
SQL);
    echo "  - mobile_action_tasks created\n";
} else {
    echo "  - mobile_action_tasks exists\n";
}

echo "[4/4] Create mobile_action_logs...\n";
if (!$hasTable('mobile_action_logs')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `mobile_action_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `device_id` INT UNSIGNED DEFAULT NULL,
  `influencer_id` INT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'report',
  `event_status` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 info,1 success,2 fail',
  `error_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
  `screenshot_path` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_task_created` (`task_id`, `created_at`),
  KEY `idx_device_created` (`device_id`, `created_at`),
  KEY `idx_influencer_created` (`influencer_id`, `created_at`),
  KEY `idx_event_status` (`event_type`, `event_status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Mobile outreach action logs';
SQL);
    echo "  - mobile_action_logs created\n";
} else {
    echo "  - mobile_action_logs exists\n";
}

echo "Done.\n";
