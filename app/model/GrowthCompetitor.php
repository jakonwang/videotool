<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthCompetitor extends Model
{
    protected $name = 'growth_competitors';

    protected $schema = [
        'id' => 'int',
        'name' => 'string',
        'platform' => 'string',
        'region' => 'string',
        'category_name' => 'string',
        'status' => 'int',
        'notes' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

