<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Category as CategoryModel;
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

    private function activeProductCategoryList()
    {
        $query = CategoryModel::where('type', 'product')->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'desc');
        $query = $this->scopeTenant($query, 'categories');

        return $query->select();
    }

    public function index()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = $this->request->param('status', '');

        $query = ProductModel::order('sort_order', 'asc')->order('id', 'desc');
        $query = $this->scopeTenant($query, 'products');
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
        $category = trim((string) $this->request->param('category', ''));

        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) {
            $pageSize = 10;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        $sortProp = (string) $this->request->param('sort_prop', 'sort_order');
        $sortOrder = (string) $this->request->param('sort_order', 'asc');
        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';

        $allowedSort = ['id', 'sort_order', 'updated_at', 'created_at', 'status'];
        if (!in_array($sortProp, $allowedSort, true)) {
            $sortProp = 'sort_order';
        }

        $query = ProductModel::order($sortProp, $sortOrder)->order('id', 'desc');
        $query = $this->scopeTenant($query, 'products');
        if ($keyword !== '') {
            $query->whereLike('name', '%' . $keyword . '%');
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }
        if ($category !== '') {
            $query->where(function ($sub) use ($category) {
                $sub->where('category_name', $category)->whereOr('category_id', (int) $category);
            });
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
        if ($productIds !== []) {
            $statsQuery = Db::name('videos')
                ->fieldRaw('
                    product_id,
                    COUNT(*) AS total_videos,
                    SUM(CASE WHEN is_downloaded = 1 THEN 1 ELSE 0 END) AS downloaded_videos,
                    SUM(CASE WHEN is_downloaded = 0 THEN 1 ELSE 0 END) AS undownloaded_videos
                ')
                ->whereIn('product_id', $productIds)
                ->group('product_id');
            $statsQuery = $this->scopeTenant($statsQuery, 'videos');
            $rows = $statsQuery->select()->toArray();
            foreach ($rows as $r) {
                $statsByProductId[(int) ($r['product_id'] ?? 0)] = [
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
                'category_name' => (string) ($p->category_name ?? ''),
                'category_id' => (int) ($p->category_id ?? 0),
                'goods_url' => (string) ($p->goods_url ?? ''),
                'thumb_url' => (string) ($p->thumb_url ?? ''),
                'tiktok_shop_url' => (string) ($p->tiktok_shop_url ?? ''),
                'status' => (int) ($p->status ?? 0),
                'sort_order' => (int) ($p->sort_order ?? 0),
                'total_videos' => (int) ($st['total_videos'] ?? 0),
                'downloaded_videos' => (int) ($st['downloaded_videos'] ?? 0),
                'undownloaded_videos' => (int) ($st['undownloaded_videos'] ?? 0),
                'updated_at' => (string) ($p->updated_at ?? ''),
                'created_at' => (string) ($p->created_at ?? ''),
            ];
        }

        $catNamesQuery = ProductModel::whereNotNull('category_name')
            ->where('category_name', '<>', '')
            ->distinct(true)
            ->order('category_name', 'asc');
        $catNamesQuery = $this->scopeTenant($catNamesQuery, 'products');

        $catOptionsQuery = CategoryModel::where('type', 'product')
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'desc')
            ->field('id,name');
        $catOptionsQuery = $this->scopeTenant($catOptionsQuery, 'categories');

        return json([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'items' => $items,
                'categories' => $catNamesQuery->column('category_name'),
                'category_options' => $catOptionsQuery->select()->toArray(),
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
            $categoryId = (int) ($data['category_id'] ?? 0);
            $categoryName = trim((string) ($data['category_name'] ?? ''));
            if ($categoryId > 0) {
                $catQuery = CategoryModel::where('id', $categoryId)->where('type', 'product');
                $catQuery = $this->scopeTenant($catQuery, 'categories');
                $cat = $catQuery->find();
                if ($cat) {
                    $categoryName = (string) ($cat->name ?? '');
                }
            }

            $payload = [
                'name' => $name,
                'category_name' => $categoryName !== '' ? $categoryName : null,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'goods_url' => trim((string) ($data['goods_url'] ?? '')) ?: null,
                'thumb_url' => trim((string) ($data['thumb_url'] ?? '')) ?: null,
                'tiktok_shop_url' => trim((string) ($data['tiktok_shop_url'] ?? '')) ?: null,
                'status' => (int) ($data['status'] ?? 1),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ];
            ProductModel::create($this->withTenantPayload($payload, 'products'));

            return json(['code' => 0, 'msg' => '添加成功']);
        }

        return View::fetch('admin/product/form', [
            'info' => null,
            'categories' => $this->activeProductCategoryList(),
        ]);
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

            $categoryId = (int) ($data['category_id'] ?? 0);
            $categoryName = trim((string) ($data['category_name'] ?? ''));
            if ($categoryId > 0) {
                $catQuery = CategoryModel::where('id', $categoryId)->where('type', 'product');
                $catQuery = $this->scopeTenant($catQuery, 'categories');
                $cat = $catQuery->find();
                if ($cat) {
                    $categoryName = (string) ($cat->name ?? '');
                }
            }

            $updateQuery = ProductModel::where('id', $id);
            $updateQuery = $this->scopeTenant($updateQuery, 'products');
            $updateQuery->update([
                'name' => $name,
                'category_name' => $categoryName !== '' ? $categoryName : null,
                'category_id' => $categoryId > 0 ? $categoryId : null,
                'goods_url' => trim((string) ($data['goods_url'] ?? '')) ?: null,
                'thumb_url' => trim((string) ($data['thumb_url'] ?? '')) ?: null,
                'tiktok_shop_url' => trim((string) ($data['tiktok_shop_url'] ?? '')) ?: null,
                'status' => (int) ($data['status'] ?? 1),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]);

            return json(['code' => 0, 'msg' => '修改成功']);
        }

        $infoQuery = ProductModel::where('id', $id);
        $infoQuery = $this->scopeTenant($infoQuery, 'products');
        $info = $infoQuery->find();
        if (!$info) {
            return '商品不存在';
        }

        return View::fetch('admin/product/form', [
            'info' => $info,
            'categories' => $this->activeProductCategoryList(),
        ]);
    }

    public function delete()
    {
        $id = (int) $this->request->param('id');
        $deleteQuery = ProductModel::where('id', $id);
        $deleteQuery = $this->scopeTenant($deleteQuery, 'products');
        $deleteQuery->delete();

        return json(['code' => 0, 'msg' => '删除成功']);
    }

    /**
     * 上传商品缩略图 -> public/uploads/product_thumbs/{Ymd}/
     */
    public function uploadThumb()
    {
        if (!$this->request->isPost()) {
            return json(['code' => 1, 'msg' => '仅支持 POST']);
        }
        $file = $this->request->file('file');
        if (!$file) {
            return json(['code' => 1, 'msg' => '请选择图片']);
        }
        $ext = strtolower((string) $file->extension());
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return json(['code' => 1, 'msg' => '仅支持 jpg/png/gif/webp']);
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            return json(['code' => 1, 'msg' => '图片不超过 5MB']);
        }
        $dateStr = date('Ymd');
        $baseDir = root_path() . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'product_thumbs' . DIRECTORY_SEPARATOR . $dateStr . DIRECTORY_SEPARATOR;
        if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
            return json(['code' => 1, 'msg' => '无法创建上传目录']);
        }
        $saveName = bin2hex(random_bytes(8)) . '.' . $ext;
        try {
            $file->move($baseDir, $saveName);
        } catch (\Throwable $e) {
            return json(['code' => 1, 'msg' => '上传失败：' . $e->getMessage()]);
        }
        $url = '/uploads/product_thumbs/' . $dateStr . '/' . $saveName;

        return json(['code' => 0, 'msg' => 'ok', 'data' => ['url' => $url]]);
    }
}

