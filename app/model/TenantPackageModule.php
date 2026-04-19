<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class TenantPackageModule extends Model
{
    protected $name = 'tenant_package_modules';

    protected $schema = [
        'id' => 'int',
        'package_id' => 'int',
        'module_name' => 'string',
        'is_optional' => 'int',
        'default_enabled' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
