<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\GrowthIndustryMetric as GrowthIndustryMetricModel;
use app\service\DataImportService;
use think\facade\View;

class IndustryTrend extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        return json(['code' => $code, 'msg' => $msg, 'error_key' => $errorKey, 'data' => $data]);
    }

    public function index()
    {
        return View::fetch('admin/industry_trend/index', []);
    }

    public function listJson()
    {
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }
        $country = trim((string) $this->request->param('country', ''));
        $category = trim((string) $this->request->param('category', ''));
        $dateFrom = trim((string) $this->request->param('date_from', ''));
        $dateTo = trim((string) $this->request->param('date_to', ''));

        $query = GrowthIndustryMetricModel::order('metric_date', 'desc')->order('id', 'desc');
        if ($country !== '') {
            $query->where('country_code', strtoupper($country));
        }
        if ($category !== '') {
            $query->where('category_name', $category);
        }
        if ($dateFrom !== '') {
            $query->where('metric_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->where('metric_date', '<=', $dateTo);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);
        $items = [];
        foreach ($list as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'metric_date' => (string) ($row->metric_date ?? ''),
                'country_code' => (string) ($row->country_code ?? ''),
                'category_name' => (string) ($row->category_name ?? ''),
                'heat_score' => (float) ($row->heat_score ?? 0),
                'content_count' => (int) ($row->content_count ?? 0),
                'engagement_rate' => (float) ($row->engagement_rate ?? 0),
                'cpc' => (float) ($row->cpc ?? 0),
                'cpm' => (float) ($row->cpm ?? 0),
                'updated_at' => (string) ($row->updated_at ?? ''),
            ];
        }

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ]);
    }

    public function summaryJson()
    {
        $country = trim((string) $this->request->param('country', ''));
        $category = trim((string) $this->request->param('category', ''));
        $dateFrom = $this->normalizeDate((string) $this->request->param('date_from', ''));
        $dateTo = $this->normalizeDate((string) $this->request->param('date_to', ''));

        if ($dateFrom === '' || $dateTo === '') {
            $latest = GrowthIndustryMetricModel::max('metric_date');
            if (is_string($latest) && $latest !== '') {
                $dateTo = $this->normalizeDate($latest);
            } else {
                $dateTo = date('Y-m-d');
            }
            $dateFrom = date('Y-m-d', strtotime($dateTo . ' -6 days'));
        }

        if (strtotime($dateFrom) > strtotime($dateTo)) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }
        $days = max(1, (int) floor((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1);
        $prevFrom = date('Y-m-d', strtotime($dateFrom . ' -' . $days . ' days'));
        $prevTo = date('Y-m-d', strtotime($dateFrom . ' -1 day'));
        $yoyFrom = date('Y-m-d', strtotime($dateFrom . ' -1 year'));
        $yoyTo = date('Y-m-d', strtotime($dateTo . ' -1 year'));

        $current = $this->aggregateRange($dateFrom, $dateTo, $country, $category);
        $mom = $this->aggregateRange($prevFrom, $prevTo, $country, $category);
        $yoy = $this->aggregateRange($yoyFrom, $yoyTo, $country, $category);

        return $this->jsonOk([
            'range' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'days' => $days,
            ],
            'current' => $current,
            'mom' => [
                'base' => $mom,
                'heat_rate' => $this->rateDiff((float) $current['heat_score_avg'], (float) $mom['heat_score_avg']),
                'content_rate' => $this->rateDiff((float) $current['content_total'], (float) $mom['content_total']),
                'engagement_rate' => $this->rateDiff((float) $current['engagement_rate_avg'], (float) $mom['engagement_rate_avg']),
            ],
            'yoy' => [
                'base' => $yoy,
                'heat_rate' => $this->rateDiff((float) $current['heat_score_avg'], (float) $yoy['heat_score_avg']),
                'content_rate' => $this->rateDiff((float) $current['content_total'], (float) $yoy['content_total']),
                'engagement_rate' => $this->rateDiff((float) $current['engagement_rate_avg'], (float) $yoy['engagement_rate_avg']),
            ],
        ]);
    }

    public function importCsv()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonErr('file_required', 1, null, 'common.pickFile');
        }
        $ext = strtolower((string) $file->extension());
        if ($ext !== 'csv') {
            return $this->jsonErr('csv_only', 1, null, 'page.dataImport.csvOnly');
        }
        $tmp = $file->getPathname();
        if (!is_readable($tmp)) {
            return $this->jsonErr('file_unreadable', 1, null, 'common.loadingFailed');
        }

        try {
            $parsed = DataImportService::parseCsvFile($tmp);
            $rows = $parsed['rows'];
            $jobId = DataImportService::createJob('industry', 'csv', (string) $file->getOriginalName(), null, [
                'headers' => $parsed['headers'],
                'rows' => $rows,
            ]);
            $result = DataImportService::runDomainImport('industry', $rows, $jobId);
            return $this->jsonOk([
                'job_id' => $jobId,
                'total_rows' => (int) $result['total_rows'],
                'success_rows' => (int) $result['success_rows'],
                'failed_rows' => (int) $result['failed_rows'],
            ], 'imported');
        } catch (\Throwable $e) {
            $jobId = DataImportService::createJob('industry', 'csv', (string) $file->getOriginalName());
            DataImportService::addJobLog($jobId, 'error', 'import_exception', ['message' => $e->getMessage()]);
            DataImportService::finishJob($jobId, DataImportService::JOB_FAILED, 0, 0, 0, 'import_exception');
            return $this->jsonErr('import_failed', 1, ['job_id' => $jobId], 'page.dataImport.importFailed');
        }
    }

    /**
     * @return array{heat_score_avg:float,content_total:int,engagement_rate_avg:float,cpc_avg:float,cpm_avg:float,row_count:int}
     */
    private function aggregateRange(string $from, string $to, string $country, string $category): array
    {
        $query = GrowthIndustryMetricModel::where('metric_date', '>=', $from)->where('metric_date', '<=', $to);
        if ($country !== '') {
            $query->where('country_code', strtoupper($country));
        }
        if ($category !== '') {
            $query->where('category_name', $category);
        }
        $raw = $query->fieldRaw('
            COUNT(*) as row_count,
            COALESCE(AVG(heat_score),0) as heat_score_avg,
            COALESCE(SUM(content_count),0) as content_total,
            COALESCE(AVG(engagement_rate),0) as engagement_rate_avg,
            COALESCE(AVG(cpc),0) as cpc_avg,
            COALESCE(AVG(cpm),0) as cpm_avg
        ')->find();
        $arr = $raw ? $raw->toArray() : [];

        return [
            'heat_score_avg' => (float) ($arr['heat_score_avg'] ?? 0),
            'content_total' => (int) ($arr['content_total'] ?? 0),
            'engagement_rate_avg' => (float) ($arr['engagement_rate_avg'] ?? 0),
            'cpc_avg' => (float) ($arr['cpc_avg'] ?? 0),
            'cpm_avg' => (float) ($arr['cpm_avg'] ?? 0),
            'row_count' => (int) ($arr['row_count'] ?? 0),
        ];
    }

    private function rateDiff(float $current, float $base): ?float
    {
        if ($base == 0.0) {
            return null;
        }
        return (($current - $base) / $base) * 100;
    }

    private function normalizeDate(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return '';
        }
        return date('Y-m-d', $ts);
    }
}
