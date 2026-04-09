<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AutoDmEvent extends Model
{
    protected $name = 'auto_dm_events';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'campaign_id' => 'int',
        'task_id' => 'int',
        'device_id' => 'int',
        'influencer_id' => 'int',
        'event_type' => 'string',
        'event_status' => 'int',
        'error_code' => 'string',
        'error_message' => 'string',
        'duration_ms' => 'int',
        'screenshot_path' => 'string',
        'payload_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

