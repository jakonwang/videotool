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
}
