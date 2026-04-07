<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthCompetitorMetric extends Model
{
    protected $name = 'growth_competitor_metrics';

    protected $schema = [
        'id' => 'int',
        'competitor_id' => 'int',
        'metric_date' => 'date',
        'followers' => 'int',
        'engagement_rate' => 'float',
        'content_count' => 'int',
        'conversion_proxy' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

