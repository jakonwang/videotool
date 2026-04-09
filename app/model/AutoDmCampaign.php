<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class AutoDmCampaign extends Model
{
    protected $name = 'auto_dm_campaigns';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'campaign_name' => 'string',
        'template_id' => 'int',
        'product_id' => 'int',
        'campaign_status' => 'int',
        'preferred_channel' => 'string',
        'daily_limit' => 'int',
        'time_window_start' => 'string',
        'time_window_end' => 'string',
        'min_interval_sec' => 'int',
        'fail_fuse_threshold' => 'int',
        'target_filter_json' => 'string',
        'stats_json' => 'string',
        'ab_config_json' => 'string',
        'sequence_config_json' => 'string',
        'stop_on_reply' => 'int',
        'reply_confirm_mode' => 'string',
        'total_targets' => 'int',
        'total_sent' => 'int',
        'total_failed' => 'int',
        'total_blocked' => 'int',
        'total_replied' => 'int',
        'total_unsubscribed' => 'int',
        'last_run_at' => 'datetime',
        'paused_at' => 'datetime',
        'created_by' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
