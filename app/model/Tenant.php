<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class Tenant extends Model
{
    protected $name = 'tenants';

    protected $schema = [
        'id' => 'int',
        'tenant_code' => 'string',
        'tenant_name' => 'string',
        'status' => 'int',
        'remark' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
