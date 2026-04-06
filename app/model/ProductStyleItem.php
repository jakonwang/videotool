<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 款式图搜索引行
 */
class ProductStyleItem extends Model
{
    protected $name = 'product_style_items';

    protected $schema = [
        'id'           => 'int',
        'product_code' => 'string',
        'image_ref'    => 'string',
        'image_path'   => 'string',
        'hot_type'     => 'string',
        'ai_description' => 'string',
        'embedding'    => 'string',
        'status'       => 'int',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];
}
