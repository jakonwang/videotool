<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class InfluencerStatusLog extends Model
{
    protected $name = 'influencer_status_logs';

    protected $schema = [
        'id' => 'int',
        'influencer_id' => 'int',
        'from_status' => 'int',
        'to_status' => 'int',
        'source' => 'string',
        'note' => 'string',
        'context_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

