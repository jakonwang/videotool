<?php
declare(strict_types=1);

namespace app\model;

use think\Model;

/**
 * 后台管理员账号
 */
class AdminUser extends Model
{
    protected $name = 'admin_users';

    protected $schema = [
        'id'            => 'int',
        'username'      => 'string',
        'password_hash' => 'string',
        'status'        => 'int',
        'last_login_at' => 'datetime',
        'last_login_ip' => 'string',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];
}

