<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AutoDmReplyReview extends Model
{
    protected $name = 'auto_dm_reply_reviews';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'campaign_id' => 'int',
        'task_id' => 'int',
        'influencer_id' => 'int',
        'channel' => 'string',
        'step_no' => 'int',
        'reply_text' => 'string',
        'rule_category' => 'string',
        'confirm_category' => 'string',
        'transition_target_status' => 'int',
        'confirm_note' => 'string',
        'confirmed_by' => 'int',
        'confirmed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
