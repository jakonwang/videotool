<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Influencer as InfluencerModel;
use app\service\InfluencerImportTaskRunner;
use app\service\InfluencerService;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;

/**
 * 达人名录（tiktok_id = TikTok @handle）
 */
class Influencer extends BaseController
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
        return View::fetch('admin/influencer/index', []);
    }

    /**
     * 达人下拉（达人链关联）
     */
    public function searchJson()
    {
        $q = trim((string) $this->request->param('q', ''));
        $items = InfluencerService::searchOptions($q, 30);

        return $this->jsonOk(['items' => $items]);
    }

    public function listJson()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = $this->request->param('status', null);
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) {
            $pageSize = 10;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        $query = InfluencerModel::order('id', 'desc');
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('tiktok_id', '%' . $keyword . '%')
                    ->whereOr('nickname', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $contactRaw = (string) ($row->contact_info ?? '');
            $contactDisplay = $contactRaw;
            if ($contactRaw !== '' && ($contactRaw[0] === '{' || $contactRaw[0] === '[')) {
                $j = json_decode($contactRaw, true);
                if (is_array($j)) {
                    $contactDisplay = isset($j['text']) ? (string) $j['text'] : $contactRaw;
                }
            }
            $items[] = [
                'id' => (int) $row->id,
                'tiktok_id' => (string) ($row->tiktok_id ?? ''),
                'nickname' => (string) ($row->nickname ?? ''),
                'avatar_url' => (string) ($row->avatar_url ?? ''),
                'follower_count' => (int) ($row->follower_count ?? 0),
                'contact_info' => $contactRaw,
                'contact_display' => $contactDisplay,
                'region' => (string) ($row->region ?? ''),
                'status' => (int) ($row->status ?? 1),
                'created_at' => (string) ($row->created_at ?? ''),
                'updated_at' => (string) ($row->updated_at ?? ''),
            ];
        }

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ]);
    }

    public function importCsv()
    {
        try {
            return $this->importCsvInner();
        } catch (\Throwable $e) {
            Log::error('influencer importCsv: ' . $e->getMessage() . "\n" . $e->getTraceAsString());

            return $this->jsonErr('导入失败：' . $e->getMessage());
        }
    }

    public function importTaskStatus()
    {
        try {
            $id = (int) $this->request->param('task_id', 0);
            if ($id <= 0) {
                return $this->jsonErr('无效 task_id');
            }
            InfluencerImportTaskRunner::bumpMemoryAndTime();
            $snap = InfluencerImportTaskRunner::snapshot($id);
            if ($snap === null) {
                return $this->jsonErr('任务不存在');
            }

            return $this->jsonOk($snap);
        } catch (\Throwable $e) {
            Log::error('influencer importTaskStatus: ' . $e->getMessage());

            return $this->jsonErr('查询失败：' . $e->getMessage());
        }
    }

    public function importTaskTick()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->jsonErr('仅支持 POST');
            }
            $id = 0;
            $raw = (string) $this->request->getContent();
            if ($raw !== '') {
                $j = json_decode($raw, true);
                if (is_array($j) && isset($j['task_id'])) {
                    $id = (int) $j['task_id'];
                }
            }
            if ($id <= 0) {
                $id = (int) $this->request->post('task_id', 0);
            }
            if ($id <= 0) {
                $id = (int) $this->request->param('task_id', 0);
            }
            if ($id <= 0) {
                return $this->jsonErr('无效 task_id');
            }
            $r = InfluencerImportTaskRunner::tick($id);
            if (isset($r['_error'])) {
                return $this->jsonErr((string) $r['_error']);
            }

            return $this->jsonOk($r);
        } catch (\Throwable $e) {
            Log::error('influencer importTaskTick: ' . $e->getMessage());

            return $this->jsonErr('处理失败：' . $e->getMessage());
        }
    }

    private function importCsvInner()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonErr('请上传文件');
        }
        $ext = strtolower((string) $file->extension());
        if ($ext === '') {
            $ext = strtolower(pathinfo((string) $file->getOriginalName(), PATHINFO_EXTENSION));
        }
        $tmp = $file->getPathname();
        if (!is_readable($tmp)) {
            return $this->jsonErr('无法读取上传文件');
        }

        if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls', 'xlsm'], true)) {
            return $this->jsonErr('仅支持 .csv / .txt / .xlsx / .xls / .xlsm');
        }

        InfluencerImportTaskRunner::bumpMemoryAndTime();
        try {
            $taskId = InfluencerImportTaskRunner::createFromUploadedFile($tmp, $ext);
        } catch (\Throwable $e) {
            Log::warning('influencer create import task: ' . $e->getMessage());

            return $this->jsonErr('创建导入任务失败：' . $e->getMessage() . '（请先执行 database/run_migration_influencers_crm.php）');
        }

        return $this->jsonOk([
            'mode' => 'async',
            'task_id' => $taskId,
        ], '任务已创建');
    }

    /**
     * 下载导入示例 CSV（表头 + 一行样例）
     */
    public function sampleCsv()
    {
        $csv = "\xEF\xBB\xBFtiktok_id,nickname,follower_count,region,contact,status\n@demo_creator,Demo昵称,12000,VN,line:demo,1\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sample_influencers.csv"',
        ], 'html');
    }

    /**
     * 导出全量达人 CSV（UTF-8 BOM，与导入列兼容：含 contact 原始文本）
     */
    public function exportCsv()
    {
        try {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $filename = 'influencers_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return $this->jsonErr('无法写入响应');
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'tiktok_id', 'nickname', 'avatar_url', 'follower_count', 'contact', 'region', 'status', 'created_at', 'updated_at']);

            $batch = 2000;
            $lastId = 0;
            while (true) {
                $rows = Db::name('influencers')
                    ->where('id', '>', $lastId)
                    ->order('id', 'asc')
                    ->limit($batch)
                    ->select();
                if ($rows === null || count($rows) === 0) {
                    break;
                }
                foreach ($rows as $row) {
                    $r = is_array($row) ? $row : $row->toArray();
                    $lastId = (int) ($r['id'] ?? 0);
                    $contact = (string) ($r['contact_info'] ?? '');
                    fputcsv($out, [
                        $r['id'] ?? '',
                        $r['tiktok_id'] ?? '',
                        $r['nickname'] ?? '',
                        $r['avatar_url'] ?? '',
                        (int) ($r['follower_count'] ?? 0),
                        $contact,
                        $r['region'] ?? '',
                        (int) ($r['status'] ?? 0),
                        (string) ($r['created_at'] ?? ''),
                        (string) ($r['updated_at'] ?? ''),
                    ]);
                }
            }
            fclose($out);
        } catch (\Throwable $e) {
            Log::error('influencer exportCsv: ' . $e->getMessage());

            if (!headers_sent()) {
                return $this->jsonErr('导出失败：' . $e->getMessage());
            }
        }

        exit;
    }

    /**
     * POST：编辑达人（不可改 tiktok_id）
     */
    public function update()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->jsonErr('仅支持 POST');
            }
            $payload = $this->parseJsonOrPost();
            $id = (int) ($payload['id'] ?? 0);
            if ($id <= 0) {
                return $this->jsonErr('无效 id');
            }
            $row = InfluencerModel::find($id);
            if (!$row) {
                return $this->jsonErr('记录不存在');
            }

            if (isset($payload['nickname'])) {
                $row->nickname = trim((string) $payload['nickname']);
            }
            if (array_key_exists('avatar_url', $payload)) {
                $a = trim((string) $payload['avatar_url']);
                $row->avatar_url = $a !== '' ? mb_substr($a, 0, 1024) : null;
            }
            if (isset($payload['follower_count'])) {
                $row->follower_count = max(0, (int) $payload['follower_count']);
            }
            if (array_key_exists('contact_text', $payload)) {
                $t = trim((string) $payload['contact_text']);
                $row->contact_info = $t !== '' ? InfluencerService::normalizeContactInfo($t) : null;
            }
            if (array_key_exists('region', $payload)) {
                $r = trim((string) $payload['region']);
                $row->region = $r !== '' ? mb_substr($r, 0, 64) : null;
            }
            if (isset($payload['status'])) {
                $st = (int) $payload['status'];
                if ($st >= 0 && $st <= 2) {
                    $row->status = $st;
                }
            }
            $row->save();

            return $this->jsonOk([], '已保存');
        } catch (\Throwable $e) {
            Log::error('influencer update: ' . $e->getMessage());

            return $this->jsonErr('保存失败：' . $e->getMessage());
        }
    }

    /**
     * POST：删除达人（先解除达人链上的关联）
     */
    public function delete()
    {
        try {
            if (!$this->request->isPost()) {
                return $this->jsonErr('仅支持 POST');
            }
            $payload = $this->parseJsonOrPost();
            $id = (int) ($payload['id'] ?? 0);
            if ($id <= 0) {
                $id = (int) $this->request->param('id', 0);
            }
            if ($id <= 0) {
                return $this->jsonErr('无效 id');
            }
            $row = InfluencerModel::find($id);
            if (!$row) {
                return $this->jsonErr('记录不存在');
            }
            Db::name('product_links')->where('influencer_id', $id)->update(['influencer_id' => null]);
            $row->delete();

            return $this->jsonOk([], '已删除');
        } catch (\Throwable $e) {
            Log::error('influencer delete: ' . $e->getMessage());

            return $this->jsonErr('删除失败：' . $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonOrPost(): array
    {
        $raw = (string) $this->request->getContent();
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                return $j;
            }
        }

        return $this->request->post();
    }
}
