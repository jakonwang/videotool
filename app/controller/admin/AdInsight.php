<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\GrowthAdCreative as GrowthAdCreativeModel;
use app\model\GrowthAdMetric as GrowthAdMetricModel;
use app\service\DataImportService;
use think\facade\View;

class AdInsight extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return $this->apiJsonOk($data, $msg);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        return $this->apiJsonErr($msg, $code, $data, $errorKey);
    }

    public function index()
    {
        return View::fetch('admin/ad_insight/index', []);
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
        $keyword = trim((string) $this->request->param('keyword', ''));
        $platform = trim((string) $this->request->param('platform', ''));
        $query = GrowthAdCreativeModel::order('id', 'desc');
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('creative_code', '%' . $keyword . '%')
                    ->whereOr('title', 'like', '%' . $keyword . '%');
            });
        }
        if ($platform !== '') {
            $query->where('platform', $platform);
        }
        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);
        $items = [];
        foreach ($list as $row) {
            $latestMetric = GrowthAdMetricModel::where('creative_id', (int) $row->id)
                ->order('metric_date', 'desc')
                ->find();
            $items[] = [
                'id' => (int) $row->id,
                'creative_code' => (string) ($row->creative_code ?? ''),
                'title' => (string) ($row->title ?? ''),
                'platform' => (string) ($row->platform ?? ''),
                'region' => (string) ($row->region ?? ''),
                'category_name' => (string) ($row->category_name ?? ''),
                'landing_url' => (string) ($row->landing_url ?? ''),
                'first_seen_at' => (string) ($row->first_seen_at ?? ''),
                'last_seen_at' => (string) ($row->last_seen_at ?? ''),
                'status' => (int) ($row->status ?? 1),
                'metric_date' => (string) ($latestMetric->metric_date ?? ''),
                'impressions' => (int) ($latestMetric->impressions ?? 0),
                'clicks' => (int) ($latestMetric->clicks ?? 0),
                'ctr' => (float) ($latestMetric->ctr ?? 0),
                'cpc' => (float) ($latestMetric->cpc ?? 0),
                'cpm' => (float) ($latestMetric->cpm ?? 0),
                'est_spend' => (float) ($latestMetric->est_spend ?? 0),
                'active_days' => (int) ($latestMetric->active_days ?? 0),
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

        try {
            $parsed = DataImportService::parseCsvFile($tmp);
            $rows = $parsed['rows'];
            $jobId = DataImportService::createJob('ads', 'csv', (string) $file->getOriginalName(), null, [
                'headers' => $parsed['headers'],
                'rows' => $rows,
            ]);
            $result = DataImportService::runDomainImport('ads', $rows, $jobId);
            return $this->jsonOk([
                'job_id' => $jobId,
                'total_rows' => (int) $result['total_rows'],
                'success_rows' => (int) $result['success_rows'],
                'failed_rows' => (int) $result['failed_rows'],
            ], 'imported');
        } catch (\Throwable $e) {
            $jobId = DataImportService::createJob('ads', 'csv', (string) $file->getOriginalName());
            DataImportService::addJobLog($jobId, 'error', 'import_exception', ['message' => $e->getMessage()]);
            DataImportService::finishJob($jobId, DataImportService::JOB_FAILED, 0, 0, 0, 'import_exception');
            return $this->jsonErr('import_failed', 1, ['job_id' => $jobId], 'page.dataImport.importFailed');
        }
    }

    public function exportCsv()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $platform = trim((string) $this->request->param('platform', ''));
        try {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $filename = 'ad_insight_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return $this->jsonErr('export_failed', 1, null, 'common.operationFailed');
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'creative_code', 'title', 'platform', 'region', 'category_name', 'impressions', 'clicks', 'ctr', 'cpc', 'cpm', 'est_spend', 'active_days', 'updated_at']);

            $query = GrowthAdCreativeModel::order('id', 'desc');
            if ($keyword !== '') {
                $query->where(function ($sub) use ($keyword) {
                    $sub->whereLike('creative_code', '%' . $keyword . '%')
                        ->whereOr('title', 'like', '%' . $keyword . '%');
                });
            }
            if ($platform !== '') {
                $query->where('platform', $platform);
            }
            $rows = $query->select();
            foreach ($rows as $row) {
                $r = is_array($row) ? $row : $row->toArray();
                $latestMetric = GrowthAdMetricModel::where('creative_id', (int) ($r['id'] ?? 0))
                    ->order('metric_date', 'desc')
                    ->find();
                fputcsv($out, [
                    (int) ($r['id'] ?? 0),
                    (string) ($r['creative_code'] ?? ''),
                    (string) ($r['title'] ?? ''),
                    (string) ($r['platform'] ?? ''),
                    (string) ($r['region'] ?? ''),
                    (string) ($r['category_name'] ?? ''),
                    (int) ($latestMetric->impressions ?? 0),
                    (int) ($latestMetric->clicks ?? 0),
                    (float) ($latestMetric->ctr ?? 0),
                    (float) ($latestMetric->cpc ?? 0),
                    (float) ($latestMetric->cpm ?? 0),
                    (float) ($latestMetric->est_spend ?? 0),
                    (int) ($latestMetric->active_days ?? 0),
                    (string) ($r['updated_at'] ?? ''),
                ]);
            }
            fclose($out);
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                return $this->jsonErr('export_failed', 1, null, 'common.operationFailed');
            }
        }
        exit;
    }
}
