-- 系统设置表（可与 run_migration_product_distribution.php 第 7～8 步二选一执行）

CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    skey VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '键',
    svalue TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '值',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_skey (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统设置';

INSERT IGNORE INTO system_settings (skey, svalue) VALUES
('storage', 'qiniu'),
('default_cover_url', ''),
('site_name', '');
