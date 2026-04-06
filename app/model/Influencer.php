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
        'nickname'       => 'string',
        'avatar_url'     => 'string',
        'follower_count' => 'int',
        'contact_info'   => 'string',
        'region'         => 'string',
        'status'         => 'int',
        'created_at'     => 'datetime',
        'updated_at'     => 'datetime',
    ];
}
