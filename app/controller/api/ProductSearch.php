<?php
declare(strict_types=1);

namespace app\controller\api;

use app\BaseController;
use app\model\ProductStyleItem as ItemModel;
use think\facade\Db;

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
        $category = trim((string) $this->request->param('category', ''));
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
            $query->where(static function ($q) use ($keyword): void {
                $q->whereLike('product_code', '%' . $keyword . '%')
                    ->whereOr('hot_type', 'like', '%' . $keyword . '%');
            });
        }
        if ($category !== '') {
            $query->where('hot_type', $category);
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
                'category' => trim((string) ($row->hot_type ?? '')),
                'wholesale_price' => (float) ($row->wholesale_price ?? 0),
                'min_order_qty' => max(1, (int) ($row->min_order_qty ?? 1)),
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

    /**
     * GET category options for customer H5.
     */
    public function categoryOptions()
    {
        $options = [];
        $rows = ItemModel::where('status', 1)
            ->whereRaw("TRIM(IFNULL(hot_type,'')) <> ''")
            ->group('hot_type')
            ->order('hot_type', 'asc')
            ->column('hot_type');
        foreach ($rows as $v) {
            $name = trim((string) $v);
            if ($name === '') {
                continue;
            }
            $options[] = [
                'value' => $name,
                'label' => $name,
            ];
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => ['items' => $options],
        ]);
    }

    /**
     * POST create offline reservation order.
     */
    public function createOfflineOrder()
    {
        if (!$this->request->isPost()) {
            return $this->jsonOut(['code' => 1, 'msg' => 'only_post', 'data' => null]);
        }
        $raw = (string) $this->request->getContent();
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return $this->jsonOut(['code' => 1, 'msg' => 'invalid_payload', 'data' => null]);
        }

        $customer = isset($payload['customer']) && is_array($payload['customer']) ? $payload['customer'] : [];
        $name = trim((string) ($customer['name'] ?? ''));
        $phone = trim((string) ($customer['phone'] ?? ''));
        $whatsapp = trim((string) ($customer['whatsapp'] ?? ''));
        $zalo = trim((string) ($customer['zalo'] ?? ''));
        if ($name === '') {
            return $this->jsonOut(['code' => 1, 'msg' => 'customer_name_required', 'data' => null]);
        }
        if ($phone === '' && $whatsapp === '' && $zalo === '') {
            return $this->jsonOut(['code' => 1, 'msg' => 'customer_contact_required', 'data' => null]);
        }

        $inputItems = isset($payload['items']) && is_array($payload['items']) ? $payload['items'] : [];
        if ($inputItems === []) {
            return $this->jsonOut(['code' => 1, 'msg' => 'items_required', 'data' => null]);
        }

        $qtyMap = [];
        foreach ($inputItems as $it) {
            if (!is_array($it)) {
                continue;
            }
            $styleId = (int) ($it['style_id'] ?? 0);
            $qty = (int) ($it['qty'] ?? 0);
            if ($styleId <= 0 || $qty <= 0) {
                continue;
            }
            $qtyMap[$styleId] = ($qtyMap[$styleId] ?? 0) + $qty;
        }
        if ($qtyMap === []) {
            return $this->jsonOut(['code' => 1, 'msg' => 'items_required', 'data' => null]);
        }

        $styleRows = ItemModel::whereIn('id', array_keys($qtyMap))
            ->where('status', 1)
            ->select();
        if ($styleRows->isEmpty()) {
            return $this->jsonOut(['code' => 1, 'msg' => 'style_not_found', 'data' => null]);
        }

        $items = [];
        $totalAmount = 0.0;
        foreach ($styleRows as $row) {
            $styleId = (int) ($row->id ?? 0);
            $qty = (int) ($qtyMap[$styleId] ?? 0);
            if ($styleId <= 0 || $qty <= 0) {
                continue;
            }
            $minQty = max(1, (int) ($row->min_order_qty ?? 1));
            if ($qty < $minQty) {
                $qty = $minQty;
            }
            $unitPrice = round((float) ($row->wholesale_price ?? 0), 2);
            $subtotal = round($unitPrice * $qty, 2);
            $totalAmount += $subtotal;
            $items[] = [
                'style_id' => $styleId,
                'product_code' => trim((string) ($row->product_code ?? '')),
                'image_ref' => trim((string) ($row->image_ref ?? '')),
                'hot_type' => trim((string) ($row->hot_type ?? '')),
                'unit_price' => $unitPrice,
                'min_order_qty' => $minQty,
                'qty' => $qty,
                'subtotal' => $subtotal,
            ];
        }
        if ($items === []) {
            return $this->jsonOut(['code' => 1, 'msg' => 'style_not_found', 'data' => null]);
        }

        $orderNo = $this->buildOrderNo();
        $customerInfo = [
            'name' => $name,
            'phone' => $phone,
            'whatsapp' => $whatsapp,
            'zalo' => $zalo,
        ];

        try {
            Db::name('offline_orders')->insert([
                'order_no' => $orderNo,
                'customer_info' => json_encode($customerInfo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'items_json' => json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'total_amount' => round($totalAmount, 2),
                'status' => 0,
                'remark' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return $this->jsonOut([
                'code' => 1,
                'msg' => 'save_failed',
                'data' => ['error' => $e->getMessage()],
            ]);
        }

        return $this->jsonOut([
            'code' => 0,
            'msg' => 'ok',
            'data' => [
                'order_no' => $orderNo,
                'item_count' => count($items),
                'total_amount' => round($totalAmount, 2),
            ],
        ]);
    }

    private function buildOrderNo(): string
    {
        $prefix = 'OFF' . date('YmdHis');
        try {
            $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } catch (\Throwable $e) {
            $suffix = strtoupper(substr(md5((string) mt_rand(100000, 999999)), 0, 6));
        }
        return $prefix . $suffix;
    }
}
