<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class TenantPackage extends Model
{
    protected $name = 'tenant_packages';

    protected $schema = [
        'id' => 'int',
        'package_code' => 'string',
        'package_name' => 'string',
        'description' => 'string',
        'status' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
