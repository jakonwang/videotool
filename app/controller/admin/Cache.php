<?php
declare(strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\service\DownloadCacheService;
use think\facade\View;

class Cache extends BaseController
{
    public function index()
    {
        $keyword = trim((string)$this->request->param('keyword', ''));
        $page = max(1, (int)$this->request->param('page', 1));
        $perPage = 15;

        $service = new DownloadCacheService();
        $list = $service->listCaches($page, $perPage, $keyword);
        $stats = $service->getStats();

        $totalPages = (int)max(1, ceil($list['total'] / $perPage));

        return View::fetch('admin/cache/index', [
            'items' => $list['items'],
            'keyword' => $keyword,
            'page' => $list['page'],
            'per_page' => $perPage,
            'total' => $list['total'],
            'total_pages' => $totalPages,
            'stats' => $stats,
            'is_enabled' => $service->isEnabled()
        ]);
    }

    public function delete(string $hash)
    {
        $service = new DownloadCacheService();
        $result = $service->deleteByHash($hash);
        if ($result) {
            return json(['code' => 0, 'msg' => '缓存已删除']);
        }
        return json(['code' => 1, 'msg' => '删除失败或缓存不存在']);
    }

    public function clear()
    {
        $service = new DownloadCacheService();
        $service->clearAll();
        return json(['code' => 0, 'msg' => '缓存已清空']);
    }

    public function download(string $hash)
    {
        $service = new DownloadCacheService();
        $file = $service->getFileForDownload($hash);
        if (!$file) {
            abort(404, '缓存文件不存在');
        }
        return response()->file($file['path'])->name($file['file_name']);
    }
}

