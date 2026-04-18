<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class GrowthLiveSession extends Model
{
    protected $name = 'growth_live_sessions';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'store_id' => 'int',
        'session_date' => 'date',
        'session_name' => 'string',
        'source_file' => 'string',
        'file_hash' => 'string',
        'import_job_id' => 'int',
        'total_rows' => 'int',
        'matched_rows' => 'int',
        'unmatched_rows' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
