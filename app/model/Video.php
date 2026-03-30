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
        'device_id'     => 'int', // 可空：达人分发专用素材无需设备
        'product_id'    => 'int',
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

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * 某商品下随机一条未下载视频（全局核销）
     */
    public static function getRandomUndownloadedByProductId(int $productId): ?self
    {
        return self::where('product_id', $productId)
            ->where('is_downloaded', 0)
            ->orderRaw('RAND()')
            ->find();
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

