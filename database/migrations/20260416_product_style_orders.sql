SET NAMES utf8mb4;

SET @db := DATABASE();

-- product_style_items.wholesale_price
SET @table_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'product_style_items'
);
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'product_style_items' AND COLUMN_NAME = 'wholesale_price'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `product_style_items` ADD COLUMN `wholesale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT ''批发价'' AFTER `hot_type`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- product_style_items.min_order_qty
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'product_style_items' AND COLUMN_NAME = 'min_order_qty'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `product_style_items` ADD COLUMN `min_order_qty` INT NOT NULL DEFAULT 1 COMMENT ''起批量'' AFTER `wholesale_price`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- product_style_search.wholesale_price
SET @table_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'product_style_search'
);
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'product_style_search' AND COLUMN_NAME = 'wholesale_price'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `product_style_search` ADD COLUMN `wholesale_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT ''批发价'' AFTER `hot_type`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- product_style_search.min_order_qty
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'product_style_search' AND COLUMN_NAME = 'min_order_qty'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `product_style_search` ADD COLUMN `min_order_qty` INT NOT NULL DEFAULT 1 COMMENT ''起批量'' AFTER `wholesale_price`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `offline_orders` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `order_no` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '唯一订单号',
  `customer_info` JSON NOT NULL COMMENT '客户信息JSON（姓名、电话、WhatsApp、Zalo）',
  `items_json` JSON NOT NULL COMMENT '款式明细JSON（style_id、qty、unit_price、subtotal）',
  `total_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT '订单总金额',
  `status` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0待确认 1已转正式订单 2已取消',
  `remark` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '备注',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='线下预定订单';
