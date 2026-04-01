<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 桌面端版本发布
 */
class AppVersion extends Model
{
    protected $name = 'app_versions';

    protected $schema = [
        'id'             => 'int',
        'version'        => 'string',
        'release_notes'  => 'string',
        'download_url'   => 'string',
        'is_mandatory'   => 'int',
        'status'         => 'int',
        'created_at'     => 'datetime',
    ];
}
