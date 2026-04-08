<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class MobileActionTask extends Model
{
    protected $name = 'mobile_action_tasks';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'influencer_id' => 'int',
        'task_type' => 'string',
        'target_channel' => 'string',
        'priority' => 'int',
        'task_status' => 'int',
        'payload_json' => 'string',
        'rendered_text' => 'string',
        'device_id' => 'int',
        'retry_count' => 'int',
        'max_retries' => 'int',
        'scheduled_at' => 'datetime',
        'assigned_at' => 'datetime',
        'prepared_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_error_code' => 'string',
        'last_error_message' => 'string',
        'last_error_at' => 'datetime',
        'created_by' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
