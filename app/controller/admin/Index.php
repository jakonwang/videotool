<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\service\StatsService;
use think\facade\View;

/**
 * 后台首页
 */
class Index extends BaseController
{
    public function index()
    {
        return View::fetch('admin/index/index', ['stats' => StatsService::overview()]);
    }
}

