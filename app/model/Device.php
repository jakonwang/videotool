<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 设备模型
 */
class Device extends Model
{
    protected $name = 'devices';
    
    // 设置字段信息
    protected $schema = [
        'id'          => 'int',
        'platform_id' => 'int',
        'ip_address'  => 'string',
        'device_name' => 'string',
        'status'      => 'int',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];
    
    // 关联平台
    public function platform()
    {
        return $this->belongsTo(Platform::class, 'platform_id');
    }
    
    // 关联视频
    public function videos()
    {
        return $this->hasMany(Video::class, 'device_id');
    }
    
    /**
     * 获取或创建设备
     * @param string $ip IP地址
     * @param int $platformId 平台ID
     * @return Device
     */
    public static function getOrCreate(string $ip, int $platformId)
    {
        $device = self::where('ip_address', $ip)
            ->where('platform_id', $platformId)
            ->find();
            
        if (!$device) {
            $device = self::create([
                'platform_id' => $platformId,
                'ip_address' => $ip,
                'device_name' => '设备_' . substr($ip, -3)
            ]);
        }
        
        return $device;
    }
}

