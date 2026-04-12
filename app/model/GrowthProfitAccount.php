<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthProfitAccount extends Model
{
    protected $name = 'growth_profit_accounts';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'store_id' => 'int',
        'account_name' => 'string',
        'account_code' => 'string',
        'channel_type' => 'string',
        'account_currency' => 'string',
        'default_gmv_currency' => 'string',
        'status' => 'int',
        'notes' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
