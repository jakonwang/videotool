SET NAMES utf8mb4;

SET @db := DATABASE();

-- product_style_items.price_levels_json
SET @table_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'product_style_items'
);
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'product_style_items' AND COLUMN_NAME = 'price_levels_json'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `product_style_items` ADD COLUMN `price_levels_json` TEXT NULL COMMENT ''分级批发价JSON'' AFTER `min_order_qty`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- product_style_search.price_levels_json (compat)
SET @table_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'product_style_search'
);
SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'product_style_search' AND COLUMN_NAME = 'price_levels_json'
);
SET @sql := IF(
  @table_exists = 1 AND @col_exists = 0,
  'ALTER TABLE `product_style_search` ADD COLUMN `price_levels_json` TEXT NULL COMMENT ''分级批发价JSON'' AFTER `min_order_qty`',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
