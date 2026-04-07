<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\AdminUser as AdminUserModel;
use app\service\AdminAuthService;
use think\facade\View;

/**
 * 后台用户（管理员账号）
 */
class User extends BaseController
{
    private function normalizeRole(string $role): string
    {
        $r = trim($role);
        if (!in_array($r, ['super_admin', 'operator', 'viewer'], true)) {
            return 'operator';
        }
        return $r;
    }

    public function index()
    {
        return View::fetch('admin/user/index');
    }

    public function listJson()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = $this->request->param('status', '');

        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) $pageSize = 10;
        if ($pageSize > 100) $pageSize = 100;

        $query = AdminUserModel::order('id', 'desc');
        if ($keyword !== '') {
            $query->whereLike('username', '%' . $keyword . '%');
        }
        if ($status !== '' && $status !== null) {
            $query->where('status', (int) $status);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => max(1, $page),
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $u) {
            $items[] = [
                'id' => (int) $u->id,
                'username' => (string) ($u->username ?? ''),
                'status' => (int) ($u->status ?? 0),
                'role' => (string) ($u->role ?? 'super_admin'),
                'last_login_at' => (string) ($u->last_login_at ?? ''),
                'last_login_ip' => (string) ($u->last_login_ip ?? ''),
                'created_at' => (string) ($u->created_at ?? ''),
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
                'me' => [
                    'id' => AdminAuthService::userId(),
                    'username' => AdminAuthService::username(),
                    'role' => AdminAuthService::role(),
                ],
            ],
        ]);
    }

    public function create()
    {
        $username = trim((string) $this->request->post('username', ''));
        $password = (string) $this->request->post('password', '');
        $status = (int) $this->request->post('status', 1);
        $role = $this->normalizeRole((string) $this->request->post('role', 'operator'));
        $meRole = AdminAuthService::role();
        if ($meRole !== 'super_admin' && $role === 'super_admin') {
            return json(['code' => 1, 'msg' => '仅超级管理员可创建超级管理员']);
        }

        if ($username === '') {
            return json(['code' => 1, 'msg' => '请输入用户名']);
        }
        if (strlen($username) > 64) {
            return json(['code' => 1, 'msg' => '用户名过长']);
        }
        if ($password === '' || strlen($password) < 6) {
            return json(['code' => 1, 'msg' => '密码至少 6 位']);
        }

        if (AdminUserModel::where('username', $username)->find()) {
            return json(['code' => 1, 'msg' => '用户名已存在']);
        }

        AdminUserModel::create([
            'username' => $username,
            'role' => $role,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'status' => $status === 1 ? 1 : 0,
        ]);

        return json(['code' => 0, 'msg' => '已创建']);
    }

    public function update()
    {
        $id = (int) $this->request->post('id', 0);
        $username = trim((string) $this->request->post('username', ''));
        $status = (int) $this->request->post('status', 1);
        $role = $this->normalizeRole((string) $this->request->post('role', 'operator'));
        $meRole = AdminAuthService::role();
        if ($meRole !== 'super_admin' && $role === 'super_admin') {
            return json(['code' => 1, 'msg' => '仅超级管理员可设置超级管理员角色']);
        }

        if ($id <= 0) {
            return json(['code' => 1, 'msg' => '参数错误']);
        }
        /** @var AdminUserModel|null $row */
        $row = AdminUserModel::find($id);
        if (!$row) {
            return json(['code' => 1, 'msg' => '用户不存在']);
        }

        if ($username === '') {
            return json(['code' => 1, 'msg' => '请输入用户名']);
        }
        if (strlen($username) > 64) {
            return json(['code' => 1, 'msg' => '用户名过长']);
        }
        $exists = AdminUserModel::where('username', $username)->where('id', '<>', $id)->find();
        if ($exists) {
            return json(['code' => 1, 'msg' => '用户名已存在']);
        }

        $meId = AdminAuthService::userId();
        if ($meId === $id && $status !== 1) {
            return json(['code' => 1, 'msg' => '不能禁用当前登录账号']);
        }

        $row->username = $username;
        $row->status = $status === 1 ? 1 : 0;
        $row->role = $role;
        $row->save();

        return json(['code' => 0, 'msg' => '已保存']);
    }

    public function toggle()
    {
        $id = (int) $this->request->post('id', 0);
        if ($id <= 0) return json(['code' => 1, 'msg' => '参数错误']);

        /** @var AdminUserModel|null $row */
        $row = AdminUserModel::find($id);
        if (!$row) return json(['code' => 1, 'msg' => '用户不存在']);

        $meId = AdminAuthService::userId();
        if ($meId === $id) {
            return json(['code' => 1, 'msg' => '不能禁用当前登录账号']);
        }

        $row->status = (int) ($row->status ?? 0) === 1 ? 0 : 1;
        $row->save();
        return json(['code' => 0, 'msg' => '已更新']);
    }

    public function resetPassword()
    {
        $id = (int) $this->request->post('id', 0);
        $password = (string) $this->request->post('password', '');
        if ($id <= 0) return json(['code' => 1, 'msg' => '参数错误']);
        if ($password === '' || strlen($password) < 6) {
            return json(['code' => 1, 'msg' => '密码至少 6 位']);
        }

        /** @var AdminUserModel|null $row */
        $row = AdminUserModel::find($id);
        if (!$row) return json(['code' => 1, 'msg' => '用户不存在']);

        $row->password_hash = password_hash($password, PASSWORD_BCRYPT);
        $row->save();
        return json(['code' => 0, 'msg' => '密码已重置']);
    }

    public function delete()
    {
        $id = (int) $this->request->post('id', 0);
        if ($id <= 0) return json(['code' => 1, 'msg' => '参数错误']);

        $meId = AdminAuthService::userId();
        if ($meId === $id) {
            return json(['code' => 1, 'msg' => '不能删除当前登录账号']);
        }

        AdminUserModel::destroy($id);
        return json(['code' => 0, 'msg' => '已删除']);
    }
}

