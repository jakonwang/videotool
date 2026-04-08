<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * Offline reservation orders from customer H5 catalog.
 */
class OfflineOrder extends Model
{
    protected $name = 'offline_orders';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'order_no' => 'string',
        'customer_info' => 'string',
        'items_json' => 'string',
        'total_amount' => 'float',
        'status' => 'int',
        'remark' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
