<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Platform as PlatformModel;
use think\facade\View;

/**
 * 平台管理
 */
class Platform extends BaseController
{
    private function buildBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
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
        $keyword = trim((string)$this->request->param('keyword', ''));
        $status = $this->request->param('status', '');
        
        $query = PlatformModel::order('id', 'desc');
        
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('name', '%' . $keyword . '%')
                    ->whereOr('code', 'like', '%' . $keyword . '%');
            });
        }
        
        if ($status !== '' && $status !== null) {
            $query->where('status', (int)$status);
        }
        
        $list = $query->paginate([
            'list_rows' => 10,
            'query' => $this->request->param()
        ]);
        
        return View::fetch('admin/platform/index', [
            'list' => $list,
            'base_url' => $this->buildBaseUrl(),
            'keyword' => $keyword,
            'status' => $status
        ]);
    }

    /**
     * 平台列表（JSON）
     * 供 Vue/ElementPlus 页面使用，复用 index() 的筛选规则
     */
    public function listJson()
    {
        $keyword = trim((string)$this->request->param('keyword', ''));
        $status = $this->request->param('status', '');

        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) $pageSize = 10;
        if ($pageSize > 100) $pageSize = 100;

        $sortProp = (string) $this->request->param('sort_prop', 'id');
        $sortOrder = (string) $this->request->param('sort_order', 'desc'); // asc|desc
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        $allowedSort = ['id', 'name', 'code', 'status', 'created_at', 'updated_at'];
        if (!in_array($sortProp, $allowedSort, true)) {
            $sortProp = 'id';
        }

        $query = PlatformModel::order($sortProp, $sortOrder);

        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('name', '%' . $keyword . '%')
                    ->whereOr('code', 'like', '%' . $keyword . '%');
            });
        }

        if ($status !== '' && $status !== null) {
            $query->where('status', (int)$status);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param()
        ]);

        $baseUrl = $this->buildBaseUrl();
        $items = [];
        foreach ($list as $p) {
            $code = (string) ($p->code ?? '');
            $items[] = [
                'id' => (int) $p->id,
                'name' => (string) ($p->name ?? ''),
                'code' => $code,
                'icon' => (string) ($p->icon ?? ''),
                'status' => (int) ($p->status ?? 0),
                'created_at' => (string) ($p->created_at ?? ''),
                'updated_at' => (string) ($p->updated_at ?? ''),
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
            $data = $this->request->post();
            PlatformModel::create($data);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return View::fetch('admin/platform/form', ['info' => null]);
    }
    
    public function edit()
    {
        $id = $this->request->param('id');
        if ($this->request->isPost()) {
            $data = $this->request->post();
            PlatformModel::where('id', $id)->update($data);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
        $info = PlatformModel::find($id);
        return View::fetch('admin/platform/form', ['info' => $info]);
    }
    
    public function delete()
    {
        $id = $this->request->param('id');
        PlatformModel::destroy($id);
        return json(['code' => 0, 'msg' => '删除成功']);
    }
}

