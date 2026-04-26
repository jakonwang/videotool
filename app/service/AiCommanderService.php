<?php
declare(strict_types=1);

namespace app\service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Db;
use think\facade\Log;

/**
 * AI 经营指挥官服务（一期）
 */
class AiCommanderService
{
    /**
     * @var array<string, bool>
     */
    private static array $tableExistsCache = [];

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function chat(int $tenantId, array $payload): array
    {
        $tenantId = max(1, $tenantId);
        $storeId = max(0, (int) ($payload['store_id'] ?? 0));
        $campaignId = self::clean((string) ($payload['campaign_id'] ?? ''), 64);
        $message = trim((string) ($payload['message'] ?? ''));
        $command = self::normalizeCommand((string) ($payload['command'] ?? 'diagnose'));
        $goalGmv = max(0.0, (float) ($payload['goal_gmv'] ?? 0));
        $roiFloor = max(0.0, (float) ($payload['roi_floor'] ?? 0));

        $context = self::collectContext($tenantId, $storeId, $campaignId);
        $core = self::buildCoreDecision($command, $message, $context, $goalGmv, $roiFloor);

        $llm = self::askExternalModel($command, $message, $context, $core);
        if (($llm['ok'] ?? false) === true) {
            $core = self::mergeLlmDecision($core, $llm);
        }

        $sessionId = self::ensureSession($tenantId, $storeId, $command, $message, $core);
        $decisionId = self::persistDecision($tenantId, $sessionId, 'chat', [
            'command' => $command,
            'message' => $message,
            'campaign_id' => $campaignId,
        ], $core);

        return [
            'session_id' => $sessionId,
            'decision_id' => $decisionId,
            'command' => $command,
            'summary' => (string) ($core['summary'] ?? ''),
            'confidence' => (float) ($core['confidence'] ?? 0.5),
            'evidence_refs' => $core['evidence_refs'] ?? [],
            'risk_level' => (string) ($core['risk_level'] ?? 'medium'),
            'requires_human_approval' => (int) ($core['requires_human_approval'] ?? 0),
            'next_actions' => $core['next_actions'] ?? [],
            'analysis' => [
                'problem_position' => (string) ($core['problem_position'] ?? 'multi_stage'),
                'stage' => (string) ($core['stage'] ?? 'cold_start'),
                'store_baseline' => $context['baseline'] ?? [],
                'latest_recommendation' => $context['recommendation'] ?? [],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function generatePlan(int $tenantId, array $payload): array
    {
        $tenantId = max(1, $tenantId);
        $storeId = max(0, (int) ($payload['store_id'] ?? 0));
        $campaignId = self::clean((string) ($payload['campaign_id'] ?? ''), 64);
        $goalGmv = max(0.0, (float) ($payload['goal_gmv'] ?? 0));
        $roiFloor = max(0.0, (float) ($payload['roi_floor'] ?? 0));
        $days = max(7, min(30, (int) ($payload['days'] ?? 14)));
        $message = trim((string) ($payload['message'] ?? '从0到放量日计划'));

        $context = self::collectContext($tenantId, $storeId, $campaignId);
        $decision = self::buildCoreDecision('plan', $message, $context, $goalGmv, $roiFloor);
        $plan = self::buildActionPlan($storeId, $campaignId, $goalGmv, $roiFloor, $days, $decision, $context);

        $sessionId = self::ensureSession($tenantId, $storeId, 'plan', $message, $decision);
        $decisionId = self::persistDecision($tenantId, $sessionId, 'plan_generate', [
            'campaign_id' => $campaignId,
            'goal_gmv' => $goalGmv,
            'roi_floor' => $roiFloor,
            'days' => $days,
        ], $decision);
        $planId = self::persistPlan($tenantId, $sessionId, $decisionId, $plan);

        return [
            'session_id' => $sessionId,
            'decision_id' => $decisionId,
            'plan_id' => $planId,
            'confidence' => (float) ($decision['confidence'] ?? 0.5),
            'evidence_refs' => $decision['evidence_refs'] ?? [],
            'risk_level' => (string) ($decision['risk_level'] ?? 'medium'),
            'requires_human_approval' => (int) ($plan['requires_human_approval'] ?? 0),
            'next_actions' => $plan['tasks'] ?? [],
            'plan' => $plan,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function executePlan(int $tenantId, array $payload): array
    {
        $tenantId = max(1, $tenantId);
        $planId = max(0, (int) ($payload['plan_id'] ?? 0));
        $approved = (int) ($payload['approved'] ?? 0) === 1;
        $notes = trim((string) ($payload['notes'] ?? ''));

        if ($planId <= 0) {
            return ['ok' => false, 'message' => 'invalid_plan_id'];
        }
        if (!self::hasTable('ai_action_plans')) {
            return ['ok' => false, 'message' => 'ai_plan_table_missing'];
        }

        $plan = Db::name('ai_action_plans')
            ->where('tenant_id', $tenantId)
            ->where('id', $planId)
            ->find();
        if (!is_array($plan)) {
            return ['ok' => false, 'message' => 'plan_not_found'];
        }

        $requiresApproval = (int) ($plan['requires_human_approval'] ?? 0) === 1;
        if ($requiresApproval && !$approved) {
            return [
                'ok' => false,
                'message' => 'approval_required',
                'risk_level' => (string) ($plan['risk_level'] ?? 'high'),
                'requires_human_approval' => 1,
            ];
        }

        $planJson = self::decodeJson($plan['plan_json'] ?? null, []);
        $tasks = is_array($planJson['tasks'] ?? null) ? $planJson['tasks'] : [];
        $today = date('Y-m-d');
        $executed = 0;
        foreach ($tasks as &$task) {
            if (!is_array($task)) {
                continue;
            }
            $status = (string) ($task['status'] ?? 'pending');
            if ($status !== 'pending') {
                continue;
            }
            $task['status'] = 'in_progress';
            $task['started_at'] = date('Y-m-d H:i:s');
            ++$executed;
        }
        unset($task);

        $planJson['tasks'] = $tasks;
        $planJson['last_execute_at'] = date('Y-m-d H:i:s');

        Db::name('ai_action_plans')
            ->where('tenant_id', $tenantId)
            ->where('id', $planId)
            ->update([
                'status' => 'running',
                'plan_json' => json_encode($planJson, JSON_UNESCAPED_UNICODE),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        self::persistFeedback($tenantId, (int) ($plan['session_id'] ?? 0), $planId, (int) ($plan['decision_id'] ?? 0), 'execute', [
            'event_date' => $today,
            'executed_tasks' => $executed,
            'notes' => $notes,
            'approved' => $approved ? 1 : 0,
        ]);

        return [
            'ok' => true,
            'plan_id' => $planId,
            'status' => 'running',
            'executed_tasks' => $executed,
            'risk_level' => (string) ($plan['risk_level'] ?? 'medium'),
            'requires_human_approval' => $requiresApproval ? 1 : 0,
            'next_actions' => array_values(array_filter($tasks, static function ($task) {
                return is_array($task) && (string) ($task['status'] ?? '') === 'in_progress';
            })),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function planStatus(int $tenantId, array $payload): array
    {
        $tenantId = max(1, $tenantId);
        $storeId = max(0, (int) ($payload['store_id'] ?? 0));
        $status = trim((string) ($payload['status'] ?? ''));
        $page = max(1, (int) ($payload['page'] ?? 1));
        $pageSize = max(1, min(100, (int) ($payload['page_size'] ?? 20)));

        if (!self::hasTable('ai_action_plans')) {
            return ['items' => [], 'total' => 0, 'page' => $page, 'page_size' => $pageSize];
        }

        $query = Db::name('ai_action_plans')->where('tenant_id', $tenantId)->order('id', 'desc');
        if ($storeId > 0) {
            $query->where('store_id', $storeId);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        $list = $query->paginate(['list_rows' => $pageSize, 'page' => $page]);
        $items = [];
        foreach ($list as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $planJson = self::decodeJson($arr['plan_json'] ?? null, []);
            $tasks = is_array($planJson['tasks'] ?? null) ? $planJson['tasks'] : [];
            $items[] = [
                'id' => (int) ($arr['id'] ?? 0),
                'session_id' => (int) ($arr['session_id'] ?? 0),
                'decision_id' => (int) ($arr['decision_id'] ?? 0),
                'store_id' => (int) ($arr['store_id'] ?? 0),
                'campaign_id' => (string) ($arr['campaign_id'] ?? ''),
                'plan_code' => (string) ($arr['plan_code'] ?? ''),
                'title' => (string) ($arr['title'] ?? ''),
                'objective' => (string) ($arr['objective'] ?? ''),
                'status' => (string) ($arr['status'] ?? 'draft'),
                'risk_level' => (string) ($arr['risk_level'] ?? 'medium'),
                'requires_human_approval' => (int) ($arr['requires_human_approval'] ?? 0),
                'owner_role' => (string) ($arr['owner_role'] ?? 'operator'),
                'due_at' => (string) ($arr['due_at'] ?? ''),
                'expected_kpi' => self::decodeJson($arr['expected_kpi_json'] ?? null, []),
                'task_total' => count($tasks),
                'task_done' => self::countTaskByStatus($tasks, 'done'),
                'task_running' => self::countTaskByStatus($tasks, 'in_progress'),
                'updated_at' => (string) ($arr['updated_at'] ?? ''),
                'created_at' => (string) ($arr['created_at'] ?? ''),
            ];
        }

        return [
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function feedback(int $tenantId, array $payload): array
    {
        $tenantId = max(1, $tenantId);
        $sessionId = max(0, (int) ($payload['session_id'] ?? 0));
        $planId = max(0, (int) ($payload['plan_id'] ?? 0));
        $decisionId = max(0, (int) ($payload['decision_id'] ?? 0));
        $eventType = self::clean((string) ($payload['event_type'] ?? 'result'), 32);
        if ($eventType === '') {
            $eventType = 'result';
        }
        $eventPayload = is_array($payload['event_payload'] ?? null) ? $payload['event_payload'] : [];

        $eventId = self::persistFeedback($tenantId, $sessionId, $planId, $decisionId, $eventType, $eventPayload);

        if ($planId > 0 && self::hasTable('ai_action_plans')) {
            $status = trim((string) ($payload['plan_status'] ?? ''));
            if ($status !== '' && in_array($status, ['running', 'completed', 'paused', 'cancelled'], true)) {
                Db::name('ai_action_plans')
                    ->where('tenant_id', $tenantId)
                    ->where('id', $planId)
                    ->update([
                        'status' => $status,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }
        }

        return [
            'event_id' => $eventId,
            'ok' => true,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function dailyInsight(int $tenantId, array $payload): array
    {
        $tenantId = max(1, $tenantId);
        $storeId = max(0, (int) ($payload['store_id'] ?? 0));
        $campaignId = self::clean((string) ($payload['campaign_id'] ?? ''), 64);

        $context = self::collectContext($tenantId, $storeId, $campaignId);
        $decision = self::buildCoreDecision('diagnose', 'daily insight', $context, 0, 0);

        $todayDo = array_map(static function (array $action): string {
            return (string) ($action['action'] ?? '');
        }, array_slice($decision['next_actions'] ?? [], 0, 4));

        return [
            'snapshot_date' => date('Y-m-d'),
            'store_id' => $storeId,
            'campaign_id' => $campaignId,
            'account_stage' => (string) ($decision['stage'] ?? 'cold_start'),
            'main_problem' => (string) ($decision['problem_position'] ?? 'multi_stage'),
            'today_do' => $todayDo,
            'today_avoid' => self::todayAvoidByRisk((string) ($decision['risk_level'] ?? 'medium'), (string) ($decision['stage'] ?? 'learning')),
            'budget_suggestion' => self::budgetSuggestion($context),
            'roi_suggestion' => self::roiSuggestion($context),
            'confidence' => (float) ($decision['confidence'] ?? 0.5),
            'evidence_refs' => $decision['evidence_refs'] ?? [],
            'risk_level' => (string) ($decision['risk_level'] ?? 'medium'),
            'requires_human_approval' => (int) ($decision['requires_human_approval'] ?? 0),
            'next_actions' => $decision['next_actions'] ?? [],
            'summary' => (string) ($decision['summary'] ?? ''),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function collectContext(int $tenantId, int $storeId, string $campaignId): array
    {
        $context = [
            'store' => [],
            'baseline' => [],
            'recommendation' => [],
            'ranking' => [],
            'profit_7d' => [],
            'live_top' => [],
            'auto_dm' => [],
            'evidence_refs' => [],
        ];

        if ($storeId > 0 && self::hasTable('growth_profit_stores')) {
            $store = Db::name('growth_profit_stores')
                ->where('tenant_id', $tenantId)
                ->where('id', $storeId)
                ->find();
            if (is_array($store)) {
                $context['store'] = [
                    'id' => (int) ($store['id'] ?? 0),
                    'store_name' => (string) ($store['store_name'] ?? ''),
                    'store_code' => (string) ($store['store_code'] ?? ''),
                    'default_gmv_currency' => (string) ($store['default_gmv_currency'] ?? 'VND'),
                ];
                $context['evidence_refs'][] = 'growth_profit_stores#' . (int) ($store['id'] ?? 0);
            }
        }

        try {
            $context['baseline'] = GmvMaxCreativeInsightService::baseline($tenantId, $storeId, 30);
            $context['recommendation'] = GmvMaxCreativeInsightService::recommendation($tenantId, $storeId, $campaignId);
            $ranking = GmvMaxCreativeInsightService::ranking($tenantId, $storeId, date('Y-m-d', strtotime('-14 day')), date('Y-m-d'), 20);
            $context['ranking'] = is_array($ranking['items'] ?? null) ? $ranking['items'] : [];
            $context['evidence_refs'][] = 'gmv_max_store_baselines:window30';
            $context['evidence_refs'][] = 'gmv_max_recommendation_snapshots:latest';
            $context['evidence_refs'][] = 'gmv_max_creative_daily:last14d';
        } catch (\Throwable $e) {
        }

        if (self::hasTable('growth_profit_daily_entries')) {
            try {
                $query = Db::name('growth_profit_daily_entries')
                    ->where('tenant_id', $tenantId)
                    ->where('entry_date', '>=', date('Y-m-d', strtotime('-6 day')))
                    ->where('entry_date', '<=', date('Y-m-d'));
                if ($storeId > 0) {
                    $query->where('store_id', $storeId);
                }
                $row = $query->fieldRaw('COALESCE(SUM(gmv_cny),0) AS gmv, COALESCE(SUM(ad_spend_cny),0) AS ad_spend, COALESCE(SUM(order_count),0) AS orders, COALESCE(SUM(net_profit_cny),0) AS net_profit')->find();
                if (is_array($row)) {
                    $context['profit_7d'] = [
                        'gmv' => round((float) ($row['gmv'] ?? 0), 2),
                        'ad_spend' => round((float) ($row['ad_spend'] ?? 0), 2),
                        'orders' => (int) ($row['orders'] ?? 0),
                        'net_profit' => round((float) ($row['net_profit'] ?? 0), 2),
                    ];
                    $context['evidence_refs'][] = 'growth_profit_daily_entries:last7d';
                }
            } catch (\Throwable $e) {
            }
        }

        if (self::hasTable('growth_live_style_agg')) {
            try {
                $query = Db::name('growth_live_style_agg')
                    ->where('tenant_id', $tenantId)
                    ->where('scope', 'store')
                    ->where('window_type', '7d')
                    ->order('window_end', 'desc')
                    ->order('ranking', 'asc')
                    ->limit(10);
                if ($storeId > 0) {
                    $query->where('store_id', $storeId);
                }
                $rows = $query->select()->toArray();
                $context['live_top'] = $rows;
                if ($rows !== []) {
                    $context['evidence_refs'][] = 'growth_live_style_agg:7d';
                }
            } catch (\Throwable $e) {
            }
        }

        if (self::hasTable('auto_dm_tasks')) {
            try {
                $pending = (int) Db::name('auto_dm_tasks')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('status', ['pending', 'copied', 'processing'])
                    ->count();
                $context['auto_dm'] = ['pending' => $pending];
                $context['evidence_refs'][] = 'auto_dm_tasks:pending';
            } catch (\Throwable $e) {
            }
        }

        $context['evidence_refs'] = array_values(array_unique(array_filter(array_map('strval', $context['evidence_refs']))));
        return $context;
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function buildCoreDecision(string $command, string $message, array $context, float $goalGmv, float $roiFloor): array
    {
        $baseline = is_array($context['baseline'] ?? null) ? $context['baseline'] : [];
        $recommendationWrap = is_array($context['recommendation'] ?? null) ? $context['recommendation'] : [];
        $recommendation = is_array($recommendationWrap['recommendation'] ?? null) ? $recommendationWrap['recommendation'] : [];
        $ranking = is_array($context['ranking'] ?? null) ? $context['ranking'] : [];

        $avgRoi = (float) ($baseline['avg_roi'] ?? 0);
        $avgCtr = (float) ($baseline['avg_ctr'] ?? 0);
        $avgCvr = (float) ($baseline['avg_cvr'] ?? 0);
        $sampleCount = (int) ($baseline['sample_count'] ?? 0);

        $stage = (string) ($recommendation['stage'] ?? 'cold_start');
        if ($stage === '') {
            $stage = $sampleCount >= 60 ? 'scaling' : ($sampleCount >= 20 ? 'learning' : 'cold_start');
        }
        $problem = (string) ($recommendation['main_problem'] ?? '');
        $problemPosition = self::resolveProblemPosition($problem, $avgCtr, $avgCvr, $ranking);

        $riskLevel = self::riskLevel($stage, $problemPosition, $avgRoi, $roiFloor);
        $requiresApproval = in_array($riskLevel, ['high', 'critical'], true) ? 1 : 0;

        $confidence = 0.45;
        if ($sampleCount >= 30) {
            $confidence += 0.2;
        }
        if ($sampleCount >= 80) {
            $confidence += 0.15;
        }
        if (($context['ranking'] ?? []) !== []) {
            $confidence += 0.1;
        }
        $confidence = max(0.2, min(0.95, $confidence));

        return [
            'summary' => self::decisionSummary($command, $problemPosition, $stage, $avgCtr, $avgCvr, $avgRoi, $goalGmv, $roiFloor, $message),
            'confidence' => round($confidence, 4),
            'risk_level' => $riskLevel,
            'requires_human_approval' => $requiresApproval,
            'problem_position' => $problemPosition,
            'stage' => $stage,
            'evidence_refs' => $context['evidence_refs'] ?? [],
            'next_actions' => self::nextActionsByCommand($command, $problemPosition, $stage, $goalGmv, $roiFloor),
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $decision
     * @return array<string,mixed>
     */
    private static function buildActionPlan(int $storeId, string $campaignId, float $goalGmv, float $roiFloor, int $days, array $decision, array $context): array
    {
        $start = new \DateTimeImmutable('today');
        $tasks = [];
        $phases = [
            ['name' => '素材迭代', 'days' => [1, 2], 'owner' => 'creative', 'kpi' => 'CTR +15%'],
            ['name' => '小测验证', 'days' => [3, 4], 'owner' => 'ad_operator', 'kpi' => '首单时间<24h'],
            ['name' => '放量申请', 'days' => [5, 7], 'owner' => 'ad_operator', 'kpi' => '预算逐日+10%-20%'],
            ['name' => '复盘优化', 'days' => [8, $days], 'owner' => 'ops_manager', 'kpi' => 'ROI 稳定不低于阈值'],
        ];
        foreach ($phases as $phase) {
            $due = $start->modify('+' . max(1, (int) $phase['days'][1]) . ' day')->format('Y-m-d');
            $taskRisk = (string) ($decision['risk_level'] ?? 'medium');
            $requiresApproval = ($phase['name'] === '放量申请' || $taskRisk === 'high' || $taskRisk === 'critical') ? 1 : 0;
            $tasks[] = [
                'task_code' => 'AI-' . strtoupper(substr(md5((string) microtime(true) . $phase['name']), 0, 8)),
                'title' => (string) $phase['name'],
                'owner_role' => (string) $phase['owner'],
                'due_date' => $due,
                'status' => 'pending',
                'kpi_target' => (string) $phase['kpi'],
                'requires_human_approval' => $requiresApproval,
                'risk_level' => $taskRisk,
                'guide' => self::taskGuide((string) $phase['name'], (string) ($decision['problem_position'] ?? 'multi_stage')),
            ];
        }

        return [
            'plan_code' => 'PLAN-' . date('Ymd-His') . '-' . strtoupper(substr(md5((string) mt_rand()), 0, 4)),
            'title' => 'GMV Max 从0到放量计划',
            'objective' => $goalGmv > 0 ? ('在 ' . $days . ' 天内冲刺 GMV ' . $goalGmv) : ('在 ' . $days . ' 天内完成冷启动并进入稳定放量'),
            'store_id' => $storeId,
            'campaign_id' => $campaignId,
            'status' => 'draft',
            'risk_level' => (string) ($decision['risk_level'] ?? 'medium'),
            'requires_human_approval' => (int) ($decision['requires_human_approval'] ?? 0),
            'owner_role' => 'operator',
            'due_at' => $start->modify('+' . $days . ' day')->format('Y-m-d 23:59:59'),
            'expected_kpi' => [
                'goal_gmv' => $goalGmv,
                'roi_floor' => $roiFloor,
                'target_ctr' => round(max(1.0, (float) (($context['baseline']['avg_ctr'] ?? 0) * 1.15)), 2),
                'target_cvr' => round(max(1.0, (float) (($context['baseline']['avg_cvr'] ?? 0) * 1.12)), 2),
            ],
            'tasks' => $tasks,
            'playbook' => [
                'today_do' => array_map(static function (array $a): string {
                    return (string) ($a['action'] ?? '');
                }, array_slice($decision['next_actions'] ?? [], 0, 5)),
                'today_avoid' => self::todayAvoidByRisk((string) ($decision['risk_level'] ?? 'medium'), (string) ($decision['stage'] ?? 'learning')),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $core
     * @param array<string,mixed> $llm
     * @return array<string,mixed>
     */
    private static function mergeLlmDecision(array $core, array $llm): array
    {
        if (($llm['summary'] ?? '') !== '') {
            $core['summary'] = (string) $llm['summary'];
        }
        if (is_array($llm['next_actions'] ?? null) && $llm['next_actions'] !== []) {
            $core['next_actions'] = array_slice($llm['next_actions'], 0, 8);
        }
        if (($llm['risk_level'] ?? '') !== '') {
            $core['risk_level'] = self::normalizeRisk((string) $llm['risk_level']);
            $core['requires_human_approval'] = in_array((string) $core['risk_level'], ['high', 'critical'], true) ? 1 : 0;
        }
        if (isset($llm['confidence'])) {
            $core['confidence'] = max(0.2, min(0.99, (float) $llm['confidence']));
        }
        return $core;
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $core
     * @return array<string,mixed>
     */
    private static function askExternalModel(string $command, string $message, array $context, array $core): array
    {
        $cfg = VisionOpenAIConfig::get();
        if (!($cfg['enabled'] ?? false) || trim((string) ($cfg['api_key'] ?? '')) === '') {
            return ['ok' => false, 'message' => 'openai_not_configured'];
        }

        $body = [
            'model' => (string) ($cfg['model'] ?? 'gpt-4o-mini'),
            'temperature' => 0.2,
            'max_tokens' => 900,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是TikTok GMV Max经营指挥官。只输出JSON，不要markdown。输出字段：summary,risk_level,confidence,next_actions。next_actions元素包含action,owner_role,due_date,kpi_target,requires_human_approval,risk_level。',
                ],
                [
                    'role' => 'user',
                    'content' => json_encode([
                        'command' => $command,
                        'message' => $message,
                        'store' => $context['store'] ?? [],
                        'baseline' => $context['baseline'] ?? [],
                        'recommendation' => $context['recommendation'] ?? [],
                        'ranking_top5' => array_slice(is_array($context['ranking'] ?? null) ? $context['ranking'] : [], 0, 5),
                        'core' => [
                            'summary' => $core['summary'] ?? '',
                            'problem_position' => $core['problem_position'] ?? '',
                            'stage' => $core['stage'] ?? '',
                        ],
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ],
        ];

        try {
            $client = new Client([
                'timeout' => (int) ($cfg['timeout_seconds'] ?? 120),
                'connect_timeout' => 12,
                'http_errors' => false,
            ]);
            $res = $client->post(rtrim((string) $cfg['base_url'], '/') . '/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . (string) $cfg['api_key'],
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);
            if ($res->getStatusCode() < 200 || $res->getStatusCode() >= 300) {
                return ['ok' => false, 'message' => 'llm_http_' . $res->getStatusCode()];
            }
            $raw = (string) $res->getBody();
            $dec = json_decode($raw, true);
            if (!is_array($dec)) {
                return ['ok' => false, 'message' => 'llm_invalid_json'];
            }
            $content = self::llmContent($dec);
            if ($content === '') {
                return ['ok' => false, 'message' => 'llm_empty_content'];
            }
            $json = self::extractJsonObject($content);
            if (!is_array($json)) {
                return ['ok' => false, 'message' => 'llm_unparsable'];
            }

            $actions = [];
            if (is_array($json['next_actions'] ?? null)) {
                foreach ($json['next_actions'] as $a) {
                    if (!is_array($a)) {
                        continue;
                    }
                    $actions[] = [
                        'action' => self::clean((string) ($a['action'] ?? ''), 220),
                        'owner_role' => self::normalizeOwnerRole((string) ($a['owner_role'] ?? 'operator')),
                        'due_date' => self::normalizeDate((string) ($a['due_date'] ?? date('Y-m-d'))),
                        'kpi_target' => self::clean((string) ($a['kpi_target'] ?? ''), 120),
                        'requires_human_approval' => (int) ($a['requires_human_approval'] ?? 0) === 1 ? 1 : 0,
                        'risk_level' => self::normalizeRisk((string) ($a['risk_level'] ?? 'medium')),
                    ];
                }
            }

            return [
                'ok' => true,
                'summary' => self::clean((string) ($json['summary'] ?? ''), 1000),
                'risk_level' => self::normalizeRisk((string) ($json['risk_level'] ?? 'medium')),
                'confidence' => max(0.2, min(0.99, (float) ($json['confidence'] ?? 0.6))),
                'next_actions' => $actions,
            ];
        } catch (GuzzleException $e) {
            Log::warning('[ai_center] llm_guzzle ' . $e->getMessage());
            return ['ok' => false, 'message' => 'llm_exception'];
        } catch (\Throwable $e) {
            Log::warning('[ai_center] llm_error ' . $e->getMessage());
            return ['ok' => false, 'message' => 'llm_error'];
        }
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function persistPlan(int $tenantId, int $sessionId, int $decisionId, array $plan): int
    {
        if (!self::hasTable('ai_action_plans')) {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        return (int) Db::name('ai_action_plans')->insertGetId([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'decision_id' => $decisionId,
            'store_id' => max(0, (int) ($plan['store_id'] ?? 0)),
            'campaign_id' => self::clean((string) ($plan['campaign_id'] ?? ''), 64),
            'plan_code' => self::clean((string) ($plan['plan_code'] ?? ''), 64),
            'title' => self::clean((string) ($plan['title'] ?? ''), 120),
            'objective' => self::clean((string) ($plan['objective'] ?? ''), 255),
            'status' => self::clean((string) ($plan['status'] ?? 'draft'), 32),
            'risk_level' => self::normalizeRisk((string) ($plan['risk_level'] ?? 'medium')),
            'requires_human_approval' => (int) ($plan['requires_human_approval'] ?? 0) === 1 ? 1 : 0,
            'owner_role' => self::normalizeOwnerRole((string) ($plan['owner_role'] ?? 'operator')),
            'due_at' => self::normalizeDateTime((string) ($plan['due_at'] ?? '')),
            'expected_kpi_json' => json_encode($plan['expected_kpi'] ?? [], JSON_UNESCAPED_UNICODE),
            'plan_json' => json_encode($plan, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $output
     */
    private static function persistDecision(int $tenantId, int $sessionId, string $decisionType, array $input, array $output): int
    {
        if (!self::hasTable('ai_decisions')) {
            return 0;
        }
        return (int) Db::name('ai_decisions')->insertGetId([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'decision_type' => self::clean($decisionType, 32),
            'input_json' => json_encode($input, JSON_UNESCAPED_UNICODE),
            'output_json' => json_encode($output, JSON_UNESCAPED_UNICODE),
            'confidence' => round((float) ($output['confidence'] ?? 0.5), 4),
            'risk_level' => self::normalizeRisk((string) ($output['risk_level'] ?? 'medium')),
            'evidence_json' => json_encode($output['evidence_refs'] ?? [], JSON_UNESCAPED_UNICODE),
            'requires_human_approval' => (int) ($output['requires_human_approval'] ?? 0) === 1 ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function persistFeedback(int $tenantId, int $sessionId, int $planId, int $decisionId, string $eventType, array $eventPayload): int
    {
        if (!self::hasTable('ai_feedback_events')) {
            return 0;
        }
        return (int) Db::name('ai_feedback_events')->insertGetId([
            'tenant_id' => $tenantId,
            'session_id' => $sessionId,
            'plan_id' => $planId,
            'decision_id' => $decisionId,
            'event_type' => self::clean($eventType, 32),
            'event_payload_json' => json_encode($eventPayload, JSON_UNESCAPED_UNICODE),
            'created_by' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string,mixed> $core
     */
    private static function ensureSession(int $tenantId, int $storeId, string $sessionType, string $lastUserMessage, array $core): int
    {
        if (!self::hasTable('ai_sessions')) {
            return 0;
        }
        $title = mb_substr($lastUserMessage !== '' ? $lastUserMessage : ('AI ' . $sessionType), 0, 120);
        $now = date('Y-m-d H:i:s');
        return (int) Db::name('ai_sessions')->insertGetId([
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'session_type' => self::clean($sessionType, 24),
            'title' => $title,
            'status' => 'active',
            'context_json' => json_encode(['problem_position' => $core['problem_position'] ?? '', 'stage' => $core['stage'] ?? ''], JSON_UNESCAPED_UNICODE),
            'last_user_message' => self::clean($lastUserMessage, 2000),
            'last_ai_message' => self::clean((string) ($core['summary'] ?? ''), 4000),
            'created_by' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param array<int,mixed> $tasks
     */
    private static function countTaskByStatus(array $tasks, string $status): int
    {
        $count = 0;
        foreach ($tasks as $task) {
            if (is_array($task) && (string) ($task['status'] ?? '') === $status) {
                ++$count;
            }
        }
        return $count;
    }

    private static function normalizeCommand(string $command): string
    {
        $v = strtolower(trim($command));
        $map = [
            'diagnose' => 'diagnose',
            '诊断' => 'diagnose',
            'plan' => 'plan',
            '策划' => 'plan',
            'push' => 'push',
            '推进' => 'push',
        ];
        return $map[$v] ?? 'diagnose';
    }

    /**
     * @param array<int,mixed> $ranking
     */
    private static function resolveProblemPosition(string $problem, float $avgCtr, float $avgCvr, array $ranking): string
    {
        $p = strtolower(trim($problem));
        if (in_array($p, ['hook', 'front_3s'], true)) {
            return 'front_3s';
        }
        if (in_array($p, ['retention', 'middle'], true)) {
            return 'middle';
        }
        if (in_array($p, ['conversion', 'conversion_tail'], true)) {
            return 'conversion_tail';
        }

        $avgView75 = 0.0;
        if ($ranking !== []) {
            $sum = 0.0;
            $cnt = 0;
            foreach ($ranking as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $sum += (float) ($item['view_rate_75'] ?? 0);
                ++$cnt;
            }
            if ($cnt > 0) {
                $avgView75 = $sum / $cnt;
            }
        }
        if ($avgCtr < 1.2) {
            return 'front_3s';
        }
        if ($avgView75 > 0 && $avgView75 < 3.2) {
            return 'middle';
        }
        if ($avgCvr < 1.2) {
            return 'conversion_tail';
        }
        return 'multi_stage';
    }

    private static function riskLevel(string $stage, string $problemPosition, float $avgRoi, float $roiFloor): string
    {
        $risk = 'medium';
        if ($avgRoi < 1.0) {
            $risk = 'high';
        }
        if ($roiFloor > 0 && $avgRoi < $roiFloor) {
            $risk = 'high';
        }
        if ($stage === 'cold_start' && $problemPosition === 'multi_stage') {
            $risk = 'high';
        }
        if ($stage === 'stable' && $avgRoi >= max(1.8, $roiFloor)) {
            $risk = 'low';
        }
        return self::normalizeRisk($risk);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function nextActionsByCommand(string $command, string $problemPosition, string $stage, float $goalGmv, float $roiFloor): array
    {
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $after2d = date('Y-m-d', strtotime('+2 day'));
        $actions = [];

        if ($command === 'diagnose') {
            $actions[] = self::makeAction('复核最近24小时素材分段表现，标记前3秒掉量素材并重剪开头', 'creative', $today, 'CTR 提升 >= 15%', 0, $problemPosition === 'front_3s' ? 'high' : 'medium');
            $actions[] = self::makeAction('按店铺历史基准筛选 Top 素材并建立放量白名单', 'ad_operator', $today, '白名单素材 >= 5 条', 0, 'medium');
            $actions[] = self::makeAction('对低转化素材补充价格锚点/信任背书并A/B测试', 'creative', $tomorrow, 'CVR 提升 >= 12%', 0, $problemPosition === 'conversion_tail' ? 'high' : 'medium');
            $actions[] = self::makeAction('检查预算与ROI设置，避免大幅波动触发模型扰动', 'ad_operator', $today, '单日预算变动 <= 20%', 1, 'high');
            $actions[] = self::makeAction('晚间生成偏差复盘，沉淀保留动作与止损动作', 'ops_manager', $today, '次日计划可执行率 100%', 0, 'medium');
        } elseif ($command === 'plan') {
            $actions[] = self::makeAction('准备 8-12 条素材（3个钩子版本 + 2个场景版本）', 'creative', $today, '可投素材 >= 8', 0, 'medium');
            $actions[] = self::makeAction('按 CPA 的 10-20 倍设预算，ROI 从保本线启动', 'ad_operator', $today, '完成首轮计划上线', 1, 'high');
            $actions[] = self::makeAction('第2天按分段评分淘汰垃圾素材，保留优秀和观察素材', 'ad_operator', $tomorrow, '垃圾素材淘汰率 >= 30%', 0, 'medium');
            $actions[] = self::makeAction('第4-7天分层加预算，单次不超过20%', 'ad_operator', $after2d, '放量计划连续 3 天稳定', 1, 'high');
            if ($goalGmv > 0) {
                $actions[] = self::makeAction('按周目标倒推日GMV，并同步线下与达人协同动作', 'ops_manager', $today, '日GMV目标达成率 >= 85%', 0, 'medium');
            }
        } else {
            $actions[] = self::makeAction('将计划拆分给素材、投放、线下三角色并设截止时间', 'ops_manager', $today, '任务分派完成率 100%', 0, 'medium');
            $actions[] = self::makeAction('高风险动作进入人工审批队列（预算、ROI 大幅调整）', 'super_admin', $today, '高风险动作审批覆盖率 100%', 1, 'high');
            $actions[] = self::makeAction('自动催办逾期任务并升级提醒负责人', 'ops_manager', $tomorrow, '逾期任务下降 30%', 0, 'medium');
            $actions[] = self::makeAction('收集执行反馈并写入次日策略继承池', 'ad_operator', $tomorrow, '有效动作继承率 >= 70%', 0, 'medium');
        }

        if ($stage === 'cold_start') {
            $actions[] = self::makeAction('先集中获取 20-30 单训练数据，避免频繁换品', 'ad_operator', $after2d, '训练样本达标', 0, 'medium');
        }
        if ($roiFloor > 0) {
            $actions[] = self::makeAction('设置 ROI 下限监控，低于阈值自动降级为建议模式', 'ops_manager', $today, 'ROI 下限 ' . $roiFloor . ' 生效', 1, 'high');
        }
        return array_slice($actions, 0, 8);
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function budgetSuggestion(array $context): array
    {
        $baseline = is_array($context['baseline'] ?? null) ? $context['baseline'] : [];
        $orders = (int) ($baseline['total_orders'] ?? 0);
        $cost = (float) ($baseline['total_cost'] ?? 0);
        $avgCpa = $orders > 0 ? $cost / $orders : 0;
        return [
            'avg_cpa' => round($avgCpa, 2),
            'recommended_daily_budget' => $avgCpa > 0 ? round($avgCpa * 15, 2) : 0.0,
            'rule' => '预算≈CPA的10-20倍，稳定后小步上调',
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private static function roiSuggestion(array $context): array
    {
        $baseline = is_array($context['baseline'] ?? null) ? $context['baseline'] : [];
        $avgRoi = (float) ($baseline['avg_roi'] ?? 0);
        return [
            'current_avg_roi' => round($avgRoi, 3),
            'recommended_next' => $avgRoi > 0 ? round($avgRoi + 0.3, 3) : 0,
            'rule' => '在保本基础上每次+0.3，直到出现掉量再回调',
        ];
    }

    /**
     * @return array<int,string>
     */
    private static function todayAvoidByRisk(string $riskLevel, string $stage): array
    {
        $items = [
            '不要在学习期频繁换品',
            '不要在同一天内大幅改预算和ROI',
            '不要让低转化素材持续消耗',
        ];
        if (in_array($riskLevel, ['high', 'critical'], true)) {
            $items[] = '高风险动作未审批前不得执行';
        }
        if ($stage === 'cold_start') {
            $items[] = '冷启动阶段不要一次性并发过多变量';
        }
        return $items;
    }

    private static function decisionSummary(string $command, string $problemPosition, string $stage, float $avgCtr, float $avgCvr, float $avgRoi, float $goalGmv, float $roiFloor, string $message): string
    {
        $goalText = $goalGmv > 0 ? ('，目标GMV ' . $goalGmv) : '';
        $roiText = $roiFloor > 0 ? ('，ROI下限 ' . $roiFloor) : '';
        $base = '当前阶段[' . $stage . ']，主要问题在[' . $problemPosition . ']，历史均值 CTR=' . round($avgCtr, 2) . '% CVR=' . round($avgCvr, 2) . '% ROI=' . round($avgRoi, 2);
        if ($command === 'plan') {
            return $base . '。已生成从冷启动到放量的分部门执行计划' . $goalText . $roiText . '。';
        }
        if ($command === 'push') {
            return $base . '。已把动作拆解为可执行任务流，支持审批与催办闭环。';
        }
        if ($message !== '') {
            return $base . '。针对你的问题「' . mb_substr($message, 0, 60) . '」已给出可执行诊断动作。';
        }
        return $base . '。建议先优化素材，再做预算与ROI小步调整。';
    }

    private static function taskGuide(string $phase, string $problemPosition): string
    {
        if ($phase === '素材迭代') {
            if ($problemPosition === 'front_3s') {
                return '先改首帧和前3秒节奏，强调利益点与价格锚点。';
            }
            if ($problemPosition === 'middle') {
                return '补充佩戴对比与场景证据，提升6秒与25%留存。';
            }
            return '补转化段信任背书、口播CTA与促销结构。';
        }
        if ($phase === '放量申请') {
            return '只对连续稳定素材提预算，单次不超过20%，并保留回撤阈值。';
        }
        return '按阶段目标执行并记录结果，次日复盘继承有效动作。';
    }

    /**
     * @return array<string,mixed>
     */
    private static function makeAction(string $action, string $ownerRole, string $dueDate, string $kpiTarget, int $requiresApproval, string $riskLevel): array
    {
        return [
            'action' => $action,
            'owner_role' => self::normalizeOwnerRole($ownerRole),
            'due_date' => $dueDate,
            'kpi_target' => $kpiTarget,
            'requires_human_approval' => $requiresApproval === 1 ? 1 : 0,
            'risk_level' => self::normalizeRisk($riskLevel),
            'status' => 'pending',
        ];
    }

    private static function normalizeOwnerRole(string $role): string
    {
        $v = strtolower(trim($role));
        $allow = ['super_admin', 'operator', 'viewer', 'creative', 'ad_operator', 'offline_operator', 'ops_manager'];
        return in_array($v, $allow, true) ? $v : 'operator';
    }

    private static function normalizeRisk(string $risk): string
    {
        $v = strtolower(trim($risk));
        if (!in_array($v, ['low', 'medium', 'high', 'critical'], true)) {
            return 'medium';
        }
        return $v;
    }

    private static function clean(string $value, int $maxLen): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }
        if (mb_strlen($v) > $maxLen) {
            return mb_substr($v, 0, $maxLen);
        }
        return $v;
    }

    private static function normalizeDate(string $v): string
    {
        $v = trim($v);
        if ($v === '') {
            return date('Y-m-d');
        }
        $ts = strtotime($v);
        if ($ts === false) {
            return date('Y-m-d');
        }
        return date('Y-m-d', $ts);
    }

    private static function normalizeDateTime(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        $ts = strtotime($v);
        if ($ts === false) {
            return null;
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private static function hasTable(string $table): bool
    {
        if (array_key_exists($table, self::$tableExistsCache)) {
            return self::$tableExistsCache[$table];
        }
        try {
            Db::name($table)->where('id', 0)->find();
            self::$tableExistsCache[$table] = true;
        } catch (\Throwable $e) {
            self::$tableExistsCache[$table] = false;
        }
        return self::$tableExistsCache[$table];
    }

    /**
     * @param mixed $raw
     * @param array<string,mixed> $default
     * @return array<string,mixed>
     */
    private static function decodeJson($raw, array $default): array
    {
        if (is_array($raw)) {
            return $raw;
        }
        if (!is_string($raw) || trim($raw) === '') {
            return $default;
        }
        $dec = json_decode($raw, true);
        return is_array($dec) ? $dec : $default;
    }

    /**
     * @param array<string,mixed> $response
     */
    private static function llmContent(array $response): string
    {
        $choices = $response['choices'] ?? null;
        if (!is_array($choices) || !isset($choices[0]) || !is_array($choices[0])) {
            return '';
        }
        $message = $choices[0]['message'] ?? null;
        if (!is_array($message)) {
            return '';
        }
        $content = $message['content'] ?? '';
        if (is_string($content)) {
            return trim($content);
        }
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_array($part) && isset($part['text'])) {
                    $parts[] = (string) $part['text'];
                }
            }
            return trim(implode('', $parts));
        }
        return '';
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function extractJsonObject(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $candidate = $text;
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $candidate = (string) ($m[0] ?? '');
        }
        if ($candidate === '') {
            return null;
        }
        $dec = json_decode($candidate, true);
        return is_array($dec) ? $dec : null;
    }
}

