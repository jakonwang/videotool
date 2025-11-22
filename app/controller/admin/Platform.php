<?php
declare (strict_types = 1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Platform as PlatformModel;
use think\facade\View;

/**
 * 平台管理
 */
class Platform extends BaseController
{
    public function index()
    {
        $keyword = trim((string)$this->request->param('keyword', ''));
        $status = $this->request->param('status', '');
        
        $query = PlatformModel::order('id', 'desc');
        
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('name', '%' . $keyword . '%')
                    ->whereOr('code', 'like', '%' . $keyword . '%');
            });
        }
        
        if ($status !== '' && $status !== null) {
            $query->where('status', (int)$status);
        }
        
        $list = $query->paginate([
            'list_rows' => 10,
            'query' => $this->request->param()
        ]);
        
        // 生成前台访问的基础URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // 构建基础URL
        $baseUrl = $protocol . '://' . $host;
        
        // 如果 admin.php 在子目录中，需要找到根目录
        if (preg_match('#^(.*?)/admin\.php#', $scriptName, $matches)) {
            // admin.php 在子目录中，添加子目录路径
            $baseUrl .= $matches[1];
        }
        
        return View::fetch('admin/platform/index', [
            'list' => $list,
            'base_url' => $baseUrl,
            'keyword' => $keyword,
            'status' => $status
        ]);
    }
    
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();
            PlatformModel::create($data);
            return json(['code' => 0, 'msg' => '添加成功']);
        }
        return View::fetch('admin/platform/form', ['info' => null]);
    }
    
    public function edit()
    {
        $id = $this->request->param('id');
        if ($this->request->isPost()) {
            $data = $this->request->post();
            PlatformModel::where('id', $id)->update($data);
            return json(['code' => 0, 'msg' => '修改成功']);
        }
        $info = PlatformModel::find($id);
        return View::fetch('admin/platform/form', ['info' => $info]);
    }
    
    public function delete()
    {
        $id = $this->request->param('id');
        PlatformModel::destroy($id);
        return json(['code' => 0, 'msg' => '删除成功']);
    }
}

