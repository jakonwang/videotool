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
    device_id INT NOT NULL COMMENT '设备ID',
    title TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '视频标题',
    cover_url TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '封面URL',
    video_url TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '视频URL',
    is_downloaded TINYINT(1) DEFAULT 0 COMMENT '是否已下载 0未下载 1已下载',
    sort_order INT DEFAULT 0 COMMENT '排序',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (platform_id) REFERENCES platforms(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE CASCADE,
    INDEX idx_device_downloaded (device_id, is_downloaded),
    INDEX idx_platform (platform_id),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='视频表';

-- 下载记录表
CREATE TABLE download_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    video_id INT NOT NULL,
    download_type ENUM('cover', 'video') NOT NULL COMMENT '下载类型',
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (video_id) REFERENCES videos(id) ON DELETE CASCADE,
    INDEX idx_video (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='下载记录表';

-- 初始化平台数据
INSERT INTO platforms (name, code, icon) VALUES
('TikTok', 'tiktok', '🎵'),
('虾皮', 'shopee', '🛒');

