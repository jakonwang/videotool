<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 达人联系话术模板
 */
class MessageTemplate extends Model
{
    protected $name = 'message_templates';

    protected $schema = [
        'id'         => 'int',
        'name'       => 'string',
        'body'       => 'string',
        'sort_order' => 'int',
        'status'     => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
