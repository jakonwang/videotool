<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GmvMaxStoreBaseline extends Model
{
    protected $name = 'gmv_max_store_baselines';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'store_id' => 'int',
        'window_days' => 'int',
        'metric_date' => 'date',
        'sample_count' => 'int',
        'total_cost' => 'float',
        'total_orders' => 'int',
        'total_revenue' => 'float',
        'avg_roi' => 'float',
        'avg_ctr' => 'float',
        'avg_cvr' => 'float',
        'p50_json' => 'string',
        'p70_json' => 'string',
        'p90_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
