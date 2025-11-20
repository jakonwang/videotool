<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 视频模型
 */
class Video extends Model
{
    protected $name = 'videos';
    
    // 设置字段信息
    protected $schema = [
        'id'            => 'int',
        'platform_id'   => 'int',
        'device_id'     => 'int',
        'title'         => 'string',
        'cover_url'     => 'string',
        'video_url'     => 'string',
        'is_downloaded' => 'int',
        'sort_order'    => 'int',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];
    
    // 关联平台
    public function platform()
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }
    
    // 关联设备
    public function device()
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
    
    /**
     * 获取未下载的视频
     * @param int $deviceId 设备ID
     * @return Video|null
     */
    public static function getUndownloaded(int $deviceId)
    {
        return self::where('device_id', $deviceId)
            ->where('is_downloaded', 0)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->find();
    }
}

