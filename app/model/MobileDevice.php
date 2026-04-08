<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class MobileDevice extends Model
{
    protected $name = 'mobile_devices';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'device_code' => 'string',
        'device_name' => 'string',
        'device_serial' => 'string',
        'platform' => 'string',
        'agent_token' => 'string',
        'status' => 'int',
        'is_online' => 'int',
        'heartbeat_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'daily_quota' => 'int',
        'daily_used' => 'int',
        'fail_streak' => 'int',
        'cooldown_until' => 'datetime',
        'capability_json' => 'string',
        'remark' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
