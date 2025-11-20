<?php
declare (strict_types = 1);

namespace app\controller\index;

use app\BaseController;
use think\facade\View;

/**
 * 前台首页
 */
class Index extends BaseController
{
    public function index()
    {
        $ip = get_client_ip();
        return View::fetch('index/index', ['ip' => $ip]);
    }
}

