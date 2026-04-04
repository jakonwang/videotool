-- 寻款索引：product_code 全局唯一（已有库升级）
-- 若表中已存在重复 product_code，须先合并或删除重复行后再执行。
-- 推荐用 CLI 脚本自动校验：php database/run_migration_product_style_unique_code.php

ALTER TABLE product_style_items ADD UNIQUE KEY uk_product_code (product_code);
