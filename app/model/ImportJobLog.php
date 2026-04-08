<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

class ImportJobLog extends Model
{
    protected $name = 'import_job_logs';

    protected $schema = [
        'id' => 'int',
        'tenant_id' => 'int',
        'job_id' => 'int',
        'level' => 'string',
        'message' => 'string',
        'context_json' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
