<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AutoDmTask extends Model
{
    protected $name = 'auto_dm_tasks';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'campaign_id' => 'int',
        'influencer_id' => 'int',
        'task_type' => 'string',
        'target_channel' => 'string',
        'idempotency_key' => 'string',
        'step_no' => 'int',
        'variant_template_id' => 'int',
        'priority' => 'int',
        'task_status' => 'int',
        'reply_state' => 'int',
        'payload_json' => 'string',
        'rendered_text' => 'string',
        'reply_text' => 'string',
        'reply_at' => 'datetime',
        'next_execute_at' => 'datetime',
        'delivery_status' => 'string',
        'conversation_snippet' => 'string',
        'device_id' => 'int',
        'retry_count' => 'int',
        'max_retries' => 'int',
        'scheduled_at' => 'datetime',
        'assigned_at' => 'datetime',
        'sending_at' => 'datetime',
        'sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_error_code' => 'string',
        'last_error_message' => 'string',
        'last_error_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
