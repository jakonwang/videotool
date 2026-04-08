<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Category as CategoryModel;
use think\facade\View;

/**
 * 分类管理（商品/达人）
 */
class Category extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return $this->apiJsonOk($data, $msg);
    }

    private function jsonErr(string $msg, int $code = 1, string $errorKey = '')
    {
        return $this->apiJsonErr($msg, $code, null, $errorKey);
    }

    public function index()
    {
        return View::fetch('admin/category/index', []);
    }

    public function listJson()
    {
        $type = trim((string) $this->request->param('type', ''));
        $status = (string) $this->request->param('status', '');
        $keyword = trim((string) $this->request->param('keyword', ''));
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }

        $query = CategoryModel::order('sort_order', 'asc')->order('id', 'desc');
        if (in_array($type, ['product', 'influencer'], true)) {
            $query->where('type', $type);
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }
        if ($keyword !== '') {
            $query->whereLike('name', '%' . $keyword . '%');
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
                'name' => (string) ($row->name ?? ''),
                'type' => (string) ($row->type ?? ''),
                'sort_order' => (int) ($row->sort_order ?? 0),
                'status' => (int) ($row->status ?? 1),
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

    public function options()
    {
        $type = trim((string) $this->request->param('type', ''));
        if (!in_array($type, ['product', 'influencer'], true)) {
            return $this->jsonErr('无效 type');
        }
        $rows = CategoryModel::where('type', $type)
            ->where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'desc')
            ->select();
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
            ];
        }

        return $this->jsonOk(['items' => $items]);
    }

    public function save()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $name = trim((string) ($payload['name'] ?? ''));
        $type = trim((string) ($payload['type'] ?? ''));
        $sortOrder = (int) ($payload['sort_order'] ?? 0);
        $status = (int) ($payload['status'] ?? 1);
        $status = $status === 0 ? 0 : 1;
        if ($name === '') {
            return $this->jsonErr('请填写分类名称');
        }
        if (!in_array($type, ['product', 'influencer'], true)) {
            return $this->jsonErr('请选择分类类型');
        }
        $name = mb_substr($name, 0, 64);
        $existsQuery = CategoryModel::where('type', $type)->where('name', $name);
        if ($id > 0) {
            $existsQuery->where('id', '<>', $id);
        }
        if ($existsQuery->find()) {
            return $this->jsonErr('同类型分类名称已存在');
        }

        if ($id > 0) {
            $row = CategoryModel::find($id);
            if (!$row) {
                return $this->jsonErr('记录不存在');
            }
            $row->name = $name;
            $row->type = $type;
            $row->sort_order = $sortOrder;
            $row->status = $status;
            $row->save();
        } else {
            CategoryModel::create([
                'name' => $name,
                'type' => $type,
                'sort_order' => $sortOrder,
                'status' => $status,
            ]);
        }

        return $this->jsonOk([], '已保存');
    }

    public function delete()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonErr('无效 id');
        }
        CategoryModel::destroy($id);

        return $this->jsonOk([], '已删除');
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

