<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 统计服务（只做聚合查询，供后台仪表盘图表使用）
 */
class StatsService
{
    private const ERROR_LINE_PATTERN = '/代理下载错误|下载失败|预缓存失败|缓存.*失败/i';

    /**
     * 总览 KPI（含今日）
     */
    public static function overview(): array
    {
        $todayStart = date('Y-m-d 00:00:00');
        $todayDate = date('Y-m-d');
        $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
        $yesterdayStart = $yesterdayDate . ' 00:00:00';
        $yesterdayEnd = $yesterdayDate . ' 23:59:59';

        $platforms = (int) Db::name('platforms')->count();
        $devices = (int) Db::name('devices')->count();
        $videos = (int) Db::name('videos')->count();
        $downloaded = (int) Db::name('videos')->where('is_downloaded', 1)->count();
        $undownloaded = (int) Db::name('videos')->where('is_downloaded', 0)->count();

        $todayUploaded = (int) Db::name('videos')->whereTime('created_at', '>=', $todayStart)->count();
        $yesterdayUploaded = (int) Db::name('videos')
            ->whereBetweenTime('created_at', $yesterdayStart, $yesterdayEnd)
            ->count();

        // 以 download_logs 作为“下载发生”口径；无日志表时可以退化为 is_downloaded 统计
        $todayDownloaded = 0;
        $yesterdayDownloaded = 0;
        try {
            $todayDownloaded = (int) Db::name('download_logs')->whereTime('downloaded_at', '>=', $todayStart)->count();
            $yesterdayDownloaded = (int) Db::name('download_logs')
                ->whereBetweenTime('downloaded_at', $yesterdayStart, $yesterdayEnd)
                ->count();
        } catch (\Throwable $e) {
            $todayDownloaded = 0;
            $yesterdayDownloaded = 0;
        }

        $downloadRate = 0.0;
        if ($videos > 0) {
            $downloadRate = round(($downloaded / $videos) * 100, 1);
        }

        $undownloadRate = 0.0;
        if ($videos > 0) {
            $undownloadRate = round(($undownloaded / $videos) * 100, 1);
        }

        $todayUploadedDeltaPct = null;
        if ($yesterdayUploaded > 0) {
            $todayUploadedDeltaPct = round((($todayUploaded - $yesterdayUploaded) / $yesterdayUploaded) * 100, 1);
        }
        $todayDownloadedDeltaPct = null;
        if ($yesterdayDownloaded > 0) {
            $todayDownloadedDeltaPct = round((($todayDownloaded - $yesterdayDownloaded) / $yesterdayDownloaded) * 100, 1);
        }

        // 近 7 天均值（用于辅助信息密度）
        $last7Start = date('Y-m-d 00:00:00', strtotime('-6 day'));
        $last7Uploaded = (int) Db::name('videos')->whereTime('created_at', '>=', $last7Start)->count();
        $avg7Uploaded = round($last7Uploaded / 7, 1);
        $avg7Downloaded = 0.0;
        try {
            $last7Downloaded = (int) Db::name('download_logs')->whereTime('downloaded_at', '>=', $last7Start)->count();
            $avg7Downloaded = round($last7Downloaded / 7, 1);
        } catch (\Throwable $e) {
            $avg7Downloaded = 0.0;
        }

        return [
            'platforms' => $platforms,
            'devices' => $devices,
            'videos' => $videos,
            'downloaded' => $downloaded,
            'undownloaded' => $undownloaded,
            'today_uploaded' => $todayUploaded,
            'today_downloaded' => $todayDownloaded,
            'download_rate' => $downloadRate,
            'undownload_rate' => $undownloadRate,
            'yesterday_uploaded' => $yesterdayUploaded,
            'yesterday_downloaded' => $yesterdayDownloaded,
            'today_uploaded_delta_pct' => $todayUploadedDeltaPct,
            'today_downloaded_delta_pct' => $todayDownloadedDeltaPct,
            'avg7_uploaded' => $avg7Uploaded,
            'avg7_downloaded' => $avg7Downloaded,
            'asof' => $todayDate,
        ];
    }

    /**
     * 近 N 天趋势（上传数、下载数）
     *
     * @return array{labels: string[], uploaded: int[], downloaded: int[]}
     */
    public static function trends(int $days = 30): array
    {
        $days = max(7, min(180, $days));
        $startDate = date('Y-m-d', strtotime('-' . ($days - 1) . ' day'));

        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('Y-m-d', strtotime('-' . $i . ' day'));
        }

