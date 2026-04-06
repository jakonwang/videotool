<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Category as CategoryModel;
use app\model\Influencer as InfluencerModel;
use app\model\OutreachLog as OutreachLogModel;
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
        $category = trim((string) $this->request->param('category', ''));
        $categoryId = (int) $this->request->param('category_id', 0);
        $tag = trim((string) $this->request->param('tag', ''));
        $sortByContact = (int) $this->request->param('sort_by_contact', 0);
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 10);
        if ($pageSize <= 0) {
            $pageSize = 10;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        $query = InfluencerModel::order('id', 'desc');
        if ($sortByContact === 1) {
            $query = InfluencerModel::orderRaw('last_contacted_at IS NULL ASC')
                ->order('last_contacted_at', 'desc')
                ->order('id', 'desc');
        }
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('tiktok_id', '%' . $keyword . '%')
                    ->whereOr('nickname', 'like', '%' . $keyword . '%');
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        if ($category !== '') {
            $query->where(function ($sub) use ($category) {
                $sub->where('category_name', $category)->whereOr('category_id', (int) $category);
            });
        }
        if ($tag !== '') {
            $query->whereLike('tags_json', '%' . $tag . '%');
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $contactRaw = (string) ($row->contact_info ?? '');
            $channels = InfluencerService::contactChannelsFromStored($contactRaw !== '' ? $contactRaw : null);
            $contactDisplay = InfluencerService::contactDisplayLine($channels);
            if ($contactDisplay === '' && $contactRaw !== '') {
                $contactDisplay = $contactRaw;
            }
            $items[] = [
                'id' => (int) $row->id,
                'tiktok_id' => (string) ($row->tiktok_id ?? ''),
                'category_name' => (string) ($row->category_name ?? ''),
                'category_id' => (int) ($row->category_id ?? 0),
                'nickname' => (string) ($row->nickname ?? ''),
                'avatar_url' => (string) ($row->avatar_url ?? ''),
                'follower_count' => (int) ($row->follower_count ?? 0),
                'contact_info' => $contactRaw,
                'contact_display' => $contactDisplay,
                'contact_channels' => $channels,
                'region' => (string) ($row->region ?? ''),
                'status' => (int) ($row->status ?? 0),
                'sample_tracking_no' => (string) ($row->sample_tracking_no ?? ''),
                'sample_status' => (int) ($row->sample_status ?? 0),
                'tags' => $this->parseTags((string) ($row->tags_json ?? '')),
                'last_contacted_at' => (string) ($row->last_contacted_at ?? ''),
                'created_at' => (string) ($row->created_at ?? ''),
                'updated_at' => (string) ($row->updated_at ?? ''),
            ];
        }

        return $this->jsonOk([
            'items' => $items,
            'categories' => CategoryModel::where('type', 'influencer')
                ->where('status', 1)
                ->order('sort_order', 'asc')
                ->order('id', 'desc')
                ->field('id,name')
                ->select()
                ->toArray(),
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
        $csv = "\xEF\xBB\xBFtiktok_id,category_name,nickname,follower_count,region,whatsapp,zalo,contact,status\n@demo_creator,美妆达人,Demo昵称,12000,VN,84912345678,84912345678,,1\n";

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
            fputcsv($out, ['id', 'tiktok_id', 'category_name', 'nickname', 'avatar_url', 'follower_count', 'contact', 'region', 'status', 'created_at', 'updated_at']);

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
                        $r['category_name'] ?? '',
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
            if (array_key_exists('category_id', $payload) || array_key_exists('category_name', $payload)) {
                $categoryId = (int) ($payload['category_id'] ?? 0);
                $categoryName = trim((string) ($payload['category_name'] ?? ''));
                if ($categoryId > 0) {
                    $cat = CategoryModel::where('id', $categoryId)->where('type', 'influencer')->find();
                    if ($cat) {
                        $categoryName = (string) ($cat->name ?? '');
                    }
                }
                $row->category_id = $categoryId > 0 ? $categoryId : null;
                $row->category_name = $categoryName !== '' ? mb_substr($categoryName, 0, 64) : null;
            }
            if (array_key_exists('avatar_url', $payload)) {
                $a = trim((string) $payload['avatar_url']);
                $row->avatar_url = $a !== '' ? mb_substr($a, 0, 1024) : null;
            }
            if (isset($payload['follower_count'])) {
                $row->follower_count = max(0, (int) $payload['follower_count']);
            }
            if (array_key_exists('contact_whatsapp', $payload)
                || array_key_exists('contact_zalo', $payload)
                || array_key_exists('contact_note', $payload)
                || array_key_exists('contact_text', $payload)) {
                $row->contact_info = InfluencerService::mergeContactFromUpdatePayload(
                    (string) ($row->contact_info ?? ''),
                    $payload
                );
            }
            if (array_key_exists('region', $payload)) {
                $r = trim((string) $payload['region']);
                $row->region = $r !== '' ? mb_substr($r, 0, 64) : null;
            }
            if (isset($payload['status'])) {
                $st = (int) $payload['status'];
                if ($st >= 0 && $st <= 6) {
                    $row->status = $st;
                }
            }
            if (array_key_exists('sample_tracking_no', $payload)) {
                $s = trim((string) $payload['sample_tracking_no']);
                $row->sample_tracking_no = $s !== '' ? mb_substr($s, 0, 64) : null;
            }
            if (array_key_exists('sample_status', $payload)) {
                $ss = (int) $payload['sample_status'];
                if ($ss < 0) {
                    $ss = 0;
                }
                if ($ss > 2) {
                    $ss = 2;
                }
                $row->sample_status = $ss;
            }
            if (array_key_exists('tags', $payload)) {
                $tags = $this->normalizeTags($payload['tags']);
                $row->tags_json = $tags !== [] ? json_encode($tags, JSON_UNESCAPED_UNICODE) : null;
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

    public function updateStatus()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('仅支持 POST');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $status = (int) ($payload['status'] ?? -1);
        if ($id <= 0 || $status < 0 || $status > 6) {
            return $this->jsonErr('参数错误');
        }
        $row = InfluencerModel::find($id);
        if (!$row) {
            return $this->jsonErr('记录不存在');
        }
        $row->status = $status;
        $row->save();

        return $this->jsonOk([], '已更新');
    }

    public function outreachHistory()
    {
        $id = (int) $this->request->param('influencer_id', 0);
        if ($id <= 0) {
            return $this->jsonErr('无效 influencer_id');
        }
        $rows = OutreachLogModel::where('influencer_id', $id)
            ->order('id', 'desc')
            ->limit(100)
            ->select();
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->id,
                'template_id' => (int) ($row->template_id ?? 0),
                'template_name' => (string) ($row->template_name ?? ''),
                'template_lang' => (string) ($row->template_lang ?? 'zh'),
                'product_id' => (int) ($row->product_id ?? 0),
                'product_name' => (string) ($row->product_name ?? ''),
                'channel' => (string) ($row->channel ?? 'render'),
                'rendered_body' => (string) ($row->rendered_body ?? ''),
                'created_at' => (string) ($row->created_at ?? ''),
            ];
        }

        return $this->jsonOk(['items' => $items]);
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

    /**
     * @return list<string>
     */
    private function parseTags(string $raw): array
    {
        if ($raw === '') {
            return [];
        }
        $j = json_decode($raw, true);
        if (!is_array($j)) {
            return [];
        }

        return $this->normalizeTags($j);
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizeTags($raw): array
    {
        $arr = [];
        if (is_string($raw)) {
            $parts = preg_split('/[,，\n]/u', $raw) ?: [];
            foreach ($parts as $part) {
                $part = trim((string) $part);
                if ($part !== '') {
                    $arr[] = mb_substr($part, 0, 24);
                }
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $item) {
                $part = trim((string) $item);
                if ($part !== '') {
                    $arr[] = mb_substr($part, 0, 24);
                }
            }
        }
        $arr = array_values(array_unique($arr));
        if (count($arr) > 20) {
            $arr = array_slice($arr, 0, 20);
        }

        return $arr;
    }
}
