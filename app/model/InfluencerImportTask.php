<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 达人名录异步导入任务
 */
class InfluencerImportTask extends Model
{
    protected $name = 'influencer_import_tasks';

    protected $schema = [
        'id'                 => 'int',
        'status'             => 'string',
        'file_path'          => 'string',
        'file_ext'           => 'string',
        'total_rows'         => 'int',
        'processed_rows'     => 'int',
        'line_idx'           => 'int',
        'header_resolved'    => 'int',
        'header_json'        => 'string',
        'use_default_header' => 'int',
        'inserted_count'     => 'int',
        'updated_count'      => 'int',
        'failed_count'       => 'int',
        'logs_json'          => 'string',
        'error_message'      => 'string',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];
}
