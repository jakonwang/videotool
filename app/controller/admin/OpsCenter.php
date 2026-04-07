<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use think\facade\View;

class OpsCenter extends BaseController
{
    public function index()
    {
        return View::fetch('admin/ops_center/index', []);
    }
}

