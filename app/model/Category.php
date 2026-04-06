<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 分类（商品/达人）
 */
class Category extends Model
{
    protected $name = 'categories';

    protected $schema = [
        'id' => 'int',
        'name' => 'string',
        'type' => 'string',
        'sort_order' => 'int',
        'status' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

