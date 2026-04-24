<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\AutoDmCampaign as AutoDmCampaignModel;
use app\model\AutoDmEvent as AutoDmEventModel;
use app\model\AutoDmReplyReview as AutoDmReplyReviewModel;
use app\model\AutoDmTask as AutoDmTaskModel;
use app\service\AdminAuthService;
use app\service\AutoDmService;
use app\service\InfluencerService;
use app\service\InfluencerStatusFlowService;
use think\facade\Db;
use think\facade\View;

class AutoDm extends BaseController
{
    /**
     * @var array<string, bool>
     */
    private static array $columnCache = [];

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
        if (!$this->canManageAutoDm()) {
            return redirect('/admin.php');
        }
        return View::fetch('admin/auto_dm/index');
    }

    public function create()
    {
        if (!$this->canManageAutoDm()) {
            return $this->jsonErr('forbidden', 403, null, 'common.forbidden');
        }
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $tenantId = $this->currentTenantId();
        $templateId = (int) ($payload['template_id'] ?? 0);
        if ($templateId <= 0) {
            return $this->jsonErr('invalid_template_id', 1, null, 'common.invalidParams');
        }

        $campaignName = trim((string) ($payload['campaign_name'] ?? ''));
        if ($campaignName === '') {
            $campaignName = 'AutoDM-' . date('Ymd-His');
        }
        $campaignName = mb_substr($campaignName, 0, 128);

        $productId = max(0, (int) ($payload['product_id'] ?? 0));
        $preferredChannel = strtolower(trim((string) ($payload['preferred_channel'] ?? 'auto')));
        if (!in_array($preferredChannel, ['auto', 'zalo', 'wa', 'whatsapp'], true)) {
            $preferredChannel = 'auto';
        }
        $stopOnReply = (int) ($payload['stop_on_reply'] ?? 1) === 1 ? 1 : 0;
        $replyConfirmMode = strtolower(trim((string) ($payload['reply_confirm_mode'] ?? 'manual')));
        if (!in_array($replyConfirmMode, ['manual', 'rule_manual'], true)) {
            $replyConfirmMode = 'manual';
        }
        $abConfig = AutoDmService::normalizeAbConfig($payload['ab_templates'] ?? [], $templateId);
        $sequenceConfig = AutoDmService::normalizeSequenceConfig($payload['followup_plan'] ?? ($payload['sequence_config'] ?? []));

        $policy = AutoDmService::loadPolicy($tenantId);
        $policy['daily_limit'] = max(1, (int) ($payload['daily_limit'] ?? $policy['daily_limit']));
        $policy['time_window_start'] = (string) ($payload['time_window_start'] ?? $policy['time_window_start']);
        $policy['time_window_end'] = (string) ($payload['time_window_end'] ?? $policy['time_window_end']);
        $policy['min_interval_sec'] = max(10, (int) ($payload['min_interval_sec'] ?? $policy['min_interval_sec']));
        $policy['fail_fuse_threshold'] = max(1, (int) ($payload['fail_fuse_threshold'] ?? $policy['fail_fuse_threshold']));
        $policy = AutoDmService::normalizePolicy($policy);

        $influencerIds = $this->normalizeIdList($payload['influencer_ids'] ?? []);
        $limit = (int) ($payload['limit'] ?? 300);
        if ($limit <= 0) {
            $limit = 300;
        }
        if ($limit > 5000) {
            $limit = 5000;
        }

        $filters = [
            'keyword' => trim((string) ($payload['keyword'] ?? '')),
            'status' => (string) ($payload['status'] ?? ''),
            'category_id' => max(0, (int) ($payload['category_id'] ?? 0)),
            'region' => trim((string) ($payload['region'] ?? '')),
            'quality_grade' => strtoupper(trim((string) ($payload['quality_grade'] ?? ''))),
            'source_system' => strtolower(trim((string) ($payload['source_system'] ?? ''))),
            'influencer_ids' => $influencerIds,
            'limit' => $limit,
            'stop_on_reply' => $stopOnReply,
            'reply_confirm_mode' => $replyConfirmMode,
            'execute_client' => AutoDmService::normalizeExecuteClient((string) ($payload['execute_client'] ?? '')),
        ];

        $influencers = $this->pickInfluencers($filters);
        if ($influencers === []) {
            return $this->jsonOk([
                'campaign_id' => 0,
                'created' => 0,
                'blocked' => 0,
                'cooling' => 0,
                'skipped' => 0,
                'total_candidates' => 0,
            ]);
        }

        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $campaign = null;
        $stats = [
            'created' => 0,
            'blocked' => 0,
            'cooling' => 0,
            'failed' => 0,
            'skipped' => 0,
            'total_candidates' => count($influencers),
        ];
        $eventQueue = [];

        Db::transaction(function () use (
            $tenantId,
            $campaignName,
            $templateId,
            $productId,
            $preferredChannel,
            $stopOnReply,
            $replyConfirmMode,
            $abConfig,
            $sequenceConfig,
            $policy,
            $filters,
            $influencers,
            $today,
            $now,
            &$campaign,
            &$stats,
            &$eventQueue
        ): void {
            $campaignPayload = $this->withTenantPayload([
                'campaign_name' => $campaignName,
                'template_id' => $templateId,
                'product_id' => $productId > 0 ? $productId : null,
                'campaign_status' => AutoDmService::CAMPAIGN_STATUS_RUNNING,
                'preferred_channel' => $preferredChannel,
                'daily_limit' => (int) $policy['daily_limit'],
                'time_window_start' => (string) $policy['time_window_start'],
                'time_window_end' => (string) $policy['time_window_end'],
                'min_interval_sec' => (int) $policy['min_interval_sec'],
                'fail_fuse_threshold' => (int) $policy['fail_fuse_threshold'],
                'target_filter_json' => AutoDmService::encodeJson($filters),
                'stats_json' => AutoDmService::encodeJson($stats),
                'ab_config_json' => AutoDmService::encodeJson($abConfig),
                'sequence_config_json' => AutoDmService::encodeJson($sequenceConfig),
                'stop_on_reply' => $stopOnReply,
                'reply_confirm_mode' => $replyConfirmMode,
                'created_by' => AdminAuthService::userId() > 0 ? AdminAuthService::userId() : null,
            ], 'auto_dm_campaigns');
            $campaign = AutoDmCampaignModel::create($campaignPayload);
            $campaignId = (int) ($campaign->id ?? 0);
            if ($campaignId <= 0) {
                throw new \RuntimeException('campaign_create_failed');
            }

            foreach ($influencers as $row) {
                $influencerId = (int) ($row['id'] ?? 0);
                if ($influencerId <= 0) {
                    continue;
                }

                $channels = InfluencerService::contactChannelsFromStored((string) ($row['contact_info'] ?? ''));
                $routeChannel = AutoDmService::routeChannel($channels, $preferredChannel);
                $stepNo = 0;
                $idempotencyKey = AutoDmService::buildIdempotencyKey($tenantId, $influencerId, $campaignId, $today, $stepNo);
                $variantTemplateId = AutoDmService::pickVariantTemplateId($abConfig, $influencerId, $templateId);

                $exists = AutoDmTaskModel::where('idempotency_key', $idempotencyKey);
                $exists = $this->scopeTenant($exists, 'auto_dm_tasks');
                if ($exists->count() > 0) {
                    ++$stats['skipped'];
                    continue;
                }

                $taskStatus = AutoDmService::TASK_STATUS_PENDING;
                $errorCode = '';
                $errorMessage = '';
                $completedAt = null;

                if ((int) ($row['do_not_contact'] ?? 0) === 1 || (int) ($row['status'] ?? 0) === 6) {
                    $taskStatus = AutoDmService::TASK_STATUS_BLOCKED;
                    $errorCode = 'do_not_contact';
                    $errorMessage = 'do_not_contact';
                } elseif (AutoDmService::isCooldownActive((string) ($row['cooldown_until'] ?? ''), time())) {
                    $taskStatus = AutoDmService::TASK_STATUS_COOLING;
                    $errorCode = 'cooling';
                    $errorMessage = 'cooldown_until_active';
                } elseif (AutoDmService::isMinIntervalBlocked((string) ($row['last_auto_dm_at'] ?? ''), (int) $policy['cooldown_hours'] * 3600, time())) {
                    $taskStatus = AutoDmService::TASK_STATUS_BLOCKED;
                    $errorCode = 'cooldown_24h';
                    $errorMessage = 'last_auto_dm_within_cooldown';
                } elseif ($routeChannel === '') {
                    $taskStatus = AutoDmService::TASK_STATUS_BLOCKED;
                    $errorCode = 'fallback_skip';
                    $errorMessage = 'no_external_channel';
                }

                $taskType = AutoDmService::taskTypeByChannel($routeChannel);
                $renderMeta = ['text' => '', 'template_id' => 0, 'template_name' => '', 'template_lang' => 'en'];
                if ($taskStatus === AutoDmService::TASK_STATUS_PENDING) {
                    $renderMeta = AutoDmService::renderCampaignText($tenantId, $influencerId, $variantTemplateId, $productId);
                    if (trim((string) ($renderMeta['text'] ?? '')) === '') {
                        $taskStatus = AutoDmService::TASK_STATUS_BLOCKED;
                        $errorCode = 'render_empty';
                        $errorMessage = 'rendered_text_empty';
                    }
                }

                if (in_array($taskStatus, [AutoDmService::TASK_STATUS_BLOCKED, AutoDmService::TASK_STATUS_COOLING], true)) {
                    $completedAt = $now;
                }

                $taskPayload = [
                    'influencer_id' => $influencerId,
                    'campaign_id' => $campaignId,
                    'template_id' => $variantTemplateId,
                    'product_id' => $productId,
                    'target_channel' => $routeChannel,
                    'channels' => $channels,
                    'step_no' => $stepNo,
                    'variant_template_id' => $variantTemplateId,
                    'tiktok_id' => (string) ($row['tiktok_id'] ?? ''),
                    'nickname' => (string) ($row['nickname'] ?? ''),
                    'region' => (string) ($row['region'] ?? ''),
                    'preferred_channel' => $preferredChannel,
                ];

                $insert = $this->withTenantPayload([
                    'campaign_id' => $campaignId,
                    'influencer_id' => $influencerId,
                    'task_type' => $taskType,
                    'target_channel' => $routeChannel !== '' ? $routeChannel : 'fallback_skip',
                    'idempotency_key' => $idempotencyKey,
                    'step_no' => $stepNo,
                    'variant_template_id' => $variantTemplateId,
                    'priority' => AutoDmService::inferPriority($row),
                    'task_status' => $taskStatus,
                    'reply_state' => AutoDmService::REPLY_STATE_NONE,
                    'payload_json' => AutoDmService::encodeJson($taskPayload),
                    'rendered_text' => (string) ($renderMeta['text'] ?? ''),
                    'max_retries' => 2,
                    'retry_count' => 0,
                    'scheduled_at' => $now,
                    'next_execute_at' => $now,
                    'completed_at' => $completedAt,
                    'last_error_code' => $errorCode !== '' ? $errorCode : null,
                    'last_error_message' => $errorMessage !== '' ? $errorMessage : null,
                    'last_error_at' => $errorCode !== '' ? $now : null,
                ], 'auto_dm_tasks');
                $task = AutoDmTaskModel::create($insert);
                $taskId = (int) ($task->id ?? 0);

                if ($taskStatus === AutoDmService::TASK_STATUS_PENDING) {
                    ++$stats['created'];
                } elseif ($taskStatus === AutoDmService::TASK_STATUS_COOLING) {
                    ++$stats['cooling'];
                } elseif ($taskStatus === AutoDmService::TASK_STATUS_BLOCKED) {
                    ++$stats['blocked'];
                } else {
                    ++$stats['failed'];
                }

                $eventQueue[] = [
                    'tenant_id' => $tenantId,
                    'campaign_id' => $campaignId,
                    'task_id' => $taskId > 0 ? $taskId : null,
                    'influencer_id' => $influencerId,
                    'event_type' => $taskStatus === AutoDmService::TASK_STATUS_PENDING
                        ? AutoDmService::EVENT_CREATED
                        : ($taskStatus === AutoDmService::TASK_STATUS_COOLING ? AutoDmService::EVENT_COOLING : AutoDmService::EVENT_BLOCKED),
                    'event_status' => $taskStatus === AutoDmService::TASK_STATUS_PENDING ? 1 : 2,
                    'error_code' => $errorCode !== '' ? $errorCode : null,
                    'error_message' => $errorMessage !== '' ? $errorMessage : null,
                    'payload_json' => AutoDmService::encodeJson([
                        'task_status' => $taskStatus,
                        'target_channel' => $routeChannel,
                    ]),
                ];
            }

            $campaign->save([
                'total_targets' => (int) ($stats['created'] + $stats['blocked'] + $stats['cooling']),
                'total_blocked' => (int) ($stats['blocked'] + $stats['cooling']),
                'stats_json' => AutoDmService::encodeJson($stats),
                'last_run_at' => $now,
            ]);
        });

        $campaignId = (int) (($campaign && isset($campaign->id)) ? $campaign->id : 0);
        if ($eventQueue !== []) {
            foreach ($eventQueue as $eventRow) {
                $this->appendEvent($eventRow);
            }
        }

        return $this->jsonOk([
            'campaign_id' => $campaignId,
            'created' => (int) $stats['created'],
            'blocked' => (int) $stats['blocked'],
            'cooling' => (int) $stats['cooling'],
            'skipped' => (int) $stats['skipped'],
            'total_candidates' => (int) $stats['total_candidates'],
            'stop_on_reply' => $stopOnReply,
            'reply_confirm_mode' => $replyConfirmMode,
            'sequence' => $sequenceConfig,
            'ab_config' => $abConfig,
        ], 'created');
    }

    public function list()
    {
        if (!$this->canManageAutoDm()) {
            return $this->jsonErr('forbidden', 403, null, 'common.forbidden');
        }
        $page = max(1, (int) $this->request->param('page', 1));
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }
        $status = trim((string) $this->request->param('campaign_status', ''));
        $keyword = trim((string) $this->request->param('keyword', ''));

        $query = AutoDmCampaignModel::order('id', 'desc');
        $query = $this->scopeTenant($query, 'auto_dm_campaigns');
        if ($status !== '') {
            $query->where('campaign_status', (int) $status);
        }
        if ($keyword !== '') {
            $query->whereLike('campaign_name', '%' . $keyword . '%');
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);
        $pageRows = $list->items();
        if (!is_array($pageRows)) {
            $pageRows = iterator_to_array($list);
        }
        $campaignIds = [];
        foreach ($pageRows as $row) {
            $campaignIds[] = is_array($row) ? (int) ($row['id'] ?? 0) : (int) ($row->id ?? 0);
        }
        $campaignMetrics = $this->buildCampaignMetrics($campaignIds);

        $items = [];
        foreach ($pageRows as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $stats = AutoDmService::decodeJsonObject((string) ($arr['stats_json'] ?? ''));
            $abConfig = AutoDmService::decodeJsonObject((string) ($arr['ab_config_json'] ?? ''));
            $sequenceConfig = AutoDmService::decodeJsonObject((string) ($arr['sequence_config_json'] ?? ''));
            $targetFilters = AutoDmService::decodeJsonObject((string) ($arr['target_filter_json'] ?? ''));
            $executeClient = AutoDmService::normalizeExecuteClient((string) ($targetFilters['execute_client'] ?? ''));
            $metric = $campaignMetrics[(int) ($arr['id'] ?? 0)] ?? [
                'funnel' => [
                    'pending' => 0,
                    'sending' => 0,
                    'sent' => 0,
                    'reply_pending' => 0,
                    'converted' => 0,
                    'blocked' => 0,
                ],
                'reply_pending' => 0,
                'unsubscribe_rate' => 0,
            ];
            $items[] = [
                'id' => (int) ($arr['id'] ?? 0),
                'campaign_name' => (string) ($arr['campaign_name'] ?? ''),
                'template_id' => (int) ($arr['template_id'] ?? 0),
                'product_id' => (int) ($arr['product_id'] ?? 0),
                'campaign_status' => (int) ($arr['campaign_status'] ?? 0),
                'preferred_channel' => (string) ($arr['preferred_channel'] ?? 'auto'),
                'daily_limit' => (int) ($arr['daily_limit'] ?? 0),
                'time_window_start' => (string) ($arr['time_window_start'] ?? '09:00'),
                'time_window_end' => (string) ($arr['time_window_end'] ?? '21:00'),
                'min_interval_sec' => (int) ($arr['min_interval_sec'] ?? 0),
                'fail_fuse_threshold' => (int) ($arr['fail_fuse_threshold'] ?? 0),
                'stop_on_reply' => (int) ($arr['stop_on_reply'] ?? 1),
                'reply_confirm_mode' => (string) ($arr['reply_confirm_mode'] ?? 'manual'),
                'total_targets' => (int) ($arr['total_targets'] ?? 0),
                'total_sent' => (int) ($arr['total_sent'] ?? 0),
                'total_failed' => (int) ($arr['total_failed'] ?? 0),
                'total_blocked' => (int) ($arr['total_blocked'] ?? 0),
                'total_replied' => (int) ($arr['total_replied'] ?? 0),
                'total_unsubscribed' => (int) ($arr['total_unsubscribed'] ?? 0),
                'stats' => $stats,
                'ab_config' => $abConfig,
                'sequence_config' => $sequenceConfig,
                'execute_client' => $executeClient,
                'funnel' => $metric['funnel'],
                'reply_pending_count' => (int) ($metric['reply_pending'] ?? 0),
                'unsubscribe_rate' => (float) ($metric['unsubscribe_rate'] ?? 0),
                'last_run_at' => (string) ($arr['last_run_at'] ?? ''),
                'paused_at' => (string) ($arr['paused_at'] ?? ''),
                'created_at' => (string) ($arr['created_at'] ?? ''),
                'updated_at' => (string) ($arr['updated_at'] ?? ''),
            ];
        }

        $summary = $this->buildSummary();

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
            'summary' => $summary,
        ]);
    }

    public function pause()
    {
        if (!$this->canManageAutoDm()) {
            return $this->jsonErr('forbidden', 403, null, 'common.forbidden');
        }
        return $this->toggleCampaignStatus(AutoDmService::CAMPAIGN_STATUS_PAUSED);
    }

    public function resume()
    {
        if (!$this->canManageAutoDm()) {
            return $this->jsonErr('forbidden', 403, null, 'common.forbidden');
        }
        return $this->toggleCampaignStatus(AutoDmService::CAMPAIGN_STATUS_RUNNING);
    }

    public function replyQueueList()
    {
        if (!$this->canManageAutoDm()) {
            return $this->jsonErr('forbidden', 403, null, 'common.forbidden');
        }
        $page = max(1, (int) $this->request->param('page', 1));
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 100) {
            $pageSize = 100;
        }

        $campaignId = (int) $this->request->param('campaign_id', 0);
        $state = strtolower(trim((string) $this->request->param('state', 'pending')));
        $keyword = trim((string) $this->request->param('keyword', ''));

        try {
            $query = AutoDmTaskModel::alias('t')
                ->leftJoin('auto_dm_campaigns c', 'c.id = t.campaign_id')
                ->leftJoin('influencers i', 'i.id = t.influencer_id')
                ->leftJoin('auto_dm_reply_reviews rr', 'rr.task_id = t.id')
                ->field([
                    't.id',
                    't.campaign_id',
                    't.influencer_id',
                    't.task_type',
                    't.target_channel',
                    't.step_no',
                    't.reply_state',
                    't.reply_text',
                    't.reply_at',
                    't.delivery_status',
                    't.conversation_snippet',
                    't.updated_at',
                    'c.campaign_name',
                    'i.tiktok_id',
                    'i.nickname',
                    'i.region',
                    'rr.rule_category',
                    'rr.confirm_category',
                    'rr.confirmed_by',
                    'rr.confirmed_at',
                ])
                ->order('t.reply_at', 'desc')
                ->order('t.id', 'desc');
            if ($this->tableHasTenantId('auto_dm_tasks')) {
                $query->where('t.tenant_id', $this->currentTenantId());
            }
            if ($this->tableHasTenantId('auto_dm_campaigns')) {
                $query->where('c.tenant_id', $this->currentTenantId());
            }
            if ($this->tableHasTenantId('influencers')) {
                $query->where('i.tenant_id', $this->currentTenantId());
            }
            if ($this->tableHasTenantId('auto_dm_reply_reviews')) {
                $query->where(function ($sub): void {
                    $sub->whereNull('rr.tenant_id')
                        ->whereOr('rr.tenant_id', $this->currentTenantId());
                });
            }
            if ($campaignId > 0) {
                $query->where('t.campaign_id', $campaignId);
            }
            if ($state === 'reviewed') {
                $query->where('t.reply_state', AutoDmService::REPLY_STATE_REVIEWED);
            } else {
                $query->where('t.reply_state', AutoDmService::REPLY_STATE_DETECTED);
            }
            if ($keyword !== '') {
                $query->where(function ($sub) use ($keyword): void {
                    $sub->whereLike('i.tiktok_id', '%' . $keyword . '%')
                        ->whereOr('i.nickname', 'like', '%' . $keyword . '%')
                        ->whereOr('t.reply_text', 'like', '%' . $keyword . '%')
                        ->whereOr('c.campaign_name', 'like', '%' . $keyword . '%');
                });
            }

            $list = $query->paginate([
                'list_rows' => $pageSize,
                'page' => $page,
                'query' => $this->request->param(),
            ]);
        } catch (\Throwable $e) {
            return $this->jsonErr('save_failed', 1, null, 'common.saveFailed');
        }

        $items = [];
        foreach ($list as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $replyText = (string) ($arr['reply_text'] ?? '');
            $rule = AutoDmService::classifyReplyByRules($replyText);
            $items[] = [
                'id' => (int) ($arr['id'] ?? 0),
                'campaign_id' => (int) ($arr['campaign_id'] ?? 0),
                'campaign_name' => (string) ($arr['campaign_name'] ?? ''),
                'influencer_id' => (int) ($arr['influencer_id'] ?? 0),
                'tiktok_id' => (string) ($arr['tiktok_id'] ?? ''),
                'nickname' => (string) ($arr['nickname'] ?? ''),
                'region' => (string) ($arr['region'] ?? ''),
                'task_type' => (string) ($arr['task_type'] ?? ''),
                'target_channel' => (string) ($arr['target_channel'] ?? ''),
                'step_no' => (int) ($arr['step_no'] ?? 0),
                'reply_state' => (int) ($arr['reply_state'] ?? 0),
                'reply_text' => $replyText,
                'reply_at' => (string) ($arr['reply_at'] ?? ''),
                'delivery_status' => (string) ($arr['delivery_status'] ?? ''),
                'conversation_snippet' => (string) ($arr['conversation_snippet'] ?? ''),
                'rule_category' => (string) ($arr['rule_category'] ?? ($rule['category'] ?? 'other')),
                'rule_matched' => (string) ($rule['matched'] ?? ''),
                'confirm_category' => (string) ($arr['confirm_category'] ?? ''),
                'confirmed_by' => (int) ($arr['confirmed_by'] ?? 0),
                'confirmed_at' => (string) ($arr['confirmed_at'] ?? ''),
                'updated_at' => (string) ($arr['updated_at'] ?? ''),
            ];
        }

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ]);
    }

    public function replyQueueConfirm()
    {
        if (!$this->canManageAutoDm()) {
            return $this->jsonErr('forbidden', 403, null, 'common.forbidden');
        }
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $taskId = (int) ($payload['task_id'] ?? 0);
        if ($taskId <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $confirmCategory = strtolower(trim((string) ($payload['confirm_category'] ?? '')));
        $allowedCategories = ['intent', 'inquiry', 'reject', 'unsubscribe', 'other'];
        if ($confirmCategory !== '' && !in_array($confirmCategory, $allowedCategories, true)) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $confirmNote = mb_substr(trim((string) ($payload['confirm_note'] ?? '')), 0, 500);
        $transitionStatusInput = (int) ($payload['transition_status'] ?? 0);

        $taskQuery = AutoDmTaskModel::where('id', $taskId);
        $taskQuery = $this->scopeTenant($taskQuery, 'auto_dm_tasks');
        $task = $taskQuery->find();
        if (!$task) {
            return $this->jsonErr('not_found', 404, null, 'common.notFound');
        }
        $replyText = trim((string) ($task->reply_text ?? ''));
        if ($replyText === '') {
            return $this->jsonErr('reply_text_empty', 1, null, 'common.invalidParams');
        }

        $rule = AutoDmService::classifyReplyByRules($replyText);
        if ($confirmCategory === '') {
            $confirmCategory = (string) ($rule['category'] ?? 'other');
        }
        if (!in_array($confirmCategory, $allowedCategories, true)) {
            $confirmCategory = 'other';
        }
        $transitionStatus = $transitionStatusInput > 0
            ? $transitionStatusInput
            : AutoDmService::replyCategoryDefaultStatus($confirmCategory);
        if ($transitionStatus < 0 || $transitionStatus > 6) {
            $transitionStatus = AutoDmService::replyCategoryDefaultStatus($confirmCategory);
        }

        $campaignQuery = AutoDmCampaignModel::where('id', (int) ($task->campaign_id ?? 0));
        $campaignQuery = $this->scopeTenant($campaignQuery, 'auto_dm_campaigns');
        $campaign = $campaignQuery->find();
        if (!$campaign) {
            return $this->jsonErr('not_found', 404, null, 'common.notFound');
        }

        $now = date('Y-m-d H:i:s');
        $tenantId = $this->currentTenantId();
        $wasReviewed = (int) ($task->reply_state ?? 0) === AutoDmService::REPLY_STATE_REVIEWED;

        Db::transaction(function () use (
            $task,
            $campaign,
            $tenantId,
            $rule,
            $confirmCategory,
            $transitionStatus,
            $confirmNote,
            $now,
            $wasReviewed
        ): void {
            $reviewQuery = AutoDmReplyReviewModel::where('task_id', (int) ($task->id ?? 0));
            $reviewQuery = $this->scopeTenant($reviewQuery, 'auto_dm_reply_reviews');
            $review = $reviewQuery->find();
            $reviewData = $this->withTenantPayload([
                'campaign_id' => (int) ($task->campaign_id ?? 0),
                'task_id' => (int) ($task->id ?? 0),
                'influencer_id' => (int) ($task->influencer_id ?? 0),
                'channel' => (string) ($task->target_channel ?? ''),
                'step_no' => (int) ($task->step_no ?? 0),
                'reply_text' => (string) ($task->reply_text ?? ''),
                'rule_category' => (string) ($rule['category'] ?? 'other'),
                'confirm_category' => $confirmCategory,
                'transition_target_status' => $transitionStatus,
                'confirm_note' => $confirmNote !== '' ? $confirmNote : null,
                'confirmed_by' => AdminAuthService::userId() > 0 ? AdminAuthService::userId() : null,
                'confirmed_at' => $now,
            ], 'auto_dm_reply_reviews');
            if ($review) {
                $review->save($reviewData);
            } else {
                AutoDmReplyReviewModel::create($reviewData);
            }

            $task->reply_state = AutoDmService::REPLY_STATE_REVIEWED;
            if ($confirmCategory === 'unsubscribe') {
                $task->task_status = AutoDmService::TASK_STATUS_BLOCKED;
            }
            $task->updated_at = $now;
            $task->save();

            $this->appendEvent([
                'tenant_id' => $tenantId,
                'campaign_id' => (int) ($task->campaign_id ?? 0),
                'task_id' => (int) ($task->id ?? 0),
                'influencer_id' => (int) ($task->influencer_id ?? 0),
                'event_type' => AutoDmService::EVENT_REPLY_CONFIRMED,
                'event_status' => 1,
                'payload_json' => AutoDmService::encodeJson([
                    'rule_category' => (string) ($rule['category'] ?? 'other'),
                    'confirm_category' => $confirmCategory,
                    'transition_status' => $transitionStatus,
                ]),
            ]);

            $logPayload = $this->withTenantPayload([
                'influencer_id' => (int) ($task->influencer_id ?? 0),
                'template_id' => (int) ($task->variant_template_id ?? 0),
                'channel' => 'auto_dm',
                'rendered_body' => (string) ($task->reply_text ?? ''),
            ], 'outreach_logs');
            if ($this->columnExists('outreach_logs', 'action_type')) {
                $logPayload['action_type'] = 'auto_dm_reply_confirmed';
            }
            Db::name('outreach_logs')->insert($logPayload);

            if (!$wasReviewed) {
                $update = [
                    'total_replied' => Db::raw('total_replied + 1'),
                    'last_run_at' => $now,
                    'updated_at' => $now,
                ];
                if ($confirmCategory === 'unsubscribe') {
                    $update['total_unsubscribed'] = Db::raw('total_unsubscribed + 1');
                }
                $campaignQuery = Db::name('auto_dm_campaigns')->where('id', (int) ($campaign->id ?? 0));
                if ($this->tableHasTenantId('auto_dm_campaigns')) {
                    $campaignQuery->where('tenant_id', $tenantId);
                }
                $campaignQuery->update($update);
            }

            $infQuery = Db::name('influencers')->where('id', (int) ($task->influencer_id ?? 0));
            if ($this->tableHasTenantId('influencers')) {
                $infQuery->where('tenant_id', $tenantId);
            }
            if ($confirmCategory === 'unsubscribe') {
                $infUpdate = ['last_contacted_at' => $now];
                if ($this->columnExists('influencers', 'do_not_contact')) {
                    $infUpdate['do_not_contact'] = 1;
                }
                if ($this->columnExists('influencers', 'cooldown_until')) {
                    $infUpdate['cooldown_until'] = date('Y-m-d H:i:s', strtotime('+3650 day'));
                }
                $infQuery->update($infUpdate);
                InfluencerStatusFlowService::transition(
                    (int) ($task->influencer_id ?? 0),
                    6,
                    'auto_dm_reply_confirm',
                    '',
                    ['task_id' => (int) $task->id, 'category' => $confirmCategory],
                    true
                );
            } else {
                $infQuery->update(['last_contacted_at' => $now]);
                InfluencerStatusFlowService::transition(
                    (int) ($task->influencer_id ?? 0),
                    $transitionStatus,
                    'auto_dm_reply_confirm',
                    '',
                    ['task_id' => (int) $task->id, 'category' => $confirmCategory],
                    true
                );
            }

            if ((int) ($campaign->stop_on_reply ?? 1) === 1) {
                $pendingQuery = AutoDmTaskModel::where('campaign_id', (int) ($task->campaign_id ?? 0))
                    ->where('influencer_id', (int) ($task->influencer_id ?? 0))
                    ->where('step_no', '>', (int) ($task->step_no ?? 0))
                    ->whereIn('task_status', [
                        AutoDmService::TASK_STATUS_PENDING,
                        AutoDmService::TASK_STATUS_ASSIGNED,
                        AutoDmService::TASK_STATUS_SENDING,
                    ]);
                $pendingQuery = $this->scopeTenant($pendingQuery, 'auto_dm_tasks');
                $pendingQuery->update([
                    'task_status' => AutoDmService::TASK_STATUS_BLOCKED,
                    'completed_at' => $now,
                    'last_error_code' => 'stop_on_reply',
                    'last_error_message' => 'stopped_by_reply_confirm',
                    'last_error_at' => $now,
                ]);
            }
        });

        return $this->jsonOk([
            'task_id' => $taskId,
            'confirm_category' => $confirmCategory,
            'transition_status' => $transitionStatus,
        ], 'updated');
    }

    public function rebuildFollowups()
    {
        if (!$this->canManageAutoDm()) {
            return $this->jsonErr('forbidden', 403, null, 'common.forbidden');
        }
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $campaignId = (int) ($payload['campaign_id'] ?? 0);
        if ($campaignId <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $limit = (int) ($payload['limit'] ?? 500);
        if ($limit <= 0) {
            $limit = 500;
        }
        if ($limit > 5000) {
            $limit = 5000;
        }

        $campaignQuery = AutoDmCampaignModel::where('id', $campaignId);
        $campaignQuery = $this->scopeTenant($campaignQuery, 'auto_dm_campaigns');
        $campaign = $campaignQuery->find();
        if (!$campaign) {
            return $this->jsonErr('not_found', 404, null, 'common.notFound');
        }

        $sequenceConfig = AutoDmService::normalizeSequenceConfig((string) ($campaign->sequence_config_json ?? ''));
        $maxStepNo = AutoDmService::maxStepNo($sequenceConfig);
        if ($maxStepNo <= 0) {
            return $this->jsonOk([
                'campaign_id' => $campaignId,
                'created' => 0,
                'skipped' => 0,
                'blocked' => 0,
            ]);
        }
        $abConfig = AutoDmService::normalizeAbConfig((string) ($campaign->ab_config_json ?? ''), (int) ($campaign->template_id ?? 0));
        $tenantId = $this->currentTenantId();

        $seedTasks = AutoDmTaskModel::where('campaign_id', $campaignId)
            ->where('task_status', AutoDmService::TASK_STATUS_SENT)
            ->where('reply_state', AutoDmService::REPLY_STATE_NONE)
            ->where('step_no', '<', $maxStepNo)
            ->order('id', 'asc')
            ->limit($limit);
        $seedTasks = $this->scopeTenant($seedTasks, 'auto_dm_tasks');
        $rows = $seedTasks->select()->toArray();

        $created = 0;
        $skipped = 0;
        $blocked = 0;
        $eventQueue = [];
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            $influencerId = (int) ($row['influencer_id'] ?? 0);
            if ($influencerId <= 0) {
                ++$skipped;
                continue;
            }
            $nextStep = (int) ($row['step_no'] ?? 0) + 1;
            if ($nextStep > $maxStepNo) {
                ++$skipped;
                continue;
            }

            if ((int) ($campaign->stop_on_reply ?? 1) === 1) {
                $repliedQuery = AutoDmTaskModel::where('campaign_id', $campaignId)
                    ->where('influencer_id', $influencerId)
                    ->where('reply_state', '>', AutoDmService::REPLY_STATE_NONE);
                $repliedQuery = $this->scopeTenant($repliedQuery, 'auto_dm_tasks');
                if ($repliedQuery->count() > 0) {
                    ++$blocked;
                    continue;
                }
            }

            $existsQuery = AutoDmTaskModel::where('campaign_id', $campaignId)
                ->where('influencer_id', $influencerId)
                ->where('step_no', $nextStep);
            $existsQuery = $this->scopeTenant($existsQuery, 'auto_dm_tasks');
            if ($existsQuery->count() > 0) {
                ++$skipped;
                continue;
            }

            $infQuery = Db::name('influencers')->where('id', $influencerId)
                ->field('id,tiktok_id,nickname,region,status,contact_info,last_auto_dm_at,do_not_contact,cooldown_until,quality_score,quality_grade');
            if ($this->tableHasTenantId('influencers')) {
                $infQuery->where('tenant_id', $tenantId);
            }
            $inf = $infQuery->find();
            if (!$inf) {
                ++$skipped;
                continue;
            }
            if ((int) ($inf['do_not_contact'] ?? 0) === 1 || (int) ($inf['status'] ?? 0) === 6) {
                ++$blocked;
                continue;
            }

            $channels = InfluencerService::contactChannelsFromStored((string) ($inf['contact_info'] ?? ''));
            $targetChannel = AutoDmService::routeChannel($channels, (string) ($campaign->preferred_channel ?? 'auto'));
            if ($targetChannel === '') {
                ++$blocked;
                continue;
            }
            $taskType = AutoDmService::taskTypeByChannel($targetChannel);
            $variantTemplateId = AutoDmService::pickVariantTemplateId($abConfig, $influencerId, (int) ($campaign->template_id ?? 0));
            $productId = (int) ($campaign->product_id ?? 0);
            $renderMeta = AutoDmService::renderCampaignText($tenantId, $influencerId, $variantTemplateId, $productId);
            $rendered = trim((string) ($renderMeta['text'] ?? ''));
            if ($rendered === '') {
                ++$blocked;
                continue;
            }

            $sentAt = trim((string) ($row['sent_at'] ?? ''));
            $baseTs = strtotime($sentAt);
            if ($baseTs === false) {
                $baseTs = time();
            }
            $delayHours = AutoDmService::stepDelayHours($sequenceConfig, $nextStep);
            $nextExecuteAt = date('Y-m-d H:i:s', $baseTs + $delayHours * 3600);
            $idemDay = date('Y-m-d', $baseTs);
            $idempotencyKey = AutoDmService::buildIdempotencyKey($tenantId, $influencerId, $campaignId, $idemDay, $nextStep);

            $existsIdemQuery = AutoDmTaskModel::where('idempotency_key', $idempotencyKey);
            $existsIdemQuery = $this->scopeTenant($existsIdemQuery, 'auto_dm_tasks');
            if ($existsIdemQuery->count() > 0) {
                ++$skipped;
                continue;
            }

            $taskPayload = [
                'influencer_id' => $influencerId,
                'campaign_id' => $campaignId,
                'template_id' => $variantTemplateId,
                'product_id' => $productId,
                'target_channel' => $targetChannel,
                'channels' => $channels,
                'step_no' => $nextStep,
                'variant_template_id' => $variantTemplateId,
                'tiktok_id' => (string) ($inf['tiktok_id'] ?? ''),
                'nickname' => (string) ($inf['nickname'] ?? ''),
                'region' => (string) ($inf['region'] ?? ''),
                'preferred_channel' => (string) ($campaign->preferred_channel ?? 'auto'),
            ];
            $insert = $this->withTenantPayload([
                'campaign_id' => $campaignId,
                'influencer_id' => $influencerId,
                'task_type' => $taskType,
                'target_channel' => $targetChannel,
                'idempotency_key' => $idempotencyKey,
                'step_no' => $nextStep,
                'variant_template_id' => $variantTemplateId,
                'priority' => AutoDmService::inferPriority($inf),
                'task_status' => AutoDmService::TASK_STATUS_PENDING,
                'reply_state' => AutoDmService::REPLY_STATE_NONE,
                'payload_json' => AutoDmService::encodeJson($taskPayload),
                'rendered_text' => $rendered,
                'max_retries' => 2,
                'retry_count' => 0,
                'scheduled_at' => $nextExecuteAt,
                'next_execute_at' => $nextExecuteAt,
            ], 'auto_dm_tasks');
            $task = AutoDmTaskModel::create($insert);
            ++$created;

            $eventQueue[] = [
                'tenant_id' => $tenantId,
                'campaign_id' => $campaignId,
                'task_id' => (int) ($task->id ?? 0),
                'influencer_id' => $influencerId,
                'event_type' => AutoDmService::EVENT_CREATED,
                'event_status' => 1,
                'payload_json' => AutoDmService::encodeJson([
                    'step_no' => $nextStep,
                    'next_execute_at' => $nextExecuteAt,
                ]),
            ];
        }

        if ($eventQueue !== []) {
            foreach ($eventQueue as $event) {
                $this->appendEvent($event);
            }
        }

        return $this->jsonOk([
            'campaign_id' => $campaignId,
            'created' => $created,
            'skipped' => $skipped,
            'blocked' => $blocked,
            'max_step_no' => $maxStepNo,
        ], 'updated');
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    private function pickInfluencers(array $filters): array
    {
        $query = Db::name('influencers')->order('id', 'asc');
        $query = $this->scopeTenant($query, 'influencers');
        $ids = $this->normalizeIdList($filters['influencer_ids'] ?? []);
        if ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            $keyword = trim((string) ($filters['keyword'] ?? ''));
            $status = (string) ($filters['status'] ?? '');
            $categoryId = (int) ($filters['category_id'] ?? 0);
            $region = trim((string) ($filters['region'] ?? ''));
            $qualityGrade = strtoupper(trim((string) ($filters['quality_grade'] ?? '')));
            $sourceSystem = strtolower(trim((string) ($filters['source_system'] ?? '')));
            $limit = max(1, min(5000, (int) ($filters['limit'] ?? 300)));
            if ($keyword !== '') {
                $query->where(function ($sub) use ($keyword): void {
                    $sub->whereLike('tiktok_id', '%' . $keyword . '%')
                        ->whereOr('nickname', 'like', '%' . $keyword . '%');
                });
            }
            if ($status !== '') {
                $query->where('status', (int) $status);
            } else {
                $query->where('status', '<>', 6);
            }
            if ($categoryId > 0) {
                $query->where('category_id', $categoryId);
            }
            if ($region !== '') {
                $query->where('region', $region);
            }
            if ($qualityGrade !== '') {
                $query->where('quality_grade', $qualityGrade);
            }
            if ($sourceSystem !== '') {
                if ($sourceSystem === 'manual') {
                    $query->where(function ($sub): void {
                        $sub->whereNull('source_system')
                            ->whereOr('source_system', '')
                            ->whereOr('source_system', 'manual');
                    });
                } else {
                    $query->where('source_system', $sourceSystem);
                }
            }
            $query->limit($limit);
        }

        $rows = $query->field(
            'id,tiktok_id,nickname,region,status,contact_info,last_auto_dm_at,do_not_contact,cooldown_until,quality_score,quality_grade'
        )->select()->toArray();

        return is_array($rows) ? $rows : [];
    }

    private function toggleCampaignStatus(int $nextStatus)
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $campaignId = (int) ($payload['campaign_id'] ?? 0);
        if ($campaignId <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        $query = AutoDmCampaignModel::where('id', $campaignId);
        $query = $this->scopeTenant($query, 'auto_dm_campaigns');
        $row = $query->find();
        if (!$row) {
            return $this->jsonErr('not_found', 404, null, 'common.notFound');
        }
        $update = [
            'campaign_status' => $nextStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        if ($nextStatus === AutoDmService::CAMPAIGN_STATUS_PAUSED) {
            $update['paused_at'] = date('Y-m-d H:i:s');
        } elseif ((int) ($row->campaign_status ?? 0) === AutoDmService::CAMPAIGN_STATUS_PAUSED) {
            $update['paused_at'] = null;
        }
        $row->save($update);

        return $this->jsonOk([
            'campaign_id' => $campaignId,
            'campaign_status' => $nextStatus,
        ], 'updated');
    }

    /**
     * @return array<string, int>
     */
    private function buildSummary(): array
    {
        $tenantId = $this->currentTenantId();
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        $runningCampaigns = Db::name('auto_dm_campaigns')->where('campaign_status', AutoDmService::CAMPAIGN_STATUS_RUNNING);
        if ($this->tableHasTenantId('auto_dm_campaigns')) {
            $runningCampaigns->where('tenant_id', $tenantId);
        }

        $pendingTasks = Db::name('auto_dm_tasks')->whereIn('task_status', [
            AutoDmService::TASK_STATUS_PENDING,
            AutoDmService::TASK_STATUS_ASSIGNED,
            AutoDmService::TASK_STATUS_SENDING,
        ]);
        if ($this->tableHasTenantId('auto_dm_tasks')) {
            $pendingTasks->where('tenant_id', $tenantId);
        }

        $todayEvents = Db::name('auto_dm_events')->whereBetween('created_at', [$todayStart, $todayEnd]);
        if ($this->tableHasTenantId('auto_dm_events')) {
            $todayEvents->where('tenant_id', $tenantId);
        }
        $todaySent = clone $todayEvents;
        $todayFailed = clone $todayEvents;
        $todayBlocked = clone $todayEvents;
        $todayReplyDetected = clone $todayEvents;
        $todayReplyConfirmed = clone $todayEvents;
        $todayReplyStop = clone $todayEvents;
        $replyPendingQuery = Db::name('auto_dm_tasks')->where('reply_state', AutoDmService::REPLY_STATE_DETECTED);
        if ($this->tableHasTenantId('auto_dm_tasks')) {
            $replyPendingQuery->where('tenant_id', $tenantId);
        }

        return [
            'running_campaigns' => (int) $runningCampaigns->count(),
            'pending_tasks' => (int) $pendingTasks->count(),
            'reply_pending' => (int) $replyPendingQuery->count(),
            'today_sent' => (int) $todaySent->where('event_type', AutoDmService::EVENT_SENT)->count(),
            'today_failed' => (int) $todayFailed->where('event_type', AutoDmService::EVENT_FAILED)->count(),
            'today_blocked' => (int) $todayBlocked->whereIn('event_type', [AutoDmService::EVENT_BLOCKED, AutoDmService::EVENT_COOLING])->count(),
            'today_reply_detected' => (int) $todayReplyDetected->where('event_type', AutoDmService::EVENT_REPLY_DETECTED)->count(),
            'today_reply_confirmed' => (int) $todayReplyConfirmed->where('event_type', AutoDmService::EVENT_REPLY_CONFIRMED)->count(),
            'today_unsubscribed' => (int) $todayReplyStop->where('event_type', AutoDmService::EVENT_REPLY_STOP)->count(),
        ];
    }

    /**
     * @param array<int, int> $campaignIds
     * @return array<int, array<string, mixed>>
     */
    private function buildCampaignMetrics(array $campaignIds): array
    {
        $ids = [];
        foreach ($campaignIds as $id) {
            $v = (int) $id;
            if ($v > 0) {
                $ids[] = $v;
            }
        }
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }
        $metrics = [];
        foreach ($ids as $campaignId) {
            $metrics[$campaignId] = [
                'funnel' => [
                    'pending' => 0,
                    'sending' => 0,
                    'sent' => 0,
                    'reply_pending' => 0,
                    'converted' => 0,
                    'blocked' => 0,
                ],
                'reply_pending' => 0,
                'unsubscribe_rate' => 0,
            ];
        }

        $taskQuery = Db::name('auto_dm_tasks')
            ->whereIn('campaign_id', $ids)
            ->field('campaign_id,task_status,reply_state');
        if ($this->tableHasTenantId('auto_dm_tasks')) {
            $taskQuery->where('tenant_id', $this->currentTenantId());
        }
        $taskRows = $taskQuery->select()->toArray();
        foreach ($taskRows as $row) {
            $campaignId = (int) ($row['campaign_id'] ?? 0);
            if (!isset($metrics[$campaignId])) {
                continue;
            }
            $status = (int) ($row['task_status'] ?? -1);
            $replyState = (int) ($row['reply_state'] ?? 0);
            if (in_array($status, [AutoDmService::TASK_STATUS_PENDING, AutoDmService::TASK_STATUS_ASSIGNED], true)) {
                $metrics[$campaignId]['funnel']['pending']++;
            } elseif ($status === AutoDmService::TASK_STATUS_SENDING) {
                $metrics[$campaignId]['funnel']['sending']++;
            } elseif ($status === AutoDmService::TASK_STATUS_SENT) {
                $metrics[$campaignId]['funnel']['sent']++;
            } elseif (in_array($status, [AutoDmService::TASK_STATUS_FAILED, AutoDmService::TASK_STATUS_BLOCKED, AutoDmService::TASK_STATUS_COOLING], true)) {
                $metrics[$campaignId]['funnel']['blocked']++;
            }
            if ($replyState === AutoDmService::REPLY_STATE_DETECTED) {
                $metrics[$campaignId]['funnel']['reply_pending']++;
                $metrics[$campaignId]['reply_pending']++;
            }
        }

        $reviewRows = [];
        try {
            $reviewQuery = Db::name('auto_dm_reply_reviews')
                ->whereIn('campaign_id', $ids)
                ->field('campaign_id,confirm_category');
            if ($this->tableHasTenantId('auto_dm_reply_reviews')) {
                $reviewQuery->where('tenant_id', $this->currentTenantId());
            }
            $reviewRows = $reviewQuery->select()->toArray();
        } catch (\Throwable $e) {
            $reviewRows = [];
        }
        $unsubscribedMap = [];
        foreach ($reviewRows as $row) {
            $campaignId = (int) ($row['campaign_id'] ?? 0);
            if (!isset($metrics[$campaignId])) {
                continue;
            }
            $category = strtolower(trim((string) ($row['confirm_category'] ?? '')));
            if (in_array($category, ['intent', 'inquiry'], true)) {
                $metrics[$campaignId]['funnel']['converted']++;
            } elseif ($category === 'unsubscribe') {
                $unsubscribedMap[$campaignId] = ($unsubscribedMap[$campaignId] ?? 0) + 1;
            }
        }

        foreach ($metrics as $campaignId => $metric) {
            $sent = max(1, (int) ($metric['funnel']['sent'] ?? 0));
            $unsubscribe = (int) ($unsubscribedMap[$campaignId] ?? 0);
            $metrics[$campaignId]['unsubscribe_rate'] = $unsubscribe > 0
                ? round(($unsubscribe / $sent) * 100, 2)
                : 0;
        }

        return $metrics;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function appendEvent(array $payload): void
    {
        $insert = $this->withTenantPayload([
            'campaign_id' => (int) ($payload['campaign_id'] ?? 0),
            'task_id' => isset($payload['task_id']) && (int) $payload['task_id'] > 0 ? (int) $payload['task_id'] : null,
            'device_id' => isset($payload['device_id']) && (int) $payload['device_id'] > 0 ? (int) $payload['device_id'] : null,
            'influencer_id' => isset($payload['influencer_id']) && (int) $payload['influencer_id'] > 0 ? (int) $payload['influencer_id'] : null,
            'event_type' => mb_substr((string) ($payload['event_type'] ?? AutoDmService::EVENT_CREATED), 0, 32),
            'event_status' => (int) ($payload['event_status'] ?? 0),
            'error_code' => isset($payload['error_code']) && $payload['error_code'] !== '' ? mb_substr((string) $payload['error_code'], 0, 64) : null,
            'error_message' => isset($payload['error_message']) && $payload['error_message'] !== '' ? mb_substr((string) $payload['error_message'], 0, 255) : null,
            'duration_ms' => max(0, (int) ($payload['duration_ms'] ?? 0)),
            'screenshot_path' => isset($payload['screenshot_path']) && $payload['screenshot_path'] !== '' ? mb_substr((string) $payload['screenshot_path'], 0, 255) : null,
            'payload_json' => isset($payload['payload_json']) ? (string) $payload['payload_json'] : null,
        ], 'auto_dm_events');
        AutoDmEventModel::create($insert);
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonOrPost(): array
    {
        $raw = (string) $this->request->getContent();
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $post = $this->request->post();
        return is_array($post) ? $post : [];
    }

    private function columnExists(string $table, string $column): bool
    {
        $table = strtolower(trim($table));
        $column = strtolower(trim($column));
        if ($table === '' || $column === '') {
            return false;
        }
        $key = $table . '.' . $column;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }
        try {
            $fields = Db::name($table)->getFields();
            $exists = is_array($fields) && array_key_exists($column, $fields);
        } catch (\Throwable $e) {
            $exists = false;
        }
        self::$columnCache[$key] = $exists;

        return $exists;
    }

    /**
     * @param mixed $raw
     * @return list<int>
     */
    private function normalizeIdList($raw): array
    {
        $ids = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $id = (int) $item;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            $parts = preg_split('/[\s,;]+/', trim($raw)) ?: [];
            foreach ($parts as $part) {
                $id = (int) $part;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }
        $ids = array_values(array_unique($ids));
        if (count($ids) > 5000) {
            $ids = array_slice($ids, 0, 5000);
        }

        return $ids;
    }

    private function canManageAutoDm(): bool
    {
        $role = strtolower(trim((string) AdminAuthService::role()));
        return $role === 'super_admin' || $role === 'operator';
    }
}
