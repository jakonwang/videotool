<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 商品
 */
class Product extends Model
{
    protected $name = 'products';

    protected $schema = [
        'id'          => 'int',
        'name'        => 'string',
        'goods_url'   => 'string',
        'thumb_url'   => 'string',
        'tiktok_shop_url' => 'string',
        'ai_description' => 'string',
        'status'      => 'int',
        'sort_order'  => 'int',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
    ];

    public function links()
    {
        return $this->hasMany(ProductLink::class, 'product_id');
    }

    public function videos()
    {
        return $this->hasMany(Video::class, 'product_id');
    }
}
