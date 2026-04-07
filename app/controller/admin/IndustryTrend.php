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

        $jobId = DataImportService::createJob('industry', 'csv', (string) $file->getOriginalName());
        try {
            $parsed = DataImportService::parseCsvFile($tmp);
            $rows = $parsed['rows'];
            $total = count($rows);
            $ok = 0;
            $fail = 0;
            foreach ($rows as $idx => $r) {
                $date = trim((string) ($r['metric_date'] ?? ($r['date'] ?? '')));
                $country = strtoupper(trim((string) ($r['country_code'] ?? ($r['country'] ?? ''))));
                $category = trim((string) ($r['category_name'] ?? ($r['category'] ?? '')));
                if ($date === '' || $country === '' || $category === '') {
                    $fail++;
                    if ($fail <= 20) {
                        DataImportService::addJobLog($jobId, 'warn', 'missing_required_fields', ['row' => $idx + 2]);
                    }
                    continue;
                }
                $payload = [
                    'metric_date' => $date,
                    'country_code' => mb_substr($country, 0, 12),
                    'category_name' => mb_substr($category, 0, 64),
                    'heat_score' => (float) ($r['heat_score'] ?? 0),
                    'content_count' => (int) ($r['content_count'] ?? 0),
                    'engagement_rate' => (float) ($r['engagement_rate'] ?? 0),
                    'cpc' => (float) ($r['cpc'] ?? 0),
                    'cpm' => (float) ($r['cpm'] ?? 0),
                ];
                $exists = GrowthIndustryMetricModel::where('metric_date', $payload['metric_date'])
                    ->where('country_code', $payload['country_code'])
                    ->where('category_name', $payload['category_name'])
                    ->find();
                if ($exists) {
                    $exists->save($payload);
                } else {
                    GrowthIndustryMetricModel::create($payload);
                }
                $ok++;
            }
            $status = $fail > 0 ? ($ok > 0 ? DataImportService::JOB_PARTIAL : DataImportService::JOB_FAILED) : DataImportService::JOB_SUCCESS;
            DataImportService::finishJob($jobId, $status, $total, $ok, $fail, $fail > 0 && $ok === 0 ? 'all_rows_failed' : '');
            return $this->jsonOk([
                'job_id' => $jobId,
                'total_rows' => $total,
                'success_rows' => $ok,
                'failed_rows' => $fail,
            ], 'imported');
        } catch (\Throwable $e) {
            DataImportService::addJobLog($jobId, 'error', 'import_exception', ['message' => $e->getMessage()]);
            DataImportService::finishJob($jobId, DataImportService::JOB_FAILED, 0, 0, 0, 'import_exception');
            return $this->jsonErr('import_failed', 1, ['job_id' => $jobId], 'page.dataImport.importFailed');
        }
    }
}

