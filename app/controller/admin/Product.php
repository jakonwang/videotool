<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Product as ProductModel;
use think\facade\Db;
use think\facade\View;

/**
 * 商品管理
 */
class Product extends BaseController
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
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = $this->request->param('status', '');

        $query = ProductModel::order('sort_order', 'asc')->order('id', 'desc');
        if ($keyword !== '') {
            $query->whereLike('name', '%' . $keyword . '%');
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $list = $query->paginate([
            'list_rows' => 15,
            'query' => $this->request->param(),
        ]);

        return View::fetch('admin/product/index', [
            'list' => $list,
            'keyword' => $keyword,
            'status' => $status,
            'base_url' => $this->buildBaseUrl(),
        ]);
    }

    /**
     * 商品列表（JSON）
     * 供 Vue/ElementPlus 页面使用，复用 index() 的筛选规则
     */
    public function listJson()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = $this->request->param('status', '');

        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) $pageSize = 10;
        if ($pageSize > 100) $pageSize = 100;

        $sortProp = (string) $this->request->param('sort_prop', 'sort_order');
        $sortOrder = (string) $this->request->param('sort_order', 'asc'); // asc|desc
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        $allowedSort = ['id', 'sort_order', 'updated_at', 'created_at', 'status'];
        if (!in_array($sortProp, $allowedSort, true)) {
            $sortProp = 'sort_order';
        }

        $query = ProductModel::order($sortProp, $sortOrder)->order('id', 'desc');
        if ($keyword !== '') {
            $query->whereLike('name', '%' . $keyword . '%');
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $productIds = [];
        foreach ($list as $p) {
            $pid = (int) ($p->id ?? 0);
            if ($pid > 0) {
                $productIds[] = $pid;
            }
        }
        $statsByProductId = [];
        if (!empty($productIds)) {
            $rows = Db::name('videos')
                ->fieldRaw("
                    product_id,
                    COUNT(*) AS total_videos,
                    SUM(CASE WHEN is_downloaded = 1 THEN 1 ELSE 0 END) AS downloaded_videos,
                    SUM(CASE WHEN is_downloaded = 0 THEN 1 ELSE 0 END) AS undownloaded_videos
                ")
                ->whereIn('product_id', $productIds)
                ->group('product_id')
                ->select()
                ->toArray();
            foreach ($rows as $r) {
                $statsByProductId[(int) $r['product_id']] = [
                    'total_videos' => (int) ($r['total_videos'] ?? 0),
                    'downloaded_videos' => (int) ($r['downloaded_videos'] ?? 0),
                    'undownloaded_videos' => (int) ($r['undownloaded_videos'] ?? 0),
                ];
            }
        }

        $items = [];
        foreach ($list as $p) {
            $pid = (int) ($p->id ?? 0);
            $st = $statsByProductId[$pid] ?? null;
            $items[] = [
                'id' => $pid,
                'name' => (string) ($p->name ?? ''),
                'goods_url' => (string) ($p->goods_url ?? ''),
                'status' => (int) ($p->status ?? 0),
                'sort_order' => (int) ($p->sort_order ?? 0),
                'total_videos' => (int) ($st['total_videos'] ?? 0),
                'downloaded_videos' => (int) ($st['downloaded_videos'] ?? 0),
                'undownloaded_videos' => (int) ($st['undownloaded_videos'] ?? 0),
                'updated_at' => (string) ($p->updated_at ?? ''),
                'created_at' => (string) ($p->created_at ?? ''),
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
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                return json(['code' => 1, 'msg' => '请填写商品名称']);
            }
            ProductModel::create([
                'name' => $name,
                'goods_url' => trim((string) ($data['goods_url'] ?? '')) ?: null,
                'status' => (int) ($data['status'] ?? 1),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return View::fetch('admin/product/form', ['info' => null]);
    }

    public function edit()
    {
        $id = (int) $this->request->param('id');
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                return json(['code' => 1, 'msg' => '请填写商品名称']);
            }
            ProductModel::where('id', $id)->update([
                'name' => $name,
                'goods_url' => trim((string) ($data['goods_url'] ?? '')) ?: null,
                'status' => (int) ($data['status'] ?? 1),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
        $info = ProductModel::find($id);
        if (!$info) {
            return '商品不存在';
        }
        return View::fetch('admin/product/form', ['info' => $info]);
    }

    public function delete()
    {
        $id = $this->request->param('id');
        ProductModel::destroy($id);
        return json(['code' => 0, 'msg' => '删除成功']);
    }
}
