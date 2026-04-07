<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthIndustryMetric extends Model
{
    protected $name = 'growth_industry_metrics';

    protected $schema = [
        'id' => 'int',
        'metric_date' => 'date',
        'country_code' => 'string',
        'category_name' => 'string',
        'heat_score' => 'float',
        'content_count' => 'int',
        'engagement_rate' => 'float',
        'cpc' => 'float',
        'cpm' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

