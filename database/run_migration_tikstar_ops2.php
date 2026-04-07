<?php
/**
 * TikStar OPS 2.0 migration
 *
 * Windows: php database\run_migration_tikstar_ops2.php
 * Linux:   php database/run_migration_tikstar_ops2.php
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
    fwrite(STDERR, "mysql connection config not found.\n");
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
    fwrite(STDERR, "DB connect failed: {$e->getMessage()}\n");
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

try {
    if (!$hasTable('influencer_status_logs')) {
        $pdo->exec("
            CREATE TABLE influencer_status_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                influencer_id INT UNSIGNED NOT NULL,
                from_status TINYINT UNSIGNED NOT NULL DEFAULT 0,
                to_status TINYINT UNSIGNED NOT NULL DEFAULT 0,
                source VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
                note VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                context_json TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_influencer_created (influencer_id, created_at),
                KEY idx_source_created (source, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Influencer status transition logs'
        ");
        echo "Created table influencer_status_logs.\n";
    } else {
        echo "Table influencer_status_logs already exists, skip.\n";
    }

    if (!$hasTable('influencer_outreach_tasks')) {
        $pdo->exec("
            CREATE TABLE influencer_outreach_tasks (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                influencer_id INT UNSIGNED NOT NULL,
                template_id INT UNSIGNED DEFAULT NULL,
                product_id INT UNSIGNED DEFAULT NULL,
                task_status TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 pending,1 copied,2 jumped,3 completed,4 skipped',
                priority INT NOT NULL DEFAULT 0,
                due_at DATETIME DEFAULT NULL,
                assigned_to INT UNSIGNED DEFAULT NULL,
                last_action_at DATETIME DEFAULT NULL,
                source_filter_json TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                payload_json TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_status_priority (task_status, priority, id),
                KEY idx_influencer_status (influencer_id, task_status),
                KEY idx_assignee_status (assigned_to, task_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Influencer outreach task queue'
        ");
        echo "Created table influencer_outreach_tasks.\n";
    } else {
        echo "Table influencer_outreach_tasks already exists, skip.\n";
    }

    if (!$hasTable('sample_shipments')) {
        $pdo->exec("
            CREATE TABLE sample_shipments (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                influencer_id INT UNSIGNED NOT NULL,
                tracking_no VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                courier VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                shipment_status TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 pending,1 shipping,2 delivered,3 exception',
                receipt_status TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 unknown,1 received,2 rejected',
                shipped_at DATETIME DEFAULT NULL,
                received_at DATETIME DEFAULT NULL,
                receipt_note VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_influencer_status (influencer_id, shipment_status),
                KEY idx_tracking_no (tracking_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sample shipping SOP records'
        ");
        echo "Created table sample_shipments.\n";
    } else {
        echo "Table sample_shipments already exists, skip.\n";
    }

    if (!$hasTable('data_sources')) {
        $pdo->exec("
            CREATE TABLE data_sources (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                code VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                name VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                source_type VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'csv',
                adapter_key VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                status TINYINT UNSIGNED NOT NULL DEFAULT 1,
                config_json TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_code (code),
                KEY idx_status_type (status, source_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pluggable data source registry'
        ");
        echo "Created table data_sources.\n";
    } else {
        echo "Table data_sources already exists, skip.\n";
    }

    if (!$hasTable('import_jobs')) {
        $pdo->exec("
            CREATE TABLE import_jobs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                source_id INT UNSIGNED DEFAULT NULL,
                domain VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generic',
                job_type VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'csv',
                file_name VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                status TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 queued,1 running,2 success,3 failed,4 partial',
                total_rows INT UNSIGNED NOT NULL DEFAULT 0,
                success_rows INT UNSIGNED NOT NULL DEFAULT 0,
                failed_rows INT UNSIGNED NOT NULL DEFAULT 0,
                error_message VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                payload_json TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                started_at DATETIME DEFAULT NULL,
                finished_at DATETIME DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_domain_status (domain, status),
                KEY idx_source_status (source_id, status),
                KEY idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Generic import jobs'
        ");
        echo "Created table import_jobs.\n";
    } else {
        echo "Table import_jobs already exists, skip.\n";
    }

    if (!$hasTable('import_job_logs')) {
        $pdo->exec("
            CREATE TABLE import_job_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                job_id BIGINT UNSIGNED NOT NULL,
                level VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'info',
                message VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                context_json TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_job_created (job_id, created_at),
                KEY idx_level_created (level, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Generic import job logs'
        ");
        echo "Created table import_job_logs.\n";
    } else {
        echo "Table import_job_logs already exists, skip.\n";
    }

    if (!$hasTable('growth_industry_metrics')) {
        $pdo->exec("
            CREATE TABLE growth_industry_metrics (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                metric_date DATE NOT NULL,
                country_code VARCHAR(12) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                category_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                heat_score DECIMAL(10,2) NOT NULL DEFAULT 0,
                content_count INT UNSIGNED NOT NULL DEFAULT 0,
                engagement_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
                cpc DECIMAL(10,4) NOT NULL DEFAULT 0,
                cpm DECIMAL(10,4) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_date_country_category (metric_date, country_code, category_name),
                KEY idx_country_date (country_code, metric_date),
                KEY idx_category_date (category_name, metric_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Industry trend metrics'
        ");
        echo "Created table growth_industry_metrics.\n";
    } else {
        echo "Table growth_industry_metrics already exists, skip.\n";
    }

    if (!$hasTable('growth_competitors')) {
        $pdo->exec("
            CREATE TABLE growth_competitors (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                platform VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tiktok',
                region VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                category_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                status TINYINT UNSIGNED NOT NULL DEFAULT 1,
                notes VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_name_platform (name, platform),
                KEY idx_status_platform (status, platform)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='Competitor entities'
        ");
        echo "Created table growth_competitors.\n";
    } else {
        echo "Table growth_competitors already exists, skip.\n";
    }

    if (!$hasTable('growth_competitor_metrics')) {
        $pdo->exec("
            CREATE TABLE growth_competitor_metrics (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                competitor_id INT UNSIGNED NOT NULL,
                metric_date DATE NOT NULL,
                followers INT UNSIGNED NOT NULL DEFAULT 0,
                engagement_rate DECIMAL(8,4) NOT NULL DEFAULT 0,
                content_count INT UNSIGNED NOT NULL DEFAULT 0,
                conversion_proxy DECIMAL(10,4) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_competitor_date (competitor_id, metric_date),
                KEY idx_metric_date (metric_date),
                KEY idx_follower (followers)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Competitor daily metrics'
        ");
        echo "Created table growth_competitor_metrics.\n";
    } else {
        echo "Table growth_competitor_metrics already exists, skip.\n";
    }

    if (!$hasTable('growth_ad_creatives')) {
        $pdo->exec("
            CREATE TABLE growth_ad_creatives (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                creative_code VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
                platform VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tiktok',
                region VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                category_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                landing_url VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                first_seen_at DATE DEFAULT NULL,
                last_seen_at DATE DEFAULT NULL,
                status TINYINT UNSIGNED NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_creative_code (creative_code),
                KEY idx_status_platform (status, platform),
                KEY idx_seen_range (first_seen_at, last_seen_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='Ad creatives library'
        ");
        echo "Created table growth_ad_creatives.\n";
    } else {
        echo "Table growth_ad_creatives already exists, skip.\n";
    }

    if (!$hasTable('growth_ad_metrics')) {
        $pdo->exec("
            CREATE TABLE growth_ad_metrics (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                creative_id BIGINT UNSIGNED NOT NULL,
                metric_date DATE NOT NULL,
                impressions BIGINT UNSIGNED NOT NULL DEFAULT 0,
                clicks BIGINT UNSIGNED NOT NULL DEFAULT 0,
                ctr DECIMAL(8,4) NOT NULL DEFAULT 0,
                cpc DECIMAL(10,4) NOT NULL DEFAULT 0,
                cpm DECIMAL(10,4) NOT NULL DEFAULT 0,
                est_spend DECIMAL(12,2) NOT NULL DEFAULT 0,
                active_days INT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_creative_date (creative_id, metric_date),
                KEY idx_metric_date (metric_date),
                KEY idx_est_spend (est_spend)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT='Ad daily metrics'
        ");
        echo "Created table growth_ad_metrics.\n";
    } else {
        echo "Table growth_ad_metrics already exists, skip.\n";
    }

    if ($hasTable('influencers')) {
        if (!$hasIndex('influencers', 'idx_status_updated')) {
            $pdo->exec("ALTER TABLE influencers ADD INDEX idx_status_updated (status, updated_at)");
            echo "Added influencers.idx_status_updated.\n";
        } else {
            echo "Index influencers.idx_status_updated already exists, skip.\n";
        }
    }

    echo "TikStar OPS 2.0 migration completed.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Migration failed: {$e->getMessage()}\n");
    exit(1);
}

