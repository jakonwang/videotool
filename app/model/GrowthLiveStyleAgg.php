<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthLiveStyleAgg extends Model
{
    protected $name = 'growth_live_style_agg';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'scope' => 'string',
        'store_id' => 'int',
        'window_type' => 'string',
        'window_start' => 'date',
        'window_end' => 'date',
        'anchor_session_id' => 'int',
        'style_code' => 'string',
        'image_url' => 'string',
        'product_count' => 'int',
        'session_count' => 'int',
        'gmv_sum' => 'float',
        'impressions_sum' => 'int',
        'clicks_sum' => 'int',
        'add_to_cart_sum' => 'int',
        'orders_sum' => 'int',
        'ctr' => 'float',
        'add_to_cart_rate' => 'float',
        'pay_cvr' => 'float',
        'score' => 'float',
        'tier' => 'string',
        'ranking' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
