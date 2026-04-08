<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthAdCreative extends Model
{
    protected $name = 'growth_ad_creatives';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'creative_code' => 'string',
        'title' => 'string',
        'platform' => 'string',
        'region' => 'string',
        'category_name' => 'string',
        'landing_url' => 'string',
        'first_seen_at' => 'date',
        'last_seen_at' => 'date',
        'status' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
