<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\DataSource as DataSourceModel;
use app\model\ImportJob as ImportJobModel;
use app\model\ImportJobLog as ImportJobLogModel;
use app\service\DataImportDispatchService;
use app\service\DataImportService;
use think\facade\View;

class DataImport extends BaseController
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
        return View::fetch('admin/data_import/index', []);
    }

    public function sourceListJson()
    {
        $rowsQuery = DataSourceModel::order('id', 'desc');
        $rowsQuery = $this->scopeTenant($rowsQuery, 'data_sources');
        $rows = $rowsQuery->select();
        $items = [];
        foreach ($rows as $row) {
            $config = DataImportDispatchService::parseConfig((string) ($row->config_json ?? ''));
            $domain = mb_strtolower(trim((string) ($config['domain'] ?? '')), 'UTF-8');
            if (!in_array($domain, ['industry', 'competitor', 'ads'], true)) {
                $domain = '';
            }
            $items[] = [
                'id' => (int) $row->id,
                'code' => (string) ($row->code ?? ''),
                'name' => (string) ($row->name ?? ''),
                'source_type' => (string) ($row->source_type ?? 'csv'),
                'adapter_key' => (string) ($row->adapter_key ?? ''),
                'domain' => $domain,
                'status' => (int) ($row->status ?? 1),
                'config_json' => (string) ($row->config_json ?? ''),
                'updated_at' => (string) ($row->updated_at ?? ''),
            ];
        }
        return $this->jsonOk(['items' => $items]);
    }

    public function sourceSave()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $code = trim((string) ($payload['code'] ?? ''));
        $name = trim((string) ($payload['name'] ?? ''));
        $sourceType = trim((string) ($payload['source_type'] ?? 'csv'));
        $adapterKey = trim((string) ($payload['adapter_key'] ?? ''));
        $status = (int) ($payload['status'] ?? 1) === 0 ? 0 : 1;
        if ($code === '' || $name === '') {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        if (!in_array($sourceType, ['csv', 'api'], true)) {
            $sourceType = 'csv';
        }
        $config = $payload['config'] ?? [];
        if (!is_array($config)) {
            $config = DataImportDispatchService::parseConfig(trim((string) ($payload['config_json'] ?? '')));
        }
        $domain = mb_strtolower(trim((string) ($payload['domain'] ?? ($config['domain'] ?? ''))), 'UTF-8');
        if (!in_array($domain, ['industry', 'competitor', 'ads'], true)) {
            $domain = '';
        }
        if ($domain !== '') {
            $config['domain'] = $domain;
        } else {
            unset($config['domain']);
        }
        $configJson = $config !== [] ? json_encode($config, JSON_UNESCAPED_UNICODE) : null;

        if ($id > 0) {
            $rowQuery = DataSourceModel::where('id', $id);
            $rowQuery = $this->scopeTenant($rowQuery, 'data_sources');
            $row = $rowQuery->find();
            if (!$row) {
                return $this->jsonErr('not_found', 1, null, 'common.notFound');
            }
            $row->code = mb_substr($code, 0, 64);
            $row->name = mb_substr($name, 0, 128);
            $row->source_type = $sourceType;
            $row->adapter_key = $adapterKey !== '' ? mb_substr($adapterKey, 0, 64) : null;
            $row->status = $status;
            $row->config_json = $configJson;
            $row->save();
        } else {
            $createPayload = $this->withTenantPayload([
                'code' => mb_substr($code, 0, 64),
                'name' => mb_substr($name, 0, 128),
                'source_type' => $sourceType,
                'adapter_key' => $adapterKey !== '' ? mb_substr($adapterKey, 0, 64) : null,
                'status' => $status,
                'config_json' => $configJson,
            ], 'data_sources');
            DataSourceModel::create($createPayload);
        }

        return $this->jsonOk([], 'saved');
    }

    public function adapterListJson()
    {
        return $this->jsonOk(['items' => DataImportDispatchService::adapterOptions()]);
    }

    public function sourceDelete()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $query = DataSourceModel::where('id', $id);
        $query = $this->scopeTenant($query, 'data_sources');
        $query->delete();
        return $this->jsonOk([], 'deleted');
    }

    public function jobListJson()
    {
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }
        $domain = trim((string) $this->request->param('domain', ''));
        $status = (string) $this->request->param('status', '');
        $query = ImportJobModel::order('id', 'desc');
        $query = $this->scopeTenant($query, 'import_jobs');
        if ($domain !== '') {
            $query->where('domain', $domain);
        }
        if ($status !== '') {
            $query->where('status', (int) $status);
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
                'source_id' => (int) ($row->source_id ?? 0),
                'domain' => (string) ($row->domain ?? ''),
                'job_type' => (string) ($row->job_type ?? ''),
                'file_name' => (string) ($row->file_name ?? ''),
                'status' => (int) ($row->status ?? 0),
                'total_rows' => (int) ($row->total_rows ?? 0),
                'success_rows' => (int) ($row->success_rows ?? 0),
                'failed_rows' => (int) ($row->failed_rows ?? 0),
                'error_message' => (string) ($row->error_message ?? ''),
                'started_at' => (string) ($row->started_at ?? ''),
                'finished_at' => (string) ($row->finished_at ?? ''),
                'created_at' => (string) ($row->created_at ?? ''),
                'can_retry' => in_array((int) ($row->status ?? 0), [DataImportService::JOB_FAILED, DataImportService::JOB_PARTIAL], true)
                    && DataImportService::extractRowsFromPayload((string) ($row->payload_json ?? '')) !== [],
            ];
        }

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ]);
    }

    public function jobLogsJson()
    {
        $jobId = (int) $this->request->param('job_id', 0);
        if ($jobId <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $rowsQuery = ImportJobLogModel::where('job_id', $jobId)->order('id', 'desc')->limit(200);
        $rowsQuery = $this->scopeTenant($rowsQuery, 'import_job_logs');
        $rows = $rowsQuery->select();
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'level' => (string) ($row->level ?? 'info'),
                'message' => (string) ($row->message ?? ''),
                'context_json' => (string) ($row->context_json ?? ''),
                'created_at' => (string) ($row->created_at ?? ''),
            ];
        }
        return $this->jsonOk(['items' => $items]);
    }

    public function retryJob()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $jobId = (int) ($payload['job_id'] ?? 0);
        if ($jobId <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        $jobQuery = ImportJobModel::where('id', $jobId);
        $jobQuery = $this->scopeTenant($jobQuery, 'import_jobs');
        $job = $jobQuery->find();
        if (!$job) {
            return $this->jsonErr('not_found', 1, null, 'common.notFound');
        }
        $domain = trim((string) ($job->domain ?? ''));
        $rows = DataImportService::extractRowsFromPayload((string) ($job->payload_json ?? ''));
        if ($rows === []) {
            return $this->jsonErr('not_retryable', 1, null, 'page.dataImportCenter.notRetryable');
        }

        $newJobId = DataImportService::createJob(
            $domain !== '' ? $domain : 'generic',
            (string) ($job->job_type ?? 'csv'),
            (string) ($job->file_name ?? ''),
            (int) ($job->source_id ?? 0) > 0 ? (int) $job->source_id : null,
            [
                'retry_of' => $jobId,
                'rows' => $rows,
            ]
        );
        $result = DataImportService::runDomainImport($domain, $rows, $newJobId);

        return $this->jsonOk([
            'job_id' => $newJobId,
            'retry_of' => $jobId,
            'status' => (int) $result['status'],
            'total_rows' => (int) $result['total_rows'],
            'success_rows' => (int) $result['success_rows'],
            'failed_rows' => (int) $result['failed_rows'],
        ], 'retried');
    }

    public function runSource()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $sourceId = (int) ($payload['source_id'] ?? 0);
        $domain = trim((string) ($payload['domain'] ?? ''));
        if ($sourceId <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        try {
            $result = DataImportDispatchService::runSource($sourceId, $domain);
            return $this->jsonOk($result, 'run_done');
        } catch (\Throwable $e) {
            $map = [
                'source_not_found' => 'common.notFound',
                'source_disabled' => 'page.dataImportCenter.sourceDisabled',
                'adapter_key_required' => 'page.dataImportCenter.adapterRequired',
                'domain_required' => 'page.dataImportCenter.domainRequired',
                'adapter_dispatch_failed' => 'page.dataImportCenter.runFailed',
            ];
            $key = trim($e->getMessage());
            return $this->jsonErr($key !== '' ? $key : 'run_failed', 1, null, $map[$key] ?? 'page.dataImportCenter.runFailed');
        }
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
