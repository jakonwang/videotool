<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;
use think\facade\View;

class OfflineOrder extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return $this->apiJsonOk($data, $msg);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null, string $errorKey = '')
    {
        return $this->apiJsonErr($msg, $code, $data, $errorKey);
    }

    public function index()
    {
        return View::fetch('admin/offline_order/index');
    }

    public function listJson()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $statusRaw = trim((string) $this->request->param('status', ''));
        $page = max(1, (int) $this->request->param('page', 1));
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize < 1) {
            $pageSize = 20;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        $q = Db::name('offline_orders')->order('id', 'desc');
        $q = $this->scopeTenant($q, 'offline_orders');
        if ($keyword !== '') {
            $q->where(static function ($where) use ($keyword): void {
                $where->whereLike('order_no', '%' . $keyword . '%')
                    ->whereOr('customer_info', 'like', '%' . $keyword . '%')
                    ->whereOr('items_json', 'like', '%' . $keyword . '%');
            });
        }
        if ($statusRaw !== '' && in_array((int) $statusRaw, [0, 1, 2], true)) {
            $q->where('status', (int) $statusRaw);
        }

        $list = $q->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $customer = $this->decodeJsonObject((string) ($row['customer_info'] ?? ''));
            $orderItems = $this->decodeJsonArray((string) ($row['items_json'] ?? ''));
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'order_no' => (string) ($row['order_no'] ?? ''),
                'customer_name' => (string) ($customer['name'] ?? ''),
                'customer_phone' => (string) ($customer['phone'] ?? ''),
                'customer_whatsapp' => (string) ($customer['whatsapp'] ?? ''),
                'customer_zalo' => (string) ($customer['zalo'] ?? ''),
                'items' => $orderItems,
                'item_count' => count($orderItems),
                'total_amount' => round((float) ($row['total_amount'] ?? 0), 2),
                'status' => (int) ($row['status'] ?? 0),
                'status_text' => $this->statusText((int) ($row['status'] ?? 0)),
                'remark' => (string) ($row['remark'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ]);
    }

    public function updateStatus()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post');
        }
        $id = (int) $this->request->param('id', 0);
        $status = (int) $this->request->param('status', -1);
        $remark = trim((string) $this->request->param('remark', ''));
        if ($id <= 0) {
            return $this->jsonErr('invalid_id');
        }
        if (!in_array($status, [0, 1, 2], true)) {
            return $this->jsonErr('invalid_status');
        }

        $existsQuery = Db::name('offline_orders')->where('id', $id);
        $existsQuery = $this->scopeTenant($existsQuery, 'offline_orders');
        $exists = $existsQuery->find();
        if (!$exists) {
            return $this->jsonErr('not_found');
        }

        $updateQuery = Db::name('offline_orders')->where('id', $id);
        $updateQuery = $this->scopeTenant($updateQuery, 'offline_orders');
        $updateQuery->update([
            'status' => $status,
            'remark' => $remark,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->jsonOk([], 'ok');
    }

    public function exportXlsx()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $statusRaw = trim((string) $this->request->param('status', ''));

        $q = Db::name('offline_orders')->order('id', 'desc');
        $q = $this->scopeTenant($q, 'offline_orders');
        if ($keyword !== '') {
            $q->where(static function ($where) use ($keyword): void {
                $where->whereLike('order_no', '%' . $keyword . '%')
                    ->whereOr('customer_info', 'like', '%' . $keyword . '%')
                    ->whereOr('items_json', 'like', '%' . $keyword . '%');
            });
        }
        if ($statusRaw !== '' && in_array((int) $statusRaw, [0, 1, 2], true)) {
            $q->where('status', (int) $statusRaw);
        }
        $rows = $q->limit(5000)->select()->toArray();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = [
            'Order No',
            'Customer Name',
            'Phone',
            'WhatsApp',
            'Zalo',
            'Order Status',
            'Style ID',
            'Style Code',
            'Style Thumbnail URL',
            'Unit Price',
            'Quantity',
            'Subtotal',
            'Order Total',
            'Created At',
        ];
        $sheet->fromArray($headers, null, 'A1');

        $line = 2;
        foreach ($rows as $row) {
            $status = (int) ($row['status'] ?? 0);
            $customer = $this->decodeJsonObject((string) ($row['customer_info'] ?? ''));
            $orderItems = $this->decodeJsonArray((string) ($row['items_json'] ?? ''));
            if ($orderItems === []) {
                $orderItems[] = [];
            }
            foreach ($orderItems as $it) {
                $sheet->fromArray([
                    (string) ($row['order_no'] ?? ''),
                    (string) ($customer['name'] ?? ''),
                    (string) ($customer['phone'] ?? ''),
                    (string) ($customer['whatsapp'] ?? ''),
                    (string) ($customer['zalo'] ?? ''),
                    $this->statusText($status),
                    (int) ($it['style_id'] ?? 0),
                    (string) ($it['product_code'] ?? ''),
                    (string) ($it['image_ref'] ?? ''),
                    round((float) ($it['unit_price'] ?? 0), 2),
                    (int) ($it['qty'] ?? 0),
                    round((float) ($it['subtotal'] ?? 0), 2),
                    round((float) ($row['total_amount'] ?? 0), 2),
                    (string) ($row['created_at'] ?? ''),
                ], null, 'A' . $line);
                ++$line;
            }
        }

        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'offline_orders_');
        if ($tmpFile === false) {
            return $this->jsonErr('export_failed');
        }
        $xlsxPath = $tmpFile . '.xlsx';
        @rename($tmpFile, $xlsxPath);
        $writer = new Xlsx($spreadsheet);
        $writer->save($xlsxPath);
        $binary = (string) @file_get_contents($xlsxPath);
        @unlink($xlsxPath);

        $filename = 'offline_orders_' . date('Ymd_His') . '.xlsx';
        return response($binary, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $json): array
    {
        if ($json === '') {
            return [];
        }
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeJsonArray(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return [];
        }
        $out = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }
        return $out;
    }

    private function statusText(int $status): string
    {
        if ($status === 1) {
            return 'Confirmed';
        }
        if ($status === 2) {
            return 'Cancelled';
        }
        return 'Pending';
    }
}
