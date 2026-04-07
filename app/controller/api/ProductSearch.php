<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\model\ProductStyleItem as ItemModel;

/**
 * 图片搜款式（开放接口，供 H5/移动端）
 */
class ProductSearch extends BaseController
{
    private function jsonOut(array $payload, int $httpCode = 200)
    {
        return json($payload, $httpCode, [], ['json_encode_param' => JSON_UNESCAPED_UNICODE]);
    }

    /**
     * GET q= 编号模糊查（无向量）
     */
    public function searchByCode()
    {
        $q = trim((string) $this->request->param('q', ''));
        if ($q === '') {
            return $this->jsonOut(['code' => 1, 'msg' => '缺少 q', 'data' => null]);
        }
        $limit = (int) $this->request->param('limit', 10);
        if ($limit < 1) {
            $limit = 10;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $list = ItemModel::where('status', 1)
            ->whereLike('product_code', '%' . $q . '%')
            ->order('id', 'desc')
            ->limit($limit)
            ->select();

        $items = [];
        foreach ($list as $row) {
            $items[] = [
                'product_code' => (string) ($row->product_code ?? ''),
                'image_ref' => (string) ($row->image_ref ?? ''),
                'hot_type' => (string) ($row->hot_type ?? ''),
            ];
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => ['items' => $items],
        ]);
    }

    /**
     * GET catalog list for mobile showroom.
     */
    public function catalogList()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 30);
        if ($page < 1) {
            $page = 1;
        }
        if ($pageSize < 1) {
            $pageSize = 30;
        }
        if ($pageSize > 120) {
            $pageSize = 120;
        }

        $query = ItemModel::where('status', 1)->order('id', 'desc');
        if ($keyword !== '') {
            $query->whereLike('product_code', '%' . $keyword . '%');
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
        ]);

        $items = [];
        foreach ($list as $row) {
            $items[] = [
                'id' => (int) ($row->id ?? 0),
                'product_code' => trim((string) ($row->product_code ?? '')),
                'image_ref' => trim((string) ($row->image_ref ?? '')),
                'hot_type' => trim((string) ($row->hot_type ?? '')),
            ];
        }

        $total = (int) $list->total();
        $current = (int) $list->currentPage();
        $rows = (int) $list->listRows();
        $hasMore = ($current * $rows) < $total;

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'items' => $items,
                'total' => $total,
                'page' => $current,
                'page_size' => $rows,
                'has_more' => $hasMore,
            ],
        ]);
    }
}
