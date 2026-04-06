<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\AppLicense as AppLicenseModel;
use think\facade\View;

/**
 * 桌面端授权码（发卡）
 */
class ClientLicense extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null)
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }

    private function generateLicenseKey(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $segments = [];
        for ($s = 0; $s < 4; $s++) {
            $part = '';
            for ($i = 0; $i < 4; $i++) {
                $part .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $segments[] = $part;
        }

        return implode('-', $segments);
    }

    private function uniqueLicenseKey(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $key = $this->generateLicenseKey();
            if (!AppLicenseModel::where('license_key', $key)->find()) {
                return $key;
            }
        }
        return $this->generateLicenseKey() . '-' . bin2hex(random_bytes(2));
    }

    public function index()
    {
        return View::fetch('admin/client_license/index', []);
    }

    /**
     * 列表 JSON（供 Vue）
     */
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

        $query = AppLicenseModel::order('id', 'desc');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('license_key', '%' . $keyword . '%')
                    ->whereOr('machine_id', 'like', '%' . $keyword . '%');
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

        $items = [];
        foreach ($list as $row) {
            $exp = $row->expire_time;
            $items[] = [
                'id' => (int) $row->id,
                'license_key' => (string) ($row->license_key ?? ''),
                'machine_id' => $row->machine_id !== null && $row->machine_id !== '' ? (string) $row->machine_id : '',
                'status' => (int) ($row->status ?? 0),
                'expire_time' => $exp ? (string) $exp : '',
                'created_at' => (string) ($row->created_at ?? ''),
            ];
        }

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ]);
    }

    /**
     * 新增单条，或批量生成（POST：batch_count + valid_days）
     */
    public function add()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }

        $batchCount = (int) $this->request->post('batch_count', 0);
        if ($batchCount > 0) {
            if ($batchCount > 500) {
                return $this->jsonErr('单次最多生成 500 条');
            }
            $validDays = (int) $this->request->post('valid_days', 0);
            $expireTime = null;
            if ($validDays > 0) {
                $expireTime = date('Y-m-d H:i:s', strtotime('+' . $validDays . ' days'));
            }
            $keys = [];
            for ($n = 0; $n < $batchCount; $n++) {
                $key = $this->uniqueLicenseKey();
                AppLicenseModel::create([
                    'license_key' => $key,
                    'machine_id' => null,
                    'status' => 1,
                    'expire_time' => $expireTime,
                ]);
                $keys[] = $key;
            }

            return $this->jsonOk(['count' => $batchCount, 'keys' => $keys], '生成成功');
        }

        $licenseKey = trim((string) $this->request->post('license_key', ''));
        if ($licenseKey === '') {
            $licenseKey = $this->uniqueLicenseKey();
        } else {
            if (AppLicenseModel::where('license_key', $licenseKey)->find()) {
                return $this->jsonErr('授权码已存在');
            }
        }

        $expireRaw = trim((string) $this->request->post('expire_time', ''));
        $expireTime = $expireRaw === '' ? null : $expireRaw;

        AppLicenseModel::create([
            'license_key' => $licenseKey,
            'machine_id' => null,
            'status' => (int) $this->request->post('status', 1),
            'expire_time' => $expireTime,
        ]);

        return $this->jsonOk(['license_key' => $licenseKey], '添加成功');
    }

    public function update()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $id = (int) $this->request->param('id', 0);
        $row = AppLicenseModel::find($id);
        if (!$row) {
            return $this->jsonErr('记录不存在');
        }

        $expireRaw = $this->request->post('expire_time');
        if ($expireRaw !== null) {
            $expireRaw = trim((string) $expireRaw);
            $row->expire_time = $expireRaw === '' ? null : $expireRaw;
        }
        if ($this->request->post('status') !== null) {
            $row->status = (int) $this->request->post('status');
        }
        $row->save();

        return $this->jsonOk([], '已保存');
    }

    public function toggle()
    {
        $id = (int) $this->request->param('id', 0);
        $row = AppLicenseModel::find($id);
        if (!$row) {
            return $this->jsonErr('记录不存在');
        }
        $row->status = (int) $row->status === 1 ? 0 : 1;
        $row->save();

        return $this->jsonOk([], '已更新');
    }

    public function unbind()
    {
        $id = (int) $this->request->param('id', 0);
        $row = AppLicenseModel::find($id);
        if (!$row) {
            return $this->jsonErr('记录不存在');
        }
        $row->machine_id = null;
        $row->save();

        return $this->jsonOk([], '已解绑');
    }

    public function delete()
    {
        $id = $this->request->param('id');
        AppLicenseModel::destroy($id);

        return $this->jsonOk([], '删除成功');
    }
}
