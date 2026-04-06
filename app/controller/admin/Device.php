<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Device as DeviceModel;
use app\model\Platform as PlatformModel;
use think\facade\View;
use think\facade\Db;

/**
 * 设备管理
 */
class Device extends BaseController
{
    public function index()
    {
        $platformId = $this->request->param('platform_id', 0);
        $keyword = trim((string)$this->request->param('keyword', ''));
        $status = $this->request->param('status', '');
        
        $query = DeviceModel::with('platform')->order('id', 'desc');
        
        if ($platformId) {
            $query->where('platform_id', '=', $platformId);
        }
        
        if ($status !== '' && $status !== null) {
            $query->where('status', (int)$status);
        }
        
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('device_name', '%' . $keyword . '%')
                    ->whereOr('ip_address', 'like', '%' . $keyword . '%');
            });
        }
        
        $list = $query->paginate([
            'list_rows' => 10,
            'query' => $this->request->param()
        ]);
            
        $platforms = PlatformModel::select();
        
        return View::fetch('admin/device/index', [
            'list' => $list,
            'platforms' => $platforms,
            'platform_id' => $platformId,
            'keyword' => $keyword,
            'status' => $status
        ]);
    }

    /**
     * 设备列表（JSON）
     * 供 Vue/ElementPlus 页面使用，复用 index() 的筛选规则
     */
    public function listJson()
    {
        $platformId = (int) $this->request->param('platform_id', 0);
        $keyword = trim((string)$this->request->param('keyword', ''));
        $status = $this->request->param('status', '');

        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) $pageSize = 10;
        if ($pageSize > 100) $pageSize = 100;

        $sortProp = (string) $this->request->param('sort_prop', 'id');
        $sortOrder = (string) $this->request->param('sort_order', 'desc'); // asc|desc
        $sortOrder = strtolower($sortOrder) === 'asc' ? 'asc' : 'desc';

        $allowedSort = ['id', 'created_at', 'updated_at', 'device_name', 'ip_address', 'status'];
        if (!in_array($sortProp, $allowedSort, true)) {
            $sortProp = 'id';
        }

        $query = DeviceModel::with('platform')->order($sortProp, $sortOrder);

        if ($platformId > 0) {
            $query->where('platform_id', '=', $platformId);
        }

        if ($status !== '' && $status !== null) {
            $query->where('status', (int)$status);
        }

        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('device_name', '%' . $keyword . '%')
                    ->whereOr('ip_address', 'like', '%' . $keyword . '%');
            });
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param()
        ]);

        $items = [];
        foreach ($list as $d) {
            $items[] = [
                'id' => (int) $d->id,
                'device_name' => (string) ($d->device_name ?? ''),
                'ip_address' => (string) ($d->ip_address ?? ''),
                'status' => (int) ($d->status ?? 0),
                'created_at' => (string) ($d->created_at ?? ''),
                'updated_at' => (string) ($d->updated_at ?? ''),
                'platform' => $d->platform ? [
                    'id' => (int) ($d->platform_id ?? 0),
                    'name' => (string) ($d->platform->name ?? ''),
                    'code' => (string) ($d->platform->code ?? ''),
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
            DeviceModel::create($data);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        $platforms = PlatformModel::select();
        return View::fetch('admin/device/form', ['info' => null, 'platforms' => $platforms]);
    }
    
    public function edit()
    {
        $id = $this->request->param('id');
        if ($this->request->isPost()) {
            $data = $this->request->post();
            DeviceModel::where('id', $id)->update($data);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
        $info = DeviceModel::find($id);
        $platforms = PlatformModel::select();
        return View::fetch('admin/device/form', ['info' => $info, 'platforms' => $platforms]);
    }
    
    public function delete()
    {
        $id = $this->request->param('id');
        DeviceModel::destroy($id);
        return json(['code' => 0, 'msg' => '删除成功']);
    }
    
    public function getByPlatform()
    {
        $platformId = $this->request->param('platform_id');
        $devices = DeviceModel::where('platform_id', $platformId)->select();
        return json(['code' => 0, 'data' => $devices]);
    }
}