        $uploadedMap = [];
        $rows = Db::name('videos')
            ->fieldRaw("DATE(created_at) as d, COUNT(*) as c")
            ->whereTime('created_at', '>=', $startDate . ' 00:00:00')
            ->group('d')
            ->select()
            ->toArray();
        foreach ($rows as $r) {
            $uploadedMap[(string) $r['d']] = (int) $r['c'];
        }

        $downloadedMap = [];
        try {
            $dRows = Db::name('download_logs')
                ->fieldRaw("DATE(downloaded_at) as d, COUNT(*) as c")
                ->whereTime('downloaded_at', '>=', $startDate . ' 00:00:00')
                ->group('d')
                ->select()
                ->toArray();
            foreach ($dRows as $r) {
                $downloadedMap[(string) $r['d']] = (int) $r['c'];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $uploaded = [];
        $downloaded = [];
        foreach ($labels as $d) {
            $uploaded[] = $uploadedMap[$d] ?? 0;
            $downloaded[] = $downloadedMap[$d] ?? 0;
        }

        return [
            'labels' => $labels,
            'uploaded' => $uploaded,
            'downloaded' => $downloaded,
        ];
    }

    /**
     * 平台分布（总数/未下载/已下载）
     */
    public static function platformDistribution(): array
    {
        // 左连接，平台无视频也显示 0
        $rows = Db::query("
            SELECT p.id, p.name,
                   SUM(CASE WHEN v.id IS NULL THEN 0 ELSE 1 END) AS total,
                   SUM(CASE WHEN v.is_downloaded = 0 THEN 1 ELSE 0 END) AS undownloaded,
                   SUM(CASE WHEN v.is_downloaded = 1 THEN 1 ELSE 0 END) AS downloaded
            FROM platforms p
            LEFT JOIN videos v ON v.platform_id = p.id
            GROUP BY p.id, p.name
            ORDER BY total DESC, p.id ASC
        ");

        $labels = [];
        $total = [];
        $undownloaded = [];
        $downloaded = [];
        foreach ($rows as $r) {
            $labels[] = (string) $r['name'];
            $total[] = (int) $r['total'];
            $undownloaded[] = (int) $r['undownloaded'];
            $downloaded[] = (int) $r['downloaded'];
        }

        return [
            'labels' => $labels,
            'total' => $total,
            'undownloaded' => $undownloaded,
            'downloaded' => $downloaded,
        ];
    }

    /**
     * 下载异常趋势（解析 runtime 日志，按天统计）
     *
     * @return array{labels: string[], total: int[], download: int[], cache: int[], other: int[]}
     */
    public static function downloadErrorTrends(int $days = 7): array
    {
        $days = max(3, min(30, $days));
        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('Y-m-d', strtotime('-' . $i . ' day'));
        }

        $out = [
            'labels' => $labels,
            'total' => array_fill(0, $days, 0),
            'download' => array_fill(0, $days, 0),
            'cache' => array_fill(0, $days, 0),
            'other' => array_fill(0, $days, 0),
        ];

        foreach ($labels as $idx => $d) {
            $ym = str_replace('-', '', substr($d, 0, 7)); // YYYYMM
            $day = substr($d, 8, 2); // DD
            $logFile = runtime_path() . 'log' . DIRECTORY_SEPARATOR . $ym . DIRECTORY_SEPARATOR . $day . '.log';
            if (!is_file($logFile)) {
                continue;
            }

            $f = new \SplFileObject($logFile, 'r');
            $f->setFlags(\SplFileObject::DROP_NEW_LINE);
            while (!$f->eof()) {
                $line = (string) $f->fgets();
                if ($line === '' || !preg_match(self::ERROR_LINE_PATTERN, $line)) {
                    continue;
                }
                $out['total'][$idx]++;
                if (preg_match('/下载失败|代理下载错误/i', $line)) {
                    $out['download'][$idx]++;
                } elseif (preg_match('/预缓存|缓存.*失败/i', $line)) {
                    $out['cache'][$idx]++;
                } else {
                    $out['other'][$idx]++;
                }
            }
        }

        return $out;
    }

    /**
     * 下载异常 Top（按关键词/错误短语）
     *
     * @return array{items: array<int, array{label: string, count: int}>}
     */
    public static function downloadErrorTop(int $days = 7, int $limit = 8): array
    {
        $days = max(1, min(30, $days));
        $limit = max(3, min(20, $limit));
        $labels = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $labels[] = date('Y-m-d', strtotime('-' . $i . ' day'));
        }

        $counter = [];
        foreach ($labels as $d) {
            $ym = str_replace('-', '', substr($d, 0, 7));
            $day = substr($d, 8, 2);
            $logFile = runtime_path() . 'log' . DIRECTORY_SEPARATOR . $ym . DIRECTORY_SEPARATOR . $day . '.log';
            if (!is_file($logFile)) {
                continue;
            }
            $f = new \SplFileObject($logFile, 'r');
            $f->setFlags(\SplFileObject::DROP_NEW_LINE);
            while (!$f->eof()) {
                $line = (string) $f->fgets();
                if ($line === '' || !preg_match(self::ERROR_LINE_PATTERN, $line)) {
                    continue;
                }

                $label = '';
                if (preg_match('/"error"["\']?\s*:\s*"([^"]+)"/', $line, $m)) {
                    $label = trim((string) $m[1]);
                } elseif (preg_match('/下载失败[：:]\s*(.+?)(?:\s|$)/', $line, $m)) {
                    $label = trim((string) $m[1]);
                } else {
                    $label = '其他';
                }

                if ($label === '') {
                    $label = '其他';
                }
                // 过长的错误截断，避免前端显示炸裂
                if (mb_strlen($label, 'UTF-8') > 40) {
                    $label = mb_substr($label, 0, 40, 'UTF-8') . '…';
                }

                $counter[$label] = ($counter[$label] ?? 0) + 1;
            }
        }

        arsort($counter);
        $items = [];
        foreach (array_slice($counter, 0, $limit, true) as $k => $v) {
            $items[] = ['label' => (string) $k, 'count' => (int) $v];
        }

        return ['items' => $items];
    }

