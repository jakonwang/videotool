<?php
declare(strict_types=1);

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

        return View::fetch('admin/index/index', array_merge(
            ['stats' => $stats],
            self::dashboardScalars($stats)
        ));
    }

    /**
     * 仪表盘 KPI 全部用扁平标量传入视图，避免模板中 {$stats.xxx|default} 编译出异常 PHP（unexpected identifier 等）。
     *
     * @param array<string, mixed> $stats
     * @return array<string, mixed>
     */
    private static function dashboardScalars(array $stats): array
    {
        $upDelta = $stats['today_uploaded_delta_pct'] ?? null;
        $dlDelta = $stats['today_downloaded_delta_pct'] ?? null;

        return [
            'video_total' => (int) ($stats['videos'] ?? 0),
            'asof_display' => (string) ($stats['asof'] ?? ''),
            'd_downloaded' => (int) ($stats['downloaded'] ?? 0),
            'd_undownloaded' => (int) ($stats['undownloaded'] ?? 0),
            'd_download_rate' => (float) ($stats['download_rate'] ?? 0),
            'd_undownload_rate' => (float) ($stats['undownload_rate'] ?? 0),
            'd_platforms' => (int) ($stats['platforms'] ?? 0),
            'd_devices' => (int) ($stats['devices'] ?? 0),
            'd_today_uploaded' => (int) ($stats['today_uploaded'] ?? 0),
            'd_yesterday_uploaded' => (int) ($stats['yesterday_uploaded'] ?? 0),
            'd_avg7_uploaded' => (float) ($stats['avg7_uploaded'] ?? 0),
            'd_today_uploaded_delta_pct' => $upDelta !== null ? (float) $upDelta : null,
            'd_today_downloaded' => (int) ($stats['today_downloaded'] ?? 0),
            'd_yesterday_downloaded' => (int) ($stats['yesterday_downloaded'] ?? 0),
            'd_avg7_downloaded' => (float) ($stats['avg7_downloaded'] ?? 0),
            'd_today_downloaded_delta_pct' => $dlDelta !== null ? (float) $dlDelta : null,
            'd_style_index_total' => (int) ($stats['style_index_total'] ?? 0),
            'd_influencers_total' => (int) ($stats['influencers_total'] ?? 0),
            'd_creator_links_total' => (int) ($stats['creator_links_total'] ?? 0),
            'd_has_upload_delta' => $upDelta !== null,
            'd_has_download_delta' => $dlDelta !== null,
        ];
    }
}
