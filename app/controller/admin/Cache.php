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

    /**
     * 缓存列表（JSON）
     * 供 Vue/ElementPlus 页面使用
     */
    public function listJson()
    {
        $keyword = trim((string)$this->request->param('keyword', ''));
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) $pageSize = 10;
        if ($pageSize > 100) $pageSize = 100;

        $service = new DownloadCacheService();
        $list = $service->listCaches(max(1, $page), $pageSize, $keyword);
        $stats = $service->getStats();

        $items = [];
        foreach (($list['items'] ?? []) as $it) {
            $items[] = [
                'hash' => (string) ($it['hash'] ?? ''),
                'file_name' => (string) ($it['file_name'] ?? ''),
                'source_url' => (string) ($it['source_url'] ?? ''),
                'size' => (int) ($it['size'] ?? 0),
                'size_human' => (string) ($it['size_human'] ?? '0 B'),
                'cached_at_text' => (string) ($it['cached_at_text'] ?? ''),
                'type' => (string) ($it['type'] ?? ''),
                'video_id' => $it['video_id'] ?? null,
            ];
        }

        return json([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'items' => $items,
                'total' => (int) ($list['total'] ?? 0),
                'page' => (int) ($list['page'] ?? 1),
                'page_size' => (int) ($list['per_page'] ?? $pageSize),
                'stats' => $stats,
                'is_enabled' => $service->isEnabled(),
            ],
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

