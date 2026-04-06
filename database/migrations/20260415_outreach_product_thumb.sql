-- 达人外联：话术模板表；商品缩略图与 TikTok 商品链
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS message_templates (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '模板名称',
    body MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '正文，占位符 {{tiktok_id}} {{nickname}} {{goods_url}} 等',
    sort_order INT NOT NULL DEFAULT 0,
    status TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status_sort (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='达人联系话术模板';

-- products：缩略图、TikTok Shop/商品链（与 goods_url 并存）
ALTER TABLE products ADD COLUMN thumb_url VARCHAR(512) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '列表缩略图 URL' AFTER goods_url;
ALTER TABLE products ADD COLUMN tiktok_shop_url VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'TikTok 商品/橱窗链接' AFTER thumb_url;
