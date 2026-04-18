<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthLiveProductMetric extends Model
{
    protected $name = 'growth_live_product_metrics';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'session_id' => 'int',
        'store_id' => 'int',
        'session_date' => 'date',
        'session_name' => 'string',
        'product_id' => 'string',
        'product_name' => 'string',
        'extracted_style_code' => 'string',
        'catalog_id' => 'int',
        'catalog_style_code' => 'string',
        'image_url' => 'string',
        'is_matched' => 'int',
        'gmv' => 'float',
        'items_sold' => 'int',
        'customers' => 'int',
        'created_sku_orders' => 'int',
        'sku_orders' => 'int',
        'orders_count' => 'int',
        'impressions' => 'int',
        'clicks' => 'int',
        'add_to_cart_count' => 'int',
        'payment_rate' => 'float',
        'ctr' => 'float',
        'add_to_cart_rate' => 'float',
        'pay_cvr' => 'float',
        'ctor_sku' => 'float',
        'ctor' => 'float',
        'watch_gpm' => 'float',
        'aov' => 'float',
        'available_stock' => 'int',
        'raw_payload_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
