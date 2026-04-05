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
        $entry = $this->request->baseFile();
        if ($entry === '' || $entry === '/') {
            $entry = '/index.php';
        }

        return View::fetch('index/search_by_image', [
            /** 与当前访问入口一致，避免子目录部署时 fetch('/index.php/...') 打到站点根路径 404 */
            'h5_api_entry_js' => \json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
