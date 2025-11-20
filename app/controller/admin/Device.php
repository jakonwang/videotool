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
        $where = [];
        if ($platformId) {
            $where[] = ['platform_id', '=', $platformId];
        }
        
        $list = DeviceModel::where($where)
            ->with('platform')
            ->order('id', 'desc')
            ->paginate([
                'list_rows' => 20,
                'query' => $this->request->param()
            ]);
            
        $platforms = PlatformModel::select();
        
        return View::fetch('admin/device/index', [
            'list' => $list,
            'platforms' => $platforms,
            'platform_id' => $platformId
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

