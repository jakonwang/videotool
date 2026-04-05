SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS product_style_import_tasks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    status VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending running completed failed',
    file_path VARCHAR(1024) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'runtime 下相对路径或文件名',
    file_ext VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'csv',
    total_rows INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '数据行总数（初始化后写入）',
    processed_rows INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已处理数据行数',
    line_idx INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '下次读取的 0-based 行号',
    header_resolved TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 已解析表头',
    header_json TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '表头列映射 JSON',
    use_default_header TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1 首行即数据（无表头）',
    inserted_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_count INT UNSIGNED NOT NULL DEFAULT 0,
    failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    vision_described_count INT UNSIGNED NOT NULL DEFAULT 0,
    google_synced_count INT UNSIGNED NOT NULL DEFAULT 0,
    google_failed_count INT UNSIGNED NOT NULL DEFAULT 0,
    logs_json MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT 'JSON 数组日志',
    error_message VARCHAR(2000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '失败原因',
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_status (status),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='寻款 CSV 异步导入任务';
