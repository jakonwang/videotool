<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class InfluencerOutreachTask extends Model
{
    protected $name = 'influencer_outreach_tasks';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'influencer_id' => 'int',
        'template_id' => 'int',
        'product_id' => 'int',
        'task_status' => 'int',
        'priority' => 'int',
        'due_at' => 'datetime',
        'assigned_to' => 'int',
        'last_action_at' => 'datetime',
        'source_filter_json' => 'string',
        'payload_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
