-- 桌面端：授权码与版本发布（可与 run_migration_client_app.php 配合增量执行）
-- 字符集与项目一致

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS app_licenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    license_key VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '授权码',
    machine_id VARCHAR(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '绑定机器标识',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
    expire_time DATETIME DEFAULT NULL COMMENT '到期时间，NULL 表示不限',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_license_key (license_key),
    INDEX idx_status (status),
    INDEX idx_machine (machine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='桌面端授权码';

CREATE TABLE IF NOT EXISTS app_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    version VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '版本号，如 1.0.1',
    release_notes TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '更新说明',
    download_url VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '安装包直链',
    is_mandatory TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否强制更新 1是 0否',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1发布 0下线',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_version (version),
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='桌面端版本发布';
