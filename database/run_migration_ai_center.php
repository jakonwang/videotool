<?php
/**
 * AI Command Center migration.
 *
 * Windows: php database\run_migration_ai_center.php
 * Linux:   php database/run_migration_ai_center.php
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

$hasIndex = static function (string $table, string $index) use ($pdo, $dbName): bool {
    $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $st->execute([$dbName, $table, $index]);
    return (int) $st->fetchColumn() > 0;
};

echo "[1/4] ai_sessions ...\n";
if (!$hasTable('ai_sessions')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `ai_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `store_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `session_type` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'diagnose',
  `title` VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` VARCHAR(24) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `context_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_user_message` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_ai_message` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_store_status` (`tenant_id`,`store_id`,`status`),
  KEY `idx_tenant_updated` (`tenant_id`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI command center sessions';
SQL);
    echo "  - created\n";
} else {
    echo "  - exists\n";
}

echo "[2/4] ai_decisions ...\n";
if (!$hasTable('ai_decisions')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `ai_decisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `session_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `decision_type` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'chat',
  `input_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `output_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `confidence` DECIMAL(6,4) NOT NULL DEFAULT 0.5000,
  `risk_level` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `evidence_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requires_human_approval` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_session` (`tenant_id`,`session_id`),
  KEY `idx_tenant_type_created` (`tenant_id`,`decision_type`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI decision snapshots';
SQL);
    echo "  - created\n";
} else {
    echo "  - exists\n";
}

echo "[3/4] ai_action_plans ...\n";
if (!$hasTable('ai_action_plans')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `ai_action_plans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `session_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `decision_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `store_id` INT UNSIGNED NOT NULL DEFAULT 0,
  `campaign_id` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `plan_code` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `title` VARCHAR(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `objective` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `risk_level` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'medium',
  `requires_human_approval` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `owner_role` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'operator',
  `due_at` DATETIME DEFAULT NULL,
  `expected_kpi_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `plan_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_plan_code` (`tenant_id`,`plan_code`),
  KEY `idx_tenant_store_status` (`tenant_id`,`store_id`,`status`),
  KEY `idx_tenant_due_at` (`tenant_id`,`due_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI structured action plans';
SQL);
    echo "  - created\n";
} else {
    echo "  - exists\n";
}

echo "[4/4] ai_feedback_events ...\n";
if (!$hasTable('ai_feedback_events')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE `ai_feedback_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL DEFAULT 1,
  `session_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `plan_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `decision_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `event_type` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'result',
  `event_payload_json` MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` INT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_plan_created` (`tenant_id`,`plan_id`,`created_at`),
  KEY `idx_tenant_session_created` (`tenant_id`,`session_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI execution feedback events';
SQL);
    echo "  - created\n";
} else {
    echo "  - exists\n";
}

$ensureIndex = static function (string $table, string $name, string $sql) use ($pdo, $hasTable, $hasIndex): void {
    if ($hasTable($table) && !$hasIndex($table, $name)) {
        $pdo->exec($sql);
        echo "  - {$table}.{$name} added\n";
    }
};

$ensureIndex('ai_sessions', 'idx_tenant_store_status', 'ALTER TABLE `ai_sessions` ADD INDEX `idx_tenant_store_status` (`tenant_id`,`store_id`,`status`)');
$ensureIndex('ai_decisions', 'idx_tenant_session', 'ALTER TABLE `ai_decisions` ADD INDEX `idx_tenant_session` (`tenant_id`,`session_id`)');
$ensureIndex('ai_action_plans', 'idx_tenant_store_status', 'ALTER TABLE `ai_action_plans` ADD INDEX `idx_tenant_store_status` (`tenant_id`,`store_id`,`status`)');
$ensureIndex('ai_feedback_events', 'idx_tenant_plan_created', 'ALTER TABLE `ai_feedback_events` ADD INDEX `idx_tenant_plan_created` (`tenant_id`,`plan_id`,`created_at`)');

echo "Done.\n";

