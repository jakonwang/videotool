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
        $stats = StatsService::overview();

        return View::fetch('admin/index/index', [
            'stats'         => $stats,
            // 标量单独传入，避免模板编译对 $stats['videos'] / ?? 等解析异常（部分环境报 unexpected identifier "videos"）
            'video_total'   => (int) ($stats['videos'] ?? 0),
            'asof_display'  => (string) ($stats['asof'] ?? ''),
        ]);
    }
}

