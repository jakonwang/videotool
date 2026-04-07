<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class SampleShipment extends Model
{
    protected $name = 'sample_shipments';

    protected $schema = [
        'id' => 'int',
        'influencer_id' => 'int',
        'tracking_no' => 'string',
        'courier' => 'string',
        'shipment_status' => 'int',
        'receipt_status' => 'int',
        'shipped_at' => 'datetime',
        'received_at' => 'datetime',
        'receipt_note' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

