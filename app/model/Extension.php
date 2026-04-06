<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 模块扩展配置
 */
class Extension extends Model
{
    protected $name = 'extensions';

    protected $schema = [
        'id' => 'int',
        'name' => 'string',
        'title' => 'string',
        'version' => 'string',
        'is_enabled' => 'int',
        'config_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

