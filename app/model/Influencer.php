<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * TikTok 达人名录（tiktok_id 为 @handle，唯一）
 */
class Influencer extends Model
{
    protected $name = 'influencers';

    protected $schema = [
        'id'             => 'int',
        'tiktok_id'      => 'string',
        'category_name'  => 'string',
        'category_id'    => 'int',
        'nickname'       => 'string',
        'avatar_url'     => 'string',
        'follower_count' => 'int',
        'contact_info'   => 'string',
        'region'         => 'string',
        'status'         => 'int',
        'sample_tracking_no' => 'string',
        'sample_status' => 'int',
        'tags_json' => 'string',
        'last_contacted_at' => 'datetime',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];
}
