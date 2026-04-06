<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\AppVersion as AppVersionModel;
use think\facade\App;
use think\facade\Log;
use think\facade\View;
use Throwable;

/**
 * 桌面端版本发布
 */
class ClientVersion extends BaseController
{
    private function jsonOk(array $data = [], string $msg = 'ok')
    {
        return json(['code' => 0, 'msg' => $msg, 'data' => $data]);
    }

    private function jsonErr(string $msg, int $code = 1, $data = null)
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data]);
    }

    public function index()
    {
        return View::fetch('admin/client_version/index', []);
    }

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

        $query = AppVersionModel::order('id', 'desc');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('version', '%' . $keyword . '%')
                    ->whereOr('release_notes', 'like', '%' . $keyword . '%');
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
            $items[] = [
                'id' => (int) $row->id,
                'version' => (string) ($row->version ?? ''),
                'release_notes' => (string) ($row->release_notes ?? ''),
                'download_url' => (string) ($row->download_url ?? ''),
                'is_mandatory' => (int) ($row->is_mandatory ?? 0),
                'status' => (int) ($row->status ?? 0),
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

    public function add()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $version = trim((string) $this->request->post('version', ''));
        if ($version === '') {
            return $this->jsonErr('请填写版本号');
        }
        if (AppVersionModel::where('version', $version)->find()) {
            return $this->jsonErr('版本号已存在');
        }
        $downloadUrl = trim((string) $this->request->post('download_url', ''));
        if ($downloadUrl === '') {
            return $this->jsonErr('请填写下载地址或上传安装包');
        }

        try {
            AppVersionModel::create([
                'version' => $version,
                'release_notes' => trim((string) $this->request->post('release_notes', '')),
                'download_url' => $downloadUrl,
                'is_mandatory' => (int) $this->request->post('is_mandatory', 0) ? 1 : 0,
                'status' => (int) $this->request->post('status', 1) ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            Log::error('ClientVersion@add: ' . $e->getMessage());

            return $this->jsonErr(
                App::isDebug() ? ('保存失败：' . $e->getMessage()) : '保存失败，请确认已执行数据库迁移（app_versions 表）或联系管理员'
            );
        }

        return $this->jsonOk([], '发布成功');
    }

    public function update()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $id = (int) $this->request->param('id', 0);
        $row = AppVersionModel::find($id);
        if (!$row) {
            return $this->jsonErr('记录不存在');
        }

        $version = trim((string) $this->request->post('version', ''));
        if ($version === '') {
            return $this->jsonErr('请填写版本号');
        }
        $dup = AppVersionModel::where('version', $version)->where('id', '<>', $id)->find();
        if ($dup) {
            return $this->jsonErr('版本号与其他记录冲突');
        }

        $downloadUrl = trim((string) $this->request->post('download_url', ''));
        if ($downloadUrl === '') {
            return $this->jsonErr('请填写下载地址');
        }

        $row->version = $version;
        $row->release_notes = trim((string) $this->request->post('release_notes', ''));
        $row->download_url = $downloadUrl;
        $row->is_mandatory = (int) $this->request->post('is_mandatory', 0) ? 1 : 0;
        $row->status = (int) $this->request->post('status', 1) ? 1 : 0;
        try {
            $row->save();
        } catch (Throwable $e) {
            Log::error('ClientVersion@update: ' . $e->getMessage());

            return $this->jsonErr(
                App::isDebug() ? ('保存失败：' . $e->getMessage()) : '保存失败，请检查数据库或联系管理员'
            );
        }

        return $this->jsonOk([], '已保存');
    }

    public function toggle()
    {
        $id = (int) $this->request->param('id', 0);
        $row = AppVersionModel::find($id);
        if (!$row) {
            return $this->jsonErr('记录不存在');
        }
        $row->status = (int) $row->status === 1 ? 0 : 1;
        $row->save();

        return $this->jsonOk([], '已更新');
    }

    public function delete()
    {
        $id = $this->request->param('id');
        AppVersionModel::destroy($id);

        return $this->jsonOk([], '删除成功');
    }

    /**
     * 上传安装包到 public/uploads/client_releases/，返回可访问 URL
     */
    public function uploadPackage()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonErr('请选择文件');
        }
        $ext = strtolower((string) $file->extension());
        $allow = ['zip', '7z', 'rar', 'exe', 'msi', 'dmg', 'pkg', 'apk'];
        if (!in_array($ext, $allow, true)) {
            return $this->jsonErr('不支持的文件类型：' . $ext);
        }
        $dateStr = date('Ymd');
        $baseDir = root_path() . 'public' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'client_releases' . DIRECTORY_SEPARATOR . $dateStr . DIRECTORY_SEPARATOR;
        if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true) && !is_dir($baseDir)) {
            return $this->jsonErr('创建目录失败');
        }
        $saveName = date('His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        try {
            $file->move($baseDir, $saveName);
        } catch (Throwable $e) {
            Log::error('ClientVersion@uploadPackage: ' . $e->getMessage());

            return $this->jsonErr(
                App::isDebug()
                    ? ('上传失败：' . $e->getMessage())
                    : '上传失败，请检查 public/uploads 目录权限、PHP 上传大小限制'
            );
        }
        $url = '/uploads/client_releases/' . $dateStr . '/' . $saveName;

        return $this->jsonOk(['url' => $url], '上传成功');
    }
}
