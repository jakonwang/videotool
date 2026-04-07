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
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        return json(['code' => $code, 'msg' => $msg, 'error_key' => $errorKey, 'data' => $data]);
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

        $jobId = DataImportService::createJob('ads', 'csv', (string) $file->getOriginalName());
        try {
            $parsed = DataImportService::parseCsvFile($tmp);
            $rows = $parsed['rows'];
            $total = count($rows);
            $ok = 0;
            $fail = 0;
            foreach ($rows as $idx => $r) {
                $creativeCode = trim((string) ($r['creative_code'] ?? ($r['creative_id'] ?? '')));
                $metricDate = trim((string) ($r['metric_date'] ?? ($r['date'] ?? '')));
                if ($creativeCode === '' || $metricDate === '') {
                    $fail++;
                    if ($fail <= 20) {
                        DataImportService::addJobLog($jobId, 'warn', 'missing_required_fields', ['row' => $idx + 2]);
                    }
                    continue;
                }
                $creative = GrowthAdCreativeModel::where('creative_code', $creativeCode)->find();
                if (!$creative) {
                    $creative = GrowthAdCreativeModel::create([
                        'creative_code' => mb_substr($creativeCode, 0, 64),
                        'title' => mb_substr((string) ($r['title'] ?? ''), 0, 255),
                        'platform' => mb_substr(trim((string) ($r['platform'] ?? 'tiktok')), 0, 32),
                        'region' => trim((string) ($r['region'] ?? '')) ?: null,
                        'category_name' => trim((string) ($r['category_name'] ?? ($r['category'] ?? ''))) ?: null,
                        'landing_url' => trim((string) ($r['landing_url'] ?? '')) ?: null,
                        'first_seen_at' => trim((string) ($r['first_seen_at'] ?? '')) ?: null,
                        'last_seen_at' => trim((string) ($r['last_seen_at'] ?? '')) ?: null,
                        'status' => 1,
                    ]);
                } else {
                    $creative->title = trim((string) ($r['title'] ?? '')) !== '' ? mb_substr((string) ($r['title'] ?? ''), 0, 255) : (string) $creative->title;
                    $creative->platform = trim((string) ($r['platform'] ?? '')) !== '' ? mb_substr((string) ($r['platform'] ?? ''), 0, 32) : (string) $creative->platform;
                    $creative->region = trim((string) ($r['region'] ?? '')) !== '' ? mb_substr((string) ($r['region'] ?? ''), 0, 16) : $creative->region;
                    $creative->category_name = trim((string) ($r['category_name'] ?? ($r['category'] ?? ''))) !== '' ? mb_substr((string) ($r['category_name'] ?? ($r['category'] ?? '')), 0, 64) : $creative->category_name;
                    $creative->landing_url = trim((string) ($r['landing_url'] ?? '')) !== '' ? mb_substr((string) ($r['landing_url'] ?? ''), 0, 512) : $creative->landing_url;
                    $creative->first_seen_at = trim((string) ($r['first_seen_at'] ?? '')) !== '' ? (string) ($r['first_seen_at'] ?? '') : $creative->first_seen_at;
                    $creative->last_seen_at = trim((string) ($r['last_seen_at'] ?? '')) !== '' ? (string) ($r['last_seen_at'] ?? '') : $creative->last_seen_at;
                    $creative->save();
                }
                $payload = [
                    'creative_id' => (int) $creative->id,
                    'metric_date' => $metricDate,
                    'impressions' => max(0, (int) ($r['impressions'] ?? 0)),
                    'clicks' => max(0, (int) ($r['clicks'] ?? 0)),
                    'ctr' => (float) ($r['ctr'] ?? 0),
                    'cpc' => (float) ($r['cpc'] ?? 0),
                    'cpm' => (float) ($r['cpm'] ?? 0),
                    'est_spend' => (float) ($r['est_spend'] ?? ($r['spend'] ?? 0)),
                    'active_days' => max(0, (int) ($r['active_days'] ?? 0)),
                ];
                $exists = GrowthAdMetricModel::where('creative_id', $payload['creative_id'])
                    ->where('metric_date', $payload['metric_date'])
                    ->find();
                if ($exists) {
                    $exists->save($payload);
                } else {
                    GrowthAdMetricModel::create($payload);
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

