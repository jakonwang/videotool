<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 系统设置表模型（读写请用 app\service\SystemConfigService，避免与 Model::set() 冲突）
 */
class SystemSetting extends Model
{
    protected $name = 'system_settings';

    protected $schema = [
        'id'         => 'int',
        'skey'       => 'string',
        'svalue'     => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
