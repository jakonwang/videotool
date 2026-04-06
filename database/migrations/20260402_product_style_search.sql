-- 图片搜款式：款式索引表（向量 JSON + 元数据）
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS product_style_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_code VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '产品编号',
    image_ref VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '参考图 URL 或站内路径',
    hot_type VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '爆款类型',
    ai_description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'OpenAI Vision 视觉特征描述',
    embedding MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '特征向量 JSON 数组',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1有效 0停用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_product_code (product_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='款式图搜索引';
