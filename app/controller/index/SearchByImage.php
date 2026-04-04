<?php
declare(strict_types=1);

namespace app\controller\index;

use app\BaseController;
use think\facade\View;

/**
 * 拍照寻款 H5
 */
class SearchByImage extends BaseController
{
    public function index()
    {
        return View::fetch('index/search_by_image', []);
    }
}
