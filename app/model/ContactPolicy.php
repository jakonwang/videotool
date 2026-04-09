<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ContactPolicy extends Model
{
    protected $name = 'contact_policies';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'policy_key' => 'string',
        'is_enabled' => 'int',
        'config_json' => 'string',
        'updated_by' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

