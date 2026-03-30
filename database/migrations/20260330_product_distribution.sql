-- 商品与达人分发（全局 is_downloaded 核销）
-- 已有库升级：整段执行一次；若提示列/索引已存在可忽略对应语句

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商品名称',
    goods_url VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商品页外链',
    status TINYINT(1) DEFAULT 1 COMMENT '1启用 0禁用',
    sort_order INT DEFAULT 0 COMMENT '排序，越小越前',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品表';

CREATE TABLE IF NOT EXISTS product_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL COMMENT '商品ID',
    token VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '达人页令牌',
    label VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
    status TINYINT(1) DEFAULT 1 COMMENT '1启用 0禁用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_token (token),
    INDEX idx_product (product_id),
    CONSTRAINT fk_pl_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='达人分发链接';

ALTER TABLE videos ADD COLUMN product_id INT NULL COMMENT '所属商品ID' AFTER device_id;
ALTER TABLE videos ADD INDEX idx_product_downloaded (product_id, is_downloaded);
ALTER TABLE videos ADD CONSTRAINT fk_videos_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL;
