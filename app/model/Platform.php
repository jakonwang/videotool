<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 平台模型
 */
class Platform extends Model
{
    protected $name = 'platforms';
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'name'        => 'string',
        'code'        => 'string',
        'icon'        => 'string',
        'status'      => 'int',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
    
    // 关联设备
    public function devices()
    {
        return $this->hasMany(Device::class, 'platform_id');
    }
    
    // 关联视频
    public function videos()
    {
        return $this->hasMany(Video::class, 'platform_id');
    }
}

