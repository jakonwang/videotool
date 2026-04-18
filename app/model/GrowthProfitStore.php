<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthProfitStore extends Model
{
    protected $name = 'growth_profit_stores';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'store_code' => 'string',
        'store_name' => 'string',
        'default_sale_price_cny' => 'float',
        'default_product_cost_cny' => 'float',
        'default_cancel_rate_live' => 'float',
        'default_cancel_rate_video' => 'float',
        'default_cancel_rate_influencer' => 'float',
        'default_cancel_rate' => 'float',
        'default_platform_fee_rate' => 'float',
        'default_influencer_commission_rate' => 'float',
        'default_live_wage_hourly_cny' => 'float',
        'default_timezone' => 'string',
        'default_gmv_currency' => 'string',
        'status' => 'int',
        'notes' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
