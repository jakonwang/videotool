SET NAMES utf8mb4;

-- TikTok 达人 CRM：tiktok_id 存规范化 @handle（小写），全局唯一
CREATE TABLE IF NOT EXISTS influencers (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tiktok_id VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'TikTok 用户名 @handle，唯一',
    nickname VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '昵称',
    avatar_url VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '头像 URL',
    follower_count INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '粉丝数',
    contact_info TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系方式 JSON 文本：whatsapp,email,telegram,raw',
    region VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '地区/国家代码',
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '0待联系 1合作中 2黑名单',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_tiktok_id (tiktok_id),
    KEY idx_status (status),
    KEY idx_region (region),
    KEY idx_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TikTok 达人名录';

CREATE TABLE IF NOT EXISTS influencer_import_tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    status VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending running completed failed',
    file_path VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'runtime 下相对路径',
    file_ext VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'csv',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '有效数据行总数',
    processed_rows INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已处理行数',
    line_idx INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'CSV:0-based 行号；Excel:下一 Excel 行号',
    header_resolved TINYINT UNSIGNED NOT NULL DEFAULT 0,
    header_json TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '表头映射 JSON',
    use_default_header TINYINT UNSIGNED NOT NULL DEFAULT 0,
    inserted_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    logs_json MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
    error_message VARCHAR(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status (status),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='达人 Excel/CSV 异步导入任务';

-- 达人链可选关联达人
ALTER TABLE product_links ADD COLUMN influencer_id INT UNSIGNED NULL DEFAULT NULL COMMENT '关联达人 influencers.id' AFTER label;
ALTER TABLE product_links ADD INDEX idx_influencer (influencer_id);
