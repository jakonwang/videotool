<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 桌面端授权码
 */
class AppLicense extends Model
{
    protected $name = 'app_licenses';

    protected $schema = [
        'id'           => 'int',
        'tenant_id'    => 'int',
        'license_key'  => 'string',
        'machine_id'   => 'string',
        'status'       => 'int',
        'expire_time'  => 'datetime',
        'created_at'   => 'datetime',
    ];

    protected $type = [
        'expire_time' => 'datetime:Y-m-d H:i:s',
    ];
}
