<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 达人外联记录
 */
class OutreachLog extends Model
{
    protected $name = 'outreach_logs';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'influencer_id' => 'int',
        'template_id' => 'int',
        'template_name' => 'string',
        'template_lang' => 'string',
        'product_id' => 'int',
        'product_name' => 'string',
        'channel' => 'string',
        'rendered_body' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

