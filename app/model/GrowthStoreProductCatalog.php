<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthStoreProductCatalog extends Model
{
    protected $name = 'growth_store_product_catalog';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'store_id' => 'int',
        'style_code' => 'string',
        'product_name' => 'string',
        'image_url' => 'string',
        'status' => 'int',
        'notes' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
