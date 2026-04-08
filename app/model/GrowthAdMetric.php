<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthAdMetric extends Model
{
    protected $name = 'growth_ad_metrics';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'creative_id' => 'int',
        'metric_date' => 'date',
        'impressions' => 'int',
        'clicks' => 'int',
        'ctr' => 'float',
        'cpc' => 'float',
        'cpm' => 'float',
        'est_spend' => 'float',
        'est_gmv' => 'float',
        'active_days' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