    /**
     * 商品分布（库存/已下载/未下载）
     */
    public static function productDistribution(int $limit = 12): array
    {
        $limit = max(5, min(50, $limit));
        $rows = Db::query("
            SELECT p.id, p.name,
                   SUM(CASE WHEN v.id IS NULL THEN 0 ELSE 1 END) AS total,
                   SUM(CASE WHEN v.is_downloaded = 0 THEN 1 ELSE 0 END) AS undownloaded,
                   SUM(CASE WHEN v.is_downloaded = 1 THEN 1 ELSE 0 END) AS downloaded
            FROM products p
            LEFT JOIN videos v ON v.product_id = p.id
            GROUP BY p.id, p.name
            ORDER BY undownloaded DESC, total DESC, p.id ASC
            LIMIT {$limit}
        ");

        $labels = [];
        $undownloaded = [];
        $downloaded = [];
        foreach ($rows as $r) {
            $labels[] = (string) $r['name'];
            $undownloaded[] = (int) $r['undownloaded'];
            $downloaded[] = (int) $r['downloaded'];
        }

        return [
            'labels' => $labels,
            'undownloaded' => $undownloaded,
            'downloaded' => $downloaded,
        ];
    }

    /**
     * 存储占用（public/uploads + runtime/cache）
     *
     * @return array{uploads: array{bytes:int, files:int}, runtime_cache: array{bytes:int, files:int}}
     */
    public static function storageUsage(): array
    {
        $uploadsDir = root_path() . 'public' . DIRECTORY_SEPARATOR . 'uploads';
        $runtimeCacheDir = runtime_path() . 'cache';

        return [
            'uploads' => self::dirSize($uploadsDir),
            'runtime_cache' => self::dirSize($runtimeCacheDir),
        ];
    }

    private static function dirSize(string $dir): array
    {
        $bytes = 0;
        $files = 0;
        if (!is_dir($dir)) {
            return ['bytes' => 0, 'files' => 0];
        }
        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
            foreach ($it as $fi) {
                /** @var \SplFileInfo $fi */
                if ($fi->isFile()) {
                    $files++;
                    $bytes += (int) $fi->getSize();
                }
            }
        } catch (\Throwable $e) {
            return ['bytes' => 0, 'files' => 0];
        }

        return ['bytes' => $bytes, 'files' => $files];
    }
}

