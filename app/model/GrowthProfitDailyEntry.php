<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthProfitDailyEntry extends Model
{
    protected $name = 'growth_profit_daily_entries';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'entry_date' => 'date',
        'store_id' => 'int',
        'account_id' => 'int',
        'channel_type' => 'string',
        'sale_price_cny' => 'float',
        'product_cost_cny' => 'float',
        'cancel_rate' => 'float',
        'platform_fee_rate' => 'float',
        'influencer_commission_rate' => 'float',
        'live_hours' => 'float',
        'wage_hourly_cny' => 'float',
        'wage_cost_cny' => 'float',
        'ad_spend_amount' => 'float',
        'ad_spend_currency' => 'string',
        'ad_spend_cny' => 'float',
        'ad_compensation_amount' => 'float',
        'ad_compensation_currency' => 'string',
        'ad_compensation_cny' => 'float',
        'gmv_amount' => 'float',
        'gmv_currency' => 'string',
        'gmv_cny' => 'float',
        'order_count' => 'int',
        'fx_snapshot_json' => 'string',
        'fx_status' => 'string',
        'roi' => 'float',
        'net_profit_cny' => 'float',
        'break_even_roi' => 'float',
        'per_order_profit_cny' => 'float',
        'raw_metrics_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
