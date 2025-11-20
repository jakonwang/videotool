<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Platform as PlatformModel;
use app\model\Device as DeviceModel;
use app\model\Video as VideoModel;
use think\facade\View;

/**
 * 后台首页
 */
class Index extends BaseController
{
    public function index()
    {
        // 统计数据
        $stats = [
            'platforms' => PlatformModel::count(),
            'devices' => DeviceModel::count(),
            'videos' => VideoModel::count(),
            'downloaded' => VideoModel::where('is_downloaded', 1)->count(),
            'undownloaded' => VideoModel::where('is_downloaded', 0)->count(),
        ];
        
        return View::fetch('admin/index/index', ['stats' => $stats]);
    }
}

