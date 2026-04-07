<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\GrowthCompetitor as GrowthCompetitorModel;
use app\model\GrowthCompetitorMetric as GrowthCompetitorMetricModel;
use app\service\DataImportService;
use think\facade\View;

class CompetitorAnalysis extends BaseController
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
        return View::fetch('admin/competitor_analysis/index', []);
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
        $query = GrowthCompetitorModel::alias('c')
            ->field('c.*')
            ->order('c.id', 'desc');
        if ($keyword !== '') {
            $query->whereLike('c.name', '%' . $keyword . '%');
        }
        if ($platform !== '') {
            $query->where('c.platform', $platform);
        }
        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $latestMetric = GrowthCompetitorMetricModel::where('competitor_id', (int) $row->id)
                ->order('metric_date', 'desc')
                ->find();
            $items[] = [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'platform' => (string) ($row->platform ?? ''),
                'region' => (string) ($row->region ?? ''),
                'category_name' => (string) ($row->category_name ?? ''),
                'status' => (int) ($row->status ?? 1),
                'notes' => (string) ($row->notes ?? ''),
                'latest_metric_date' => (string) ($latestMetric->metric_date ?? ''),
                'latest_followers' => (int) ($latestMetric->followers ?? 0),
                'latest_engagement_rate' => (float) ($latestMetric->engagement_rate ?? 0),
                'latest_content_count' => (int) ($latestMetric->content_count ?? 0),
                'latest_conversion_proxy' => (float) ($latestMetric->conversion_proxy ?? 0),
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

    public function saveCompetitor()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $platform = trim((string) ($payload['platform'] ?? 'tiktok'));
        $region = trim((string) ($payload['region'] ?? ''));
        $category = trim((string) ($payload['category_name'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $status = (int) ($payload['status'] ?? 1) === 0 ? 0 : 1;

        if ($id > 0) {
            $row = GrowthCompetitorModel::find($id);
            if (!$row) {
                return $this->jsonErr('not_found', 1, null, 'common.notFound');
            }
            $row->name = mb_substr($name, 0, 128);
            $row->platform = mb_substr($platform, 0, 32);
            $row->region = $region !== '' ? mb_substr($region, 0, 16) : null;
            $row->category_name = $category !== '' ? mb_substr($category, 0, 64) : null;
            $row->notes = $notes !== '' ? mb_substr($notes, 0, 255) : null;
            $row->status = $status;
            $row->save();
        } else {
            GrowthCompetitorModel::create([
                'name' => mb_substr($name, 0, 128),
                'platform' => mb_substr($platform, 0, 32),
                'region' => $region !== '' ? mb_substr($region, 0, 16) : null,
                'category_name' => $category !== '' ? mb_substr($category, 0, 64) : null,
                'notes' => $notes !== '' ? mb_substr($notes, 0, 255) : null,
                'status' => $status,
            ]);
        }
        return $this->jsonOk([], 'saved');
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
            $jobId = DataImportService::createJob('competitor', 'csv', (string) $file->getOriginalName(), null, [
                'headers' => $parsed['headers'],
                'rows' => $rows,
            ]);
            $result = DataImportService::runDomainImport('competitor', $rows, $jobId);
            return $this->jsonOk([
                'job_id' => $jobId,
                'total_rows' => (int) $result['total_rows'],
                'success_rows' => (int) $result['success_rows'],
                'failed_rows' => (int) $result['failed_rows'],
            ], 'imported');
        } catch (\Throwable $e) {
            $jobId = DataImportService::createJob('competitor', 'csv', (string) $file->getOriginalName());
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
            $filename = 'competitor_analysis_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return $this->jsonErr('export_failed', 1, null, 'common.operationFailed');
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'name', 'platform', 'region', 'category_name', 'status', 'latest_metric_date', 'latest_followers', 'latest_engagement_rate', 'latest_content_count', 'latest_conversion_proxy', 'updated_at']);

            $query = GrowthCompetitorModel::alias('c')
                ->field('c.*')
                ->order('c.id', 'desc');
            if ($keyword !== '') {
                $query->whereLike('c.name', '%' . $keyword . '%');
            }
            if ($platform !== '') {
                $query->where('c.platform', $platform);
            }
            $rows = $query->select();
            foreach ($rows as $row) {
                $r = is_array($row) ? $row : $row->toArray();
                $latestMetric = GrowthCompetitorMetricModel::where('competitor_id', (int) ($r['id'] ?? 0))
                    ->order('metric_date', 'desc')
                    ->find();
                fputcsv($out, [
                    (int) ($r['id'] ?? 0),
                    (string) ($r['name'] ?? ''),
                    (string) ($r['platform'] ?? ''),
                    (string) ($r['region'] ?? ''),
                    (string) ($r['category_name'] ?? ''),
                    (int) ($r['status'] ?? 1),
                    (string) ($latestMetric->metric_date ?? ''),
                    (int) ($latestMetric->followers ?? 0),
                    (float) ($latestMetric->engagement_rate ?? 0),
                    (int) ($latestMetric->content_count ?? 0),
                    (float) ($latestMetric->conversion_proxy ?? 0),
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

    /**
     * @return array<string, mixed>
     */
    private function parseJsonOrPost(): array
    {
        $raw = (string) $this->request->getContent();
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                return $j;
            }
        }
        return $this->request->post();
    }
}
