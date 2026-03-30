<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\StatsService;

/**
 * 仪表盘统计接口（只读）
 */
class Stats extends BaseController
{
    public function overview()
    {
        return json(['code' => 0, 'data' => StatsService::overview()]);
    }

    public function trends()
    {
        $days = (int) $this->request->get('days', 30);
        return json(['code' => 0, 'data' => StatsService::trends($days)]);
    }

    public function platformDistribution()
    {
        return json(['code' => 0, 'data' => StatsService::platformDistribution()]);
    }

    public function downloadErrorTrends()
    {
        $days = (int) $this->request->get('days', 7);
        return json(['code' => 0, 'data' => StatsService::downloadErrorTrends($days)]);
    }

    public function downloadErrorTop()
    {
        $days = (int) $this->request->get('days', 7);
        $limit = (int) $this->request->get('limit', 8);
        return json(['code' => 0, 'data' => StatsService::downloadErrorTop($days, $limit)]);
    }

    public function productDistribution()
    {
        $limit = (int) $this->request->get('limit', 12);
        return json(['code' => 0, 'data' => StatsService::productDistribution($limit)]);
    }

    public function storageUsage()
    {
        return json(['code' => 0, 'data' => StatsService::storageUsage()]);
    }
}

