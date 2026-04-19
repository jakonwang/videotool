<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Device as DeviceModel;
use app\model\Platform as PlatformModel;
use think\facade\View;

class Device extends BaseController
{
    private function platformBelongsTenant(int $platformId): bool
    {
        if ($platformId <= 0) {
            return false;
        }
        $query = $this->scopeTenant(PlatformModel::where('id', $platformId), 'platforms');
        return (bool) $query->find();
    }

    public function index()
    {
        $platformId = (int) $this->request->param('platform_id', 0);
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = $this->request->param('status', '');

        $query = $this->scopeTenant(DeviceModel::with('platform')->order('id', 'desc'), 'devices');
        if ($platformId > 0) {
            $query->where('platform_id', $platformId);
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('device_name', '%' . $keyword . '%')
                    ->whereOrLike('ip_address', '%' . $keyword . '%');
            });
        }

        $list = $query->paginate([
            'list_rows' => 10,
            'query' => $this->request->param(),
        ]);
        $platforms = $this->scopeTenant(PlatformModel::order('id', 'desc'), 'platforms')->select();

        return View::fetch('admin/device/index', [
            'list' => $list,
            'platforms' => $platforms,
            'platform_id' => $platformId,
            'keyword' => $keyword,
            'status' => $status,
        ]);
    }

    public function listJson()
    {
        $platformId = (int) $this->request->param('platform_id', 0);
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

        $sortProp = (string) $this->request->param('sort_prop', 'id');
        $sortOrder = strtolower((string) $this->request->param('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $allowedSort = ['id', 'created_at', 'updated_at', 'device_name', 'ip_address', 'status'];
        if (!in_array($sortProp, $allowedSort, true)) {
            $sortProp = 'id';
        }

        $query = $this->scopeTenant(DeviceModel::with('platform')->order($sortProp, $sortOrder), 'devices');
        if ($platformId > 0) {
            $query->where('platform_id', $platformId);
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('device_name', '%' . $keyword . '%')
                    ->whereOrLike('ip_address', '%' . $keyword . '%');
            });
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $device) {
            $items[] = [
                'id' => (int) $device->id,
                'device_name' => (string) ($device->device_name ?? ''),
                'ip_address' => (string) ($device->ip_address ?? ''),
                'status' => (int) ($device->status ?? 0),
                'created_at' => (string) ($device->created_at ?? ''),
                'updated_at' => (string) ($device->updated_at ?? ''),
                'platform' => $device->platform ? [
                    'id' => (int) ($device->platform_id ?? 0),
                    'name' => (string) ($device->platform->name ?? ''),
                    'code' => (string) ($device->platform->code ?? ''),
                ] : null,
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
            $platformId = (int) ($data['platform_id'] ?? 0);
            if ($platformId > 0 && !$this->platformBelongsTenant($platformId)) {
                return json(['code' => 1, 'msg' => 'platform_not_found']);
            }
            $saveData = $this->withTenantPayload($data, 'devices');
            DeviceModel::create($saveData);
            return json(['code' => 0, 'msg' => 'saved']);
        }

        $platforms = $this->scopeTenant(PlatformModel::order('id', 'desc'), 'platforms')->select();
        return View::fetch('admin/device/form', ['info' => null, 'platforms' => $platforms]);
    }

    public function edit()
    {
        $id = (int) $this->request->param('id', 0);
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $platformId = (int) ($data['platform_id'] ?? 0);
            if ($platformId > 0 && !$this->platformBelongsTenant($platformId)) {
                return json(['code' => 1, 'msg' => 'platform_not_found']);
            }
            $saveData = $this->withTenantPayload($data, 'devices');
            $query = $this->scopeTenant(DeviceModel::where('id', $id), 'devices');
            $query->update($saveData);
            return json(['code' => 0, 'msg' => 'updated']);
        }

        $info = $this->scopeTenant(DeviceModel::where('id', $id), 'devices')->find();
        $platforms = $this->scopeTenant(PlatformModel::order('id', 'desc'), 'platforms')->select();
        return View::fetch('admin/device/form', ['info' => $info, 'platforms' => $platforms]);
    }

    public function delete()
    {
        $id = (int) $this->request->param('id', 0);
        $query = $this->scopeTenant(DeviceModel::where('id', $id), 'devices');
        $query->delete();

        return json(['code' => 0, 'msg' => 'deleted']);
    }

    public function getByPlatform()
    {
        $platformId = (int) $this->request->param('platform_id', 0);
        if ($platformId <= 0 || !$this->platformBelongsTenant($platformId)) {
            return json(['code' => 0, 'data' => []]);
        }

        $query = $this->scopeTenant(DeviceModel::where('platform_id', $platformId), 'devices');
        $devices = $query->select();
        return json(['code' => 0, 'data' => $devices]);
    }
}
