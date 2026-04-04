-- 寻款 OpenAI Vision：产品表与索引表增加 ai_description
SET NAMES utf8mb4;

ALTER TABLE products
    ADD COLUMN ai_description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'OpenAI Vision 耳环视觉特征' AFTER goods_url;

ALTER TABLE product_style_items
    ADD COLUMN ai_description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'OpenAI Vision 视觉特征描述' AFTER hot_type;
