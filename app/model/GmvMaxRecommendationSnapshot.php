<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GmvMaxRecommendationSnapshot extends Model
{
    protected $name = 'gmv_max_recommendation_snapshots';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'store_id' => 'int',
        'campaign_id' => 'string',
        'campaign_name' => 'string',
        'snapshot_date' => 'date',
        'baseline_mode' => 'string',
        'sample_level' => 'string',
        'stage' => 'string',
        'main_problem' => 'string',
        'action_level' => 'string',
        'recommendation_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
