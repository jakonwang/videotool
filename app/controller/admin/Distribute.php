<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Product as ProductModel;
use app\model\ProductLink as ProductLinkModel;
use think\facade\View;

/**
 * 达人分发链接
 */
class Distribute extends BaseController
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

    private function generateToken(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $token = bin2hex(random_bytes(16));
            if (!ProductLinkModel::where('token', $token)->find()) {
                return $token;
            }
        }
        return bin2hex(random_bytes(16));
    }

    public function index()
    {
        $productId = (int) $this->request->param('product_id', 0);
        $query = ProductLinkModel::with(['product'])->order('id', 'desc');
        if ($productId > 0) {
            $query->where('product_id', $productId);
        }
        $list = $query->paginate([
            'list_rows' => 15,
            'query' => $this->request->param(),
        ]);
        $products = ProductModel::where('status', 1)->order('sort_order', 'asc')->order('id', 'desc')->select();

        return View::fetch('admin/distribute/index', [
            'list' => $list,
            'products' => $products,
            'product_id' => $productId,
            'base_url' => $this->buildBaseUrl(),
        ]);
    }

    /**
     * 达人链列表（JSON）
     * 供 Vue/ElementPlus 页面使用，复用 index() 的筛选规则
     */
    public function listJson()
    {
        $productId = (int) $this->request->param('product_id', 0);

        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) $pageSize = 10;
        if ($pageSize > 100) $pageSize = 100;

        $query = ProductLinkModel::with(['product'])->order('id', 'desc');
        if ($productId > 0) {
            $query->where('product_id', $productId);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $baseUrl = $this->buildBaseUrl();
        $items = [];
        foreach ($list as $row) {
            $token = (string) ($row->token ?? '');
            $items[] = [
                'id' => (int) $row->id,
                'product' => $row->product ? [
                    'id' => (int) ($row->product_id ?? 0),
                    'name' => (string) ($row->product->name ?? ''),
                ] : null,
                'label' => (string) ($row->label ?? ''),
                'token' => $token,
                'status' => (int) ($row->status ?? 0),
                'created_at' => (string) ($row->created_at ?? ''),
                'link' => $token !== '' ? ($baseUrl . '/index.php/d/' . $token) : '',
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
            $productId = (int) $this->request->post('product_id', 0);
            $label = trim((string) $this->request->post('label', ''));
            if ($productId <= 0) {
                return json(['code' => 1, 'msg' => '请选择商品']);
            }
            $product = ProductModel::where('id', $productId)->where('status', 1)->find();
            if (!$product) {
                return json(['code' => 1, 'msg' => '商品不存在或未启用']);
            }
            ProductLinkModel::create([
                'product_id' => $productId,
                'token' => $this->generateToken(),
                'label' => $label !== '' ? $label : null,
                'status' => 1,
            ]);
            return json(['code' => 0, 'msg' => '生成成功']);
        }
        $products = ProductModel::where('status', 1)->order('sort_order', 'asc')->order('id', 'desc')->select();
        return View::fetch('admin/distribute/form', ['products' => $products]);
    }

    public function delete()
    {
        $id = $this->request->param('id');
        ProductLinkModel::destroy($id);
        return json(['code' => 0, 'msg' => '已删除']);
    }

    public function toggle()
    {
        $id = (int) $this->request->param('id', 0);
        $row = ProductLinkModel::find($id);
        if (!$row) {
            return json(['code' => 1, 'msg' => '记录不存在']);
        }
        $row->status = (int) $row->status === 1 ? 0 : 1;
        $row->save();
        return json(['code' => 0, 'msg' => '已更新']);
    }
}
