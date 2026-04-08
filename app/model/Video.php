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

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'platform_id' => 'int',
        'device_id' => 'int',
        'product_id' => 'int',
        'title' => 'string',
        'cover_url' => 'string',
        'video_url' => 'string',
        'video_md5' => 'string',
        'ad_creative_code' => 'string',
        'is_downloaded' => 'int',
        'sort_order' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function platform()
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }

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
     * 获取设备未下载视频
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
