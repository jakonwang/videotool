<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Platform as PlatformModel;
use think\facade\View;

class Platform extends BaseController
{
    private function buildBaseUrl(): string
    {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (preg_match('#^(.*?)/admin\.php#', $scriptName, $matches)) {
            $baseUrl .= $matches[1];
        }

        return $baseUrl;
    }

    public function index()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = $this->request->param('status', '');

        $query = $this->scopeTenant(PlatformModel::order('id', 'desc'), 'platforms');
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('name', '%' . $keyword . '%')
                    ->whereOrLike('code', '%' . $keyword . '%');
            });
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $list = $query->paginate([
            'list_rows' => 10,
            'query' => $this->request->param(),
        ]);

        return View::fetch('admin/platform/index', [
            'list' => $list,
            'base_url' => $this->buildBaseUrl(),
            'keyword' => $keyword,
            'status' => $status,
        ]);
    }

    public function listJson()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = $this->request->param('status', '');
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) {
            $pageSize = 10;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        $sortProp = (string) $this->request->param('sort_prop', 'id');
        $sortOrder = strtolower((string) $this->request->param('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'name', 'code', 'status', 'created_at', 'updated_at'];
        if (!in_array($sortProp, $allowedSort, true)) {
            $sortProp = 'id';
        }

        $query = $this->scopeTenant(PlatformModel::order($sortProp, $sortOrder), 'platforms');
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('name', '%' . $keyword . '%')
                    ->whereOrLike('code', '%' . $keyword . '%');
            });
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $baseUrl = $this->buildBaseUrl();
        $items = [];
        foreach ($list as $platform) {
            $code = (string) ($platform->code ?? '');
            $items[] = [
                'id' => (int) $platform->id,
                'name' => (string) ($platform->name ?? ''),
                'code' => $code,
                'icon' => (string) ($platform->icon ?? ''),
                'status' => (int) ($platform->status ?? 0),
                'created_at' => (string) ($platform->created_at ?? ''),
                'updated_at' => (string) ($platform->updated_at ?? ''),
                'link' => $code !== '' ? ($baseUrl . '?platform=' . $code) : '',
            ];
        }

        return json([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'items' => $items,
                'total' => (int) $list->total(),
                'page' => (int) $list->currentPage(),
                'page_size' => (int) $list->listRows(),
            ],
        ]);
    }

    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->withTenantPayload($this->request->post(), 'platforms');
            PlatformModel::create($data);
            return json(['code' => 0, 'msg' => 'saved']);
        }

        return View::fetch('admin/platform/form', ['info' => null]);
    }

    public function edit()
    {
        $id = (int) $this->request->param('id', 0);
        if ($this->request->isPost()) {
            $data = $this->withTenantPayload($this->request->post(), 'platforms');
            $query = $this->scopeTenant(PlatformModel::where('id', $id), 'platforms');
            $query->update($data);
            return json(['code' => 0, 'msg' => 'updated']);
        }

        $query = $this->scopeTenant(PlatformModel::where('id', $id), 'platforms');
        $info = $query->find();
        return View::fetch('admin/platform/form', ['info' => $info]);
    }

    public function delete()
    {
        $id = (int) $this->request->param('id', 0);
        $query = $this->scopeTenant(PlatformModel::where('id', $id), 'platforms');
        $query->delete();

        return json(['code' => 0, 'msg' => 'deleted']);
    }
}
