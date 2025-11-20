<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 下载记录模型
 */
class DownloadLog extends Model
{
    protected $name = 'download_logs';
    
    // 设置字段信息
    protected $schema = [
        'id'            => 'int',
        'video_id'      => 'int',
        'download_type' => 'string',
        'downloaded_at' => 'datetime',
    ];
    
    // 关联视频
    public function video()
    {
        return $this->belongsTo(Video::class, 'video_id');
    }
}

