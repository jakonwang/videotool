-- 达人素材：视频可不绑定设备（device_id 可空）
-- 若已执行过 run_migration_product_distribution.php 则通常已包含本步；单独执行亦可

ALTER TABLE videos MODIFY device_id INT NULL COMMENT '设备ID，达人素材可不绑定';
