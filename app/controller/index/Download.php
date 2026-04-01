<?php
declare(strict_types=1);

namespace app\controller\index;

use app\BaseController;
use app\model\AppVersion as AppVersionModel;
use think\facade\View;

/**
 * 桌面端公开下载页（无需登录）
 */
class Download extends BaseController
{
    public function index()
    {
        $list = AppVersionModel::where('status', 1)
            ->order('created_at', 'desc')
            ->order('id', 'desc')
            ->select();

        $items = [];
        foreach ($list as $row) {
            $items[] = [
                'version' => (string) ($row->version ?? ''),
                'release_notes' => (string) ($row->release_notes ?? ''),
                'download_url' => (string) ($row->download_url ?? ''),
                'is_mandatory' => (int) ($row->is_mandatory ?? 0),
                'created_at' => (string) ($row->created_at ?? ''),
            ];
        }
        $latest = $items[0] ?? null;
        $history = count($items) > 1 ? array_slice($items, 1) : [];

        return View::fetch('index/download', [
            'latest' => $latest,
            'history' => $history,
        ]);
    }
}
