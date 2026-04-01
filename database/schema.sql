-- 设置连接字符集
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- 创建数据库
DROP DATABASE IF EXISTS videotool;
CREATE DATABASE videotool DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE videotool;

-- 确保使用utf8mb4
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- 平台表
CREATE TABLE platforms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '平台名称',
    code VARCHAR(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci UNIQUE NOT NULL COMMENT '平台代码',
    icon VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '平台图标',
    status TINYINT(1) DEFAULT 1 COMMENT '状态 1启用 0禁用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='平台表';

-- 商品表（达人分发按商品归类视频）
CREATE TABLE products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '商品名称',
    goods_url VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商品页外链',
    status TINYINT(1) DEFAULT 1 COMMENT '1启用 0禁用',
    sort_order INT DEFAULT 0 COMMENT '排序，越小越前',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商品表';

-- 达人分发链接（token 对应前台取片页）
CREATE TABLE product_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL COMMENT '商品ID',
    token VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '达人页令牌',
    label VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
    status TINYINT(1) DEFAULT 1 COMMENT '1启用 0禁用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_token (token),
    INDEX idx_product (product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='达人分发链接';

-- 设备表
CREATE TABLE devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    platform_id INT NOT NULL COMMENT '平台ID',
    ip_address VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'IP地址',
    device_name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '设备名称',
    status TINYINT(1) DEFAULT 1 COMMENT '状态 1启用 0禁用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE,
    UNIQUE KEY uk_ip_platform (ip_address, platform_id),
    INDEX idx_platform (platform_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='设备表';

-- 视频表
CREATE TABLE videos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    platform_id INT NOT NULL COMMENT '平台ID',
    device_id INT NULL COMMENT '设备ID，达人素材可不绑定',
    product_id INT NULL COMMENT '所属商品ID',
    title TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '视频标题',
    cover_url TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '封面URL',
    video_url TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '视频URL',
    is_downloaded TINYINT(1) DEFAULT 0 COMMENT '是否已下载 0未下载 1已下载',
    sort_order INT DEFAULT 0 COMMENT '排序',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_device_downloaded (device_id, is_downloaded),
    INDEX idx_product_downloaded (product_id, is_downloaded),
    INDEX idx_platform (platform_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频表';

-- 系统键值设置（存储方式、默认封面等）
CREATE TABLE system_settings (
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

-- 后台管理员账号（用于后台登录）
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '用户名',
    password_hash VARCHAR(255) NOT NULL COMMENT '密码哈希',
    status TINYINT(1) DEFAULT 1 COMMENT '状态 1启用 0禁用',
    last_login_at TIMESTAMP NULL DEFAULT NULL COMMENT '最后登录时间',
    last_login_ip VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '最后登录IP',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_username (username),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='后台管理员';

-- 默认管理员账号（请登录后尽快修改密码）
INSERT IGNORE INTO admin_users (username, password_hash, status)
VALUES ('admin', '$2y$10$6CKQ.7n3GmMLNu7l7tNlsutBdqsRRhIzbh/MHM0rfui0KgGzjtIa.', 1);

-- 下载记录表
CREATE TABLE download_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    video_id INT NOT NULL,
    download_type ENUM('cover', 'video') NOT NULL COMMENT '下载类型',
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    INDEX idx_video (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='下载记录表';

-- 桌面端授权码
CREATE TABLE app_licenses (
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

-- 桌面端版本发布
CREATE TABLE app_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    version VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '版本号',
    release_notes TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '更新说明',
    download_url VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '安装包直链',
    is_mandatory TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否强制更新',
    status TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1发布 0下线',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_version (version),
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='桌面端版本发布';

-- 初始化平台数据
INSERT INTO platforms (name, code, icon) VALUES
('TikTok', 'tiktok', '🎵'),
('虾皮', 'shopee', '🛒');

