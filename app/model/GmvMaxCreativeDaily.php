<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GmvMaxCreativeDaily extends Model
{
    protected $name = 'gmv_max_creative_daily';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'store_id' => 'int',
        'campaign_id' => 'string',
        'campaign_name' => 'string',
        'metric_date' => 'date',
        'date_range' => 'string',
        'video_id' => 'string',
        'title' => 'string',
        'tiktok_account' => 'string',
        'status_text' => 'string',
        'cost' => 'float',
        'sku_orders' => 'int',
        'cost_per_order' => 'float',
        'gross_revenue' => 'float',
        'roi' => 'float',
        'product_ad_impressions' => 'int',
        'product_ad_clicks' => 'int',
        'product_ad_click_rate' => 'float',
        'ad_conversion_rate' => 'float',
        'view_rate_2s' => 'float',
        'view_rate_6s' => 'float',
        'view_rate_25' => 'float',
        'view_rate_50' => 'float',
        'view_rate_75' => 'float',
        'view_rate_100' => 'float',
        'hook_score' => 'string',
        'retention_score' => 'string',
        'conversion_score' => 'string',
        'material_type' => 'string',
        'problem_position' => 'string',
        'diagnosis_json' => 'string',
        'raw_metrics_json' => 'string',
        'source_page' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
