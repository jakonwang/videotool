<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\DataSource as DataSourceModel;
use app\model\ImportJob as ImportJobModel;
use app\model\ImportJobLog as ImportJobLogModel;
use think\facade\View;

class DataImport extends BaseController
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
        return View::fetch('admin/data_import/index', []);
    }

    public function sourceListJson()
    {
        $rows = DataSourceModel::order('id', 'desc')->select();
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'code' => (string) ($row->code ?? ''),
                'name' => (string) ($row->name ?? ''),
                'source_type' => (string) ($row->source_type ?? 'csv'),
                'adapter_key' => (string) ($row->adapter_key ?? ''),
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
        $configJson = is_array($config) ? json_encode($config, JSON_UNESCAPED_UNICODE) : trim((string) ($payload['config_json'] ?? ''));

        if ($id > 0) {
            $row = DataSourceModel::find($id);
            if (!$row) {
                return $this->jsonErr('not_found', 1, null, 'common.notFound');
            }
            $row->code = mb_substr($code, 0, 64);
            $row->name = mb_substr($name, 0, 128);
            $row->source_type = $sourceType;
            $row->adapter_key = $adapterKey !== '' ? mb_substr($adapterKey, 0, 64) : null;
            $row->status = $status;
            $row->config_json = $configJson !== '' ? $configJson : null;
            $row->save();
        } else {
            DataSourceModel::create([
                'code' => mb_substr($code, 0, 64),
                'name' => mb_substr($name, 0, 128),
                'source_type' => $sourceType,
                'adapter_key' => $adapterKey !== '' ? mb_substr($adapterKey, 0, 64) : null,
                'status' => $status,
                'config_json' => $configJson !== '' ? $configJson : null,
            ]);
        }

        return $this->jsonOk([], 'saved');
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
        DataSourceModel::destroy($id);
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
        $rows = ImportJobLogModel::where('job_id', $jobId)->order('id', 'desc')->limit(200)->select();
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

