<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ImportJob extends Model
{
    protected $name = 'import_jobs';

    protected $schema = [
        'id' => 'int',
        'source_id' => 'int',
        'domain' => 'string',
        'job_type' => 'string',
        'file_name' => 'string',
        'status' => 'int',
        'total_rows' => 'int',
        'success_rows' => 'int',
        'failed_rows' => 'int',
        'error_message' => 'string',
        'payload_json' => 'string',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

