<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class DataSource extends Model
{
    protected $name = 'data_sources';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'code' => 'string',
        'name' => 'string',
        'source_type' => 'string',
        'adapter_key' => 'string',
        'status' => 'int',
        'config_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
