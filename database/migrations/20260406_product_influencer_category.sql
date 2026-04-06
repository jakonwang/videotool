SET NAMES utf8mb4;

-- 商品/达人分类字段（支持列表筛选与编辑）
ALTER TABLE products
    ADD COLUMN category_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商品分类' AFTER name;
ALTER TABLE products
    ADD INDEX idx_category_name (category_name);

ALTER TABLE influencers
    ADD COLUMN category_name VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '达人分类' AFTER tiktok_id;
ALTER TABLE influencers
    ADD INDEX idx_category_name (category_name);
