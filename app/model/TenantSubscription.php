<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class TenantSubscription extends Model
{
    protected $name = 'tenant_subscriptions';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'package_id' => 'int',
        'status' => 'int',
        'expires_at' => 'datetime',
        'addon_modules_json' => 'string',
        'disabled_modules_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
