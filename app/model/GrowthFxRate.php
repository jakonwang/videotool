<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthFxRate extends Model
{
    protected $name = 'growth_fx_rates';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'rate_date' => 'date',
        'from_currency' => 'string',
        'to_currency' => 'string',
        'rate' => 'float',
        'source' => 'string',
        'is_fallback' => 'int',
        'meta_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
