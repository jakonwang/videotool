<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\Influencer as InfluencerModel;
use app\model\InfluencerOutreachTask as InfluencerOutreachTaskModel;
use app\model\MessageTemplate as MessageTemplateModel;
use app\model\OutreachLog as OutreachLogModel;
use app\service\AdminAuthService;
use app\service\InfluencerService;
use app\service\InfluencerStatusFlowService;
use think\facade\Db;
use think\facade\View;

class OutreachWorkspace extends BaseController
{
    private const TASK_PENDING = 0;
    private const TASK_COPIED = 1;
    private const TASK_JUMPED = 2;
    private const TASK_COMPLETED = 3;
    private const TASK_SKIPPED = 4;

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
        return View::fetch('admin/outreach_workspace/index', []);
    }

    public function listJson()
    {
        $page = (int) $this->request->param('page', 1);
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }
        $status = $this->request->param('task_status', '');
        $keyword = trim((string) $this->request->param('keyword', ''));
        $assignedOnly = (int) $this->request->param('assigned_only', 0) === 1;

        $query = InfluencerOutreachTaskModel::alias('t')
            ->leftJoin('influencers i', 'i.id = t.influencer_id')
            ->leftJoin('message_templates mt', 'mt.id = t.template_id')
            ->leftJoin('products p', 'p.id = t.product_id')
            ->field('t.*,i.tiktok_id,i.nickname,i.region,i.contact_info,mt.name as template_name,p.name as product_name')
            ->order('t.priority', 'desc')
            ->order('t.id', 'asc');
        if ($this->tableHasTenantId('influencer_outreach_tasks')) {
            $query->where('t.tenant_id', $this->currentTenantId());
        }
        if ($this->tableHasTenantId('influencers')) {
            $query->where('i.tenant_id', $this->currentTenantId());
        }
        if ($status !== '' && $status !== null) {
            $query->where('t.task_status', (int) $status);
        }
        if ($keyword !== '') {
            $query->where(function ($sub) use ($keyword) {
                $sub->whereLike('i.tiktok_id', '%' . $keyword . '%')
                    ->whereOr('i.nickname', 'like', '%' . $keyword . '%');
            });
        }
        if ($assignedOnly) {
            $query->where('t.assigned_to', AdminAuthService::userId());
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);
        $items = [];
        foreach ($list as $row) {
            $arr = $row->toArray();
            $items[] = $this->mapTaskRow($arr);
        }

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ]);
    }

    public function nextTaskJson()
    {
        $row = $this->pickNextTask();
        if (!$row) {
            return $this->jsonOk(['item' => null]);
        }
        return $this->jsonOk(['item' => $this->mapTaskRow($row)]);
    }

    public function generate()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $limit = (int) ($payload['limit'] ?? 100);
        if ($limit <= 0) {
            $limit = 100;
        }
        if ($limit > 1000) {
            $limit = 1000;
        }
        $templateId = (int) ($payload['template_id'] ?? 0);
        $productId = (int) ($payload['product_id'] ?? 0);
        $priority = (int) ($payload['priority'] ?? 0);
        $tagFilters = $this->normalizeTags($payload['tags'] ?? []);
        $categoryId = (int) ($payload['category_id'] ?? 0);
        $region = trim((string) ($payload['region'] ?? ''));
        $influencerStatus = $payload['influencer_status'] ?? '';
        $query = InfluencerModel::order('id', 'asc');
        $query = $this->scopeTenant($query, 'influencers');
        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }
        if ($region !== '') {
            $query->where('region', $region);
        }
        if ($influencerStatus !== '' && $influencerStatus !== null) {
            $query->where('status', (int) $influencerStatus);
        } else {
            $query->where('status', '<>', 6);
        }
        if ($tagFilters !== []) {
            foreach ($tagFilters as $tagItem) {
                $like = '%"' . addcslashes($tagItem, '\\"_%') . '"%';
                $query->whereLike('tags_json', $like);
            }
        }
        $rows = $query->field('id')->limit($limit)->select()->toArray();
        if ($rows === []) {
            return $this->jsonOk(['created' => 0, 'skipped' => 0]);
        }

        $ids = array_values(array_unique(array_map(static function ($r): int {
            return (int) ($r['id'] ?? 0);
        }, $rows)));
        $ids = array_values(array_filter($ids, static fn($v) => $v > 0));
        if ($ids === []) {
            return $this->jsonOk(['created' => 0, 'skipped' => 0]);
        }

        $openMap = [];
        $openRowsQuery = InfluencerOutreachTaskModel::whereIn('influencer_id', $ids)
            ->whereIn('task_status', [self::TASK_PENDING, self::TASK_COPIED, self::TASK_JUMPED])
            ->field('influencer_id');
        $openRows = $this->scopeTenant($openRowsQuery, 'influencer_outreach_tasks')
            ->select()
            ->toArray();
        foreach ($openRows as $r) {
            $openMap[(int) ($r['influencer_id'] ?? 0)] = 1;
        }

        $created = 0;
        $skipped = 0;
        $filterJson = json_encode([
            'category_id' => $categoryId,
            'region' => $region,
            'influencer_status' => $influencerStatus,
            'tags' => $tagFilters,
        ], JSON_UNESCAPED_UNICODE);

        foreach ($ids as $id) {
            if (isset($openMap[$id])) {
                $skipped++;
                continue;
            }
            $taskPayload = $this->withTenantPayload([
                'influencer_id' => $id,
                'template_id' => $templateId > 0 ? $templateId : null,
                'product_id' => $productId > 0 ? $productId : null,
                'task_status' => self::TASK_PENDING,
                'priority' => $priority,
                'assigned_to' => AdminAuthService::userId() > 0 ? AdminAuthService::userId() : null,
                'source_filter_json' => $filterJson,
            ], 'influencer_outreach_tasks');
            InfluencerOutreachTaskModel::create($taskPayload);
            $created++;
        }

        return $this->jsonOk(['created' => $created, 'skipped' => $skipped], 'generated');
    }

    public function action()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $taskId = (int) ($payload['task_id'] ?? 0);
        $action = trim((string) ($payload['action'] ?? ''));
        $templateId = (int) ($payload['template_id'] ?? 0);
        $productId = (int) ($payload['product_id'] ?? 0);
        $renderedBody = (string) ($payload['rendered_body'] ?? '');
        if ($taskId <= 0 || $action === '') {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $taskQuery = InfluencerOutreachTaskModel::where('id', $taskId);
        $taskQuery = $this->scopeTenant($taskQuery, 'influencer_outreach_tasks');
        $task = $taskQuery->find();
        if (!$task) {
            return $this->jsonErr('not_found', 1, null, 'common.notFound');
        }
        $infQuery = InfluencerModel::where('id', (int) ($task->influencer_id ?? 0));
        $infQuery = $this->scopeTenant($infQuery, 'influencers');
        $inf = $infQuery->find();
        if (!$inf) {
            return $this->jsonErr('influencer_not_found', 1, null, 'page.influencer.notFound');
        }

        if ($templateId > 0) {
            $task->template_id = $templateId;
        }
        if ($productId > 0) {
            $task->product_id = $productId;
        }

        $nextTaskStatus = self::TASK_PENDING;
        $channel = 'action_copy';
        $statusTarget = null;
        $allowSkip = false;
        if ($action === 'copy') {
            $nextTaskStatus = self::TASK_COPIED;
            $channel = 'action_copy';
            $statusTarget = 1;
        } elseif ($action === 'jump') {
            $nextTaskStatus = self::TASK_JUMPED;
            $channel = 'action_jump';
            $statusTarget = 1;
        } elseif ($action === 'complete') {
            $nextTaskStatus = self::TASK_COMPLETED;
            $channel = 'action_complete';
            $statusTarget = 2;
            $allowSkip = true;
        } elseif ($action === 'to_wait_sample') {
            $nextTaskStatus = self::TASK_COMPLETED;
            $channel = 'action_to_wait_sample';
            $statusTarget = 3;
            $allowSkip = true;
        } elseif ($action === 'skip') {
            $nextTaskStatus = self::TASK_SKIPPED;
            $channel = 'action_skip';
        } elseif ($action === 'reset') {
            $nextTaskStatus = self::TASK_PENDING;
            $channel = 'action_reset';
        } else {
            return $this->jsonErr('invalid_action', 1, null, 'common.invalidAction');
        }

        try {
            Db::transaction(function () use ($task, $inf, $nextTaskStatus, $channel, $renderedBody, $statusTarget, $allowSkip): void {
                $task->task_status = $nextTaskStatus;
                $task->assigned_to = AdminAuthService::userId() > 0 ? AdminAuthService::userId() : null;
                $task->last_action_at = date('Y-m-d H:i:s');
                $task->save();

                $tpl = null;
                if ((int) ($task->template_id ?? 0) > 0) {
                    $tplQuery = MessageTemplateModel::where('id', (int) $task->template_id);
                    $tplQuery = $this->scopeTenant($tplQuery, 'message_templates');
                    $tpl = $tplQuery->find();
                }
                $productName = '';
                if ((int) ($task->product_id ?? 0) > 0) {
                    $productQuery = Db::name('products')->where('id', (int) $task->product_id);
                    $productQuery = $this->scopeTenant($productQuery, 'products');
                    $p = $productQuery->find();
                    if ($p) {
                        $productName = (string) ($p['name'] ?? '');
                    }
                }
                $logPayload = $this->withTenantPayload([
                    'influencer_id' => (int) $inf->id,
                    'template_id' => (int) ($task->template_id ?? 0),
                    'template_name' => (string) ($tpl->name ?? ''),
                    'template_lang' => (string) ($tpl->lang ?? 'zh'),
                    'product_id' => (int) ($task->product_id ?? 0) > 0 ? (int) $task->product_id : null,
                    'product_name' => $productName !== '' ? $productName : null,
                    'channel' => $channel,
                    'rendered_body' => $renderedBody !== '' ? $renderedBody : null,
                ], 'outreach_logs');
                OutreachLogModel::create($logPayload);
                $touchQuery = InfluencerModel::where('id', (int) $inf->id);
                $touchQuery = $this->scopeTenant($touchQuery, 'influencers');
                $touchQuery->update(['last_contacted_at' => date('Y-m-d H:i:s')]);

                if ($statusTarget !== null) {
                    $current = (int) ($inf->status ?? 0);
                    if ($current < $statusTarget && $current !== 6) {
                        InfluencerStatusFlowService::transition(
                            (int) $inf->id,
                            (int) $statusTarget,
                            'outreach_workspace',
                            '',
                            ['channel' => $channel, 'task_id' => (int) $task->id],
                            $allowSkip
                        );
                    }
                }
            });
        } catch (\Throwable $e) {
            return $this->jsonErr('save_failed', 1, null, 'common.saveFailed');
        }

        $next = $this->pickNextTask();
        return $this->jsonOk([
            'task_status' => $nextTaskStatus,
            'next_item' => $next ? $this->mapTaskRow($next) : null,
        ], 'updated');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function pickNextTask(): ?array
    {
        $uid = AdminAuthService::userId();
        $query = InfluencerOutreachTaskModel::alias('t')
            ->leftJoin('influencers i', 'i.id = t.influencer_id')
            ->leftJoin('message_templates mt', 'mt.id = t.template_id')
            ->leftJoin('products p', 'p.id = t.product_id')
            ->field('t.*,i.tiktok_id,i.nickname,i.region,i.contact_info,mt.name as template_name,p.name as product_name')
            ->whereIn('t.task_status', [self::TASK_PENDING, self::TASK_COPIED, self::TASK_JUMPED])
            ->order('t.priority', 'desc')
            ->order('t.id', 'asc');
        if ($this->tableHasTenantId('influencer_outreach_tasks')) {
            $query->where('t.tenant_id', $this->currentTenantId());
        }
        if ($this->tableHasTenantId('influencers')) {
            $query->where('i.tenant_id', $this->currentTenantId());
        }
        if ($uid > 0) {
            $query->where(function ($sub) use ($uid) {
                $sub->whereNull('t.assigned_to')->whereOr('t.assigned_to', $uid);
            });
        }
        $row = $query->find();
        if (!$row) {
            return null;
        }
        return is_array($row) ? $row : $row->toArray();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapTaskRow(array $row): array
    {
        $channels = InfluencerService::contactChannelsFromStored((string) ($row['contact_info'] ?? ''));
        return [
            'id' => (int) ($row['id'] ?? 0),
            'influencer_id' => (int) ($row['influencer_id'] ?? 0),
            'template_id' => (int) ($row['template_id'] ?? 0),
            'product_id' => (int) ($row['product_id'] ?? 0),
            'task_status' => (int) ($row['task_status'] ?? 0),
            'priority' => (int) ($row['priority'] ?? 0),
            'due_at' => (string) ($row['due_at'] ?? ''),
            'assigned_to' => (int) ($row['assigned_to'] ?? 0),
            'last_action_at' => (string) ($row['last_action_at'] ?? ''),
            'tiktok_id' => (string) ($row['tiktok_id'] ?? ''),
            'nickname' => (string) ($row['nickname'] ?? ''),
            'region' => (string) ($row['region'] ?? ''),
            'template_name' => (string) ($row['template_name'] ?? ''),
            'product_name' => (string) ($row['product_name'] ?? ''),
            'contact_channels' => $channels,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private function normalizeTags($raw): array
    {
        $out = [];
        if (is_string($raw)) {
            $parts = preg_split('/[,\\x{FF0C}\\r\\n]/u', $raw) ?: [];
            foreach ($parts as $part) {
                $v = trim((string) $part);
                if ($v !== '') {
                    $out[] = mb_substr($v, 0, 24);
                }
            }
        } elseif (is_array($raw)) {
            foreach ($raw as $part) {
                $v = trim((string) $part);
                if ($v !== '') {
                    $out[] = mb_substr($v, 0, 24);
                }
            }
        }
        $out = array_values(array_unique($out));
        if (count($out) > 20) {
            $out = array_slice($out, 0, 20);
        }
        return $out;
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

