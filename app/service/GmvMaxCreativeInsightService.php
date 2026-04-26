<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class GmvMaxCreativeInsightService
{
    private const WINDOWS = [7, 14, 30, 0];

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public static function sync(int $tenantId, array $payload): array
    {
        $tenantId = max(1, $tenantId);
        $storeId = self::resolveStoreId($tenantId, $payload['store_id'] ?? ($payload['store_name'] ?? ''));
        if ($storeId <= 0) {
            return ['ok' => false, 'message' => 'store_not_found'];
        }

        $rows = $payload['rows'] ?? [];
        if (!is_array($rows) || $rows === []) {
            return ['ok' => false, 'message' => 'empty_rows'];
        }

        $metricDate = self::normalizeDate((string) ($payload['metric_date'] ?? ($payload['date'] ?? '')));
        if ($metricDate === '') {
            $metricDate = self::resolveDateFromRange((string) ($payload['date_range'] ?? ''));
        }
        if ($metricDate === '') {
            $metricDate = date('Y-m-d');
        }

        $campaignId = self::cleanText($payload['campaign_id'] ?? '', 64);
        if ($campaignId === '') {
            $campaignId = 'unknown_campaign';
        }
        $campaignName = self::cleanText($payload['campaign_name'] ?? '', 255);
        $dateRange = self::cleanText($payload['date_range'] ?? '', 64);
        $sourcePage = self::cleanText($payload['source_page'] ?? '', 500);

        $saved = 0;
        $failed = 0;
        $items = [];
        foreach ($rows as $idx => $row) {
            if (!is_array($row)) {
                ++$failed;
                continue;
            }
            $normalized = self::normalizeRow($tenantId, $storeId, $campaignId, $campaignName, $metricDate, $dateRange, $sourcePage, $row);
            if (!($normalized['ok'] ?? false)) {
                ++$failed;
                $items[] = ['index' => $idx, 'ok' => false, 'message' => (string) ($normalized['message'] ?? 'invalid_row')];
                continue;
            }
            $data = $normalized['data'];
            $existing = Db::name('gmv_max_creative_daily')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('campaign_id', $campaignId)
                ->where('metric_date', $metricDate)
                ->where('video_id', $data['video_id'])
                ->find();
            if (is_array($existing)) {
                $data['updated_at'] = date('Y-m-d H:i:s');
                Db::name('gmv_max_creative_daily')
                    ->where('id', (int) ($existing['id'] ?? 0))
                    ->where('tenant_id', $tenantId)
                    ->update($data);
                $id = (int) ($existing['id'] ?? 0);
            } else {
                $data['created_at'] = date('Y-m-d H:i:s');
                $data['updated_at'] = date('Y-m-d H:i:s');
                $id = (int) Db::name('gmv_max_creative_daily')->insertGetId($data);
            }
            ++$saved;
            $items[] = [
                'index' => $idx,
                'ok' => true,
                'id' => $id,
                'video_id' => (string) $data['video_id'],
                'material_type' => (string) ($data['material_type'] ?? ''),
            ];
        }

        $baseline = self::rebuildBaselines($tenantId, $storeId, $metricDate);
        $currentRows = self::loadDailyRows($tenantId, $storeId, $campaignId, $metricDate, 500);
        $recommendation = self::buildRecommendation($tenantId, $storeId, $campaignId, $campaignName, $metricDate, $currentRows, $baseline, $payload);
        self::saveRecommendationSnapshot($tenantId, $storeId, $campaignId, $campaignName, $metricDate, $recommendation);

        return [
            'ok' => true,
            'store_id' => $storeId,
            'campaign_id' => $campaignId,
            'metric_date' => $metricDate,
            'total_rows' => count($rows),
            'saved_count' => $saved,
            'failed_count' => $failed,
            'items' => array_slice($items, 0, 200),
            'baseline' => $baseline,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function baseline(int $tenantId, int $storeId, int $windowDays = 30): array
    {
        $tenantId = max(1, $tenantId);
        $storeId = max(0, $storeId);
        $windowDays = in_array($windowDays, self::WINDOWS, true) ? $windowDays : 30;
        $query = Db::name('gmv_max_store_baselines')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('window_days', $windowDays)
            ->order('metric_date', 'desc');
        $row = $query->find();
        if (!is_array($row)) {
            return ['baseline_mode' => 'regional_default', 'sample_level' => 'insufficient'];
        }
        return self::formatBaselineRow($row);
    }

    /**
     * @return array<string,mixed>
     */
    public static function recommendation(int $tenantId, int $storeId, string $campaignId = ''): array
    {
        $query = Db::name('gmv_max_recommendation_snapshots')
            ->where('tenant_id', max(1, $tenantId))
            ->where('store_id', max(0, $storeId))
            ->order('snapshot_date', 'desc')
            ->order('id', 'desc');
        if (trim($campaignId) !== '') {
            $query->where('campaign_id', trim($campaignId));
        }
        $row = $query->find();
        if (!is_array($row)) {
            return ['items' => [], 'recommendation' => null];
        }
        $rec = self::decodeJson($row['recommendation_json'] ?? null, []);
        return [
            'id' => (int) ($row['id'] ?? 0),
            'store_id' => (int) ($row['store_id'] ?? 0),
            'campaign_id' => (string) ($row['campaign_id'] ?? ''),
            'campaign_name' => (string) ($row['campaign_name'] ?? ''),
            'snapshot_date' => (string) ($row['snapshot_date'] ?? ''),
            'recommendation' => $rec,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function history(int $tenantId, int $storeId, string $campaignId = '', string $dateFrom = '', string $dateTo = '', int $page = 1, int $pageSize = 50): array
    {
        $query = Db::name('gmv_max_creative_daily')
            ->where('tenant_id', max(1, $tenantId))
            ->order('metric_date', 'desc')
            ->order('id', 'desc');
        if ($storeId > 0) {
            $query->where('store_id', $storeId);
        }
        if (trim($campaignId) !== '') {
            $query->where('campaign_id', trim($campaignId));
        }
        $from = self::normalizeDate($dateFrom);
        $to = self::normalizeDate($dateTo);
        if ($from !== '') {
            $query->where('metric_date', '>=', $from);
        }
        if ($to !== '') {
            $query->where('metric_date', '<=', $to);
        }
        $pageSize = max(1, min(200, $pageSize));
        $list = $query->paginate(['list_rows' => $pageSize, 'page' => max(1, $page)]);
        $items = [];
        foreach ($list as $row) {
            $items[] = self::formatDailyRow(is_array($row) ? $row : $row->toArray());
        }
        return [
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function ranking(int $tenantId, int $storeId, string $dateFrom = '', string $dateTo = '', int $limit = 50): array
    {
        $query = Db::name('gmv_max_creative_daily')
            ->where('tenant_id', max(1, $tenantId));
        if ($storeId > 0) {
            $query->where('store_id', $storeId);
        }
        $from = self::normalizeDate($dateFrom);
        $to = self::normalizeDate($dateTo);
        if ($from !== '') {
            $query->where('metric_date', '>=', $from);
        }
        if ($to !== '') {
            $query->where('metric_date', '<=', $to);
        }
        $rows = $query->fieldRaw('
            video_id,
            MAX(title) AS title,
            MAX(tiktok_account) AS tiktok_account,
            SUM(cost) AS cost,
            SUM(sku_orders) AS sku_orders,
            SUM(gross_revenue) AS gross_revenue,
            AVG(roi) AS roi,
            AVG(product_ad_click_rate) AS product_ad_click_rate,
            AVG(ad_conversion_rate) AS ad_conversion_rate,
            AVG(view_rate_2s) AS view_rate_2s,
            AVG(view_rate_75) AS view_rate_75,
            MAX(material_type) AS material_type,
            COUNT(*) AS days
        ')
            ->group('video_id')
            ->orderRaw('SUM(gross_revenue) DESC, SUM(sku_orders) DESC, AVG(roi) DESC')
            ->limit(max(1, min(200, $limit)))
            ->select()
            ->toArray();
        return ['items' => array_map(static function ($row) {
            return [
                'video_id' => (string) ($row['video_id'] ?? ''),
                'title' => (string) ($row['title'] ?? ''),
                'tiktok_account' => (string) ($row['tiktok_account'] ?? ''),
                'cost' => round((float) ($row['cost'] ?? 0), 4),
                'sku_orders' => (int) ($row['sku_orders'] ?? 0),
                'gross_revenue' => round((float) ($row['gross_revenue'] ?? 0), 4),
                'roi' => round((float) ($row['roi'] ?? 0), 4),
                'ctr' => round((float) ($row['product_ad_click_rate'] ?? 0), 4),
                'cvr' => round((float) ($row['ad_conversion_rate'] ?? 0), 4),
                'view_rate_2s' => round((float) ($row['view_rate_2s'] ?? 0), 4),
                'view_rate_75' => round((float) ($row['view_rate_75'] ?? 0), 4),
                'material_type' => (string) ($row['material_type'] ?? ''),
                'days' => (int) ($row['days'] ?? 0),
            ];
        }, $rows)];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    private static function buildRecommendation(
        int $tenantId,
        int $storeId,
        string $campaignId,
        string $campaignName,
        string $metricDate,
        array $rows,
        array $baseline,
        array $payload
    ): array {
        $stats = self::aggregateRows($rows);
        $sampleCount = (int) ($baseline['sample_count'] ?? 0);
        $baselineMode = $sampleCount >= 30 ? 'store_history' : 'regional_default';
        $sampleLevel = $sampleCount >= 100 ? 'strong' : ($sampleCount >= 30 ? 'usable' : 'insufficient');
        $p70 = is_array($baseline['p70'] ?? null) ? $baseline['p70'] : [];
        $targetRoi = self::toFloat($payload['target_roi'] ?? null);
        $campaignBudget = self::toFloat($payload['campaign_budget'] ?? null);

        $stage = self::resolveStage($stats, $sampleLevel);
        $mainProblem = self::resolveMainProblem($stats, $p70);
        $actionLevel = self::resolveActionLevel($stats, $targetRoi);
        $trend = self::buildRecentTrend($tenantId, $storeId, $campaignId, $metricDate);

        $scaleRows = array_values(array_filter($rows, static function ($row) use ($targetRoi, $p70) {
            $roi = (float) ($row['roi'] ?? 0);
            $orders = (int) ($row['sku_orders'] ?? 0);
            $cvr = (float) ($row['ad_conversion_rate'] ?? 0);
            $roiGate = $targetRoi > 0 ? $targetRoi : (float) ($p70['roi'] ?? 1.8);
            return $orders >= 3 && $roi >= max(1.5, $roiGate * 0.9) && $cvr >= 1.5;
        }));
        $garbageRows = array_values(array_filter($rows, static function ($row) {
            return (string) ($row['material_type'] ?? '') === 'bad'
                || ((float) ($row['cost'] ?? 0) >= 1.2 && (int) ($row['sku_orders'] ?? 0) <= 0);
        }));

        $todayDo = [];
        $todayAvoid = [];
        $creativeAdvice = [];

        if ($mainProblem === 'hook') {
            $todayDo[] = '优先重做前3秒：首帧用佩戴近景、价格锚点和强利益字幕，先把CTR拉回店铺基准。';
            $creativeAdvice[] = '新增3条钩子变体：痛点开场、效果对比开场、价格优惠开场，每条只改前3秒测试。';
        } elseif ($mainProblem === 'conversion') {
            $todayDo[] = '先补转化段：尾段加入买家评价、活动价、售后承诺和明确CTA，不建议只加预算。';
            $creativeAdvice[] = '新增2条成交结构变体：佩戴证明+价格锚点+下单路径，目标提升CVR和ROI。';
        } elseif ($mainProblem === 'retention') {
            $todayDo[] = '优化中段留存：增加佩戴前后对比、多场景切换和细节特写，减少重复空镜。';
            $creativeAdvice[] = '每3-4秒一个信息点，按材质、舒适度、搭配场景重剪中段。';
        } elseif ($actionLevel === 'scale') {
            $todayDo[] = '保留放量素材并小步加预算，优先复制同款结构做新素材，不要一次性大幅改动模型。';
            $creativeAdvice[] = '围绕当前放量素材做5条轻改版：换首帧、换达人、换场景、换价格表达、换CTA。';
        } else {
            $todayDo[] = '继续收集有效样本，先保证每条素材有足够曝光和点击，再判断是否放量或淘汰。';
        }

        if (count($garbageRows) > 0) {
            $todayDo[] = '排除或停止高消耗零订单/低转化素材，减少预算被垃圾素材吃掉。';
        }
        if ($stage === 'cold_start') {
            $todayAvoid[] = '冷启动阶段不要频繁换品或大幅调整ROI，先让账户积累点击和转化信号。';
        }
        if ($actionLevel !== 'scale') {
            $todayAvoid[] = '当前不建议盲目加预算，先解决素材点击或转化短板。';
        }

        $budgetAdvice = '样本不足时保持小预算测试，优先让素材跑出点击和首单。';
        if ($actionLevel === 'scale') {
            $budgetAdvice = $campaignBudget > 0
                ? '建议预算从 ' . round($campaignBudget, 2) . ' 小步增加到 ' . round($campaignBudget * 1.2, 2) . '-' . round($campaignBudget * 1.3, 2) . '，观察24小时ROI是否守住。'
                : '建议对放量素材预算小步递增20%-30%，不要一次性翻倍。';
        } elseif (count($garbageRows) > 0) {
            $budgetAdvice = '先回收垃圾素材预算，不增加总预算，把预算集中给观察/放量素材。';
        }

        $roiAdvice = '未填写目标ROI时，只给方向建议：ROI稳定高于店铺基准可小幅降ROI抢量，低于基准先优化素材。';
        if ($targetRoi > 0) {
            if ($actionLevel === 'scale') {
                $roiAdvice = '当前可在保本基础上小幅降低或维持目标ROI抢量；若掉量，每次只调整0.3。';
            } elseif ($stats['avg_roi'] > 0 && $stats['avg_roi'] < $targetRoi) {
                $roiAdvice = '当前ROI低于目标ROI，不建议降ROI抢量，先优化素材转化段。';
            } else {
                $roiAdvice = 'ROI未形成稳定优势，保持目标ROI并继续测试素材。';
            }
        }

        $scaleIds = array_values(array_unique(array_filter(array_map(static function ($row) {
            return (string) ($row['video_id'] ?? '');
        }, $scaleRows))));
        $excludeIds = array_values(array_unique(array_filter(array_map(static function ($row) {
            return (string) ($row['video_id'] ?? '');
        }, $garbageRows))));
        $fatigueAlert = self::buildFatigueAlert($stats, $trend, $rows);
        $scaleGuard = self::buildScaleGuard($stats, $trend, $targetRoi, $actionLevel);
        $budgetSplit = self::buildBudgetSplit($scaleGuard, $stats);

        return [
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignName,
            'snapshot_date' => $metricDate,
            'baseline_mode' => $baselineMode,
            'sample_level' => $sampleLevel,
            'stage' => $stage,
            'main_problem' => $mainProblem,
            'action_level' => $actionLevel,
            'stats' => $stats,
            'baseline' => $baseline,
            'today_do' => array_values(array_unique($todayDo)),
            'today_avoid' => array_values(array_unique($todayAvoid)),
            'budget_advice' => $budgetAdvice,
            'roi_advice' => $roiAdvice,
            'creative_advice' => array_values(array_unique($creativeAdvice)),
            'exclude_video_ids' => $excludeIds,
            'scale_video_ids' => $scaleIds,
            'fatigue_alert' => $fatigueAlert,
            'scale_guard' => $scaleGuard,
            'budget_split' => $budgetSplit,
            'trend' => $trend,
            'core_conclusion' => self::conclusion($stage, $mainProblem, $actionLevel),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function buildRecentTrend(int $tenantId, int $storeId, string $campaignId, string $metricDate): array
    {
        $query = Db::name('gmv_max_creative_daily')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('metric_date', '<=', $metricDate)
            ->where('metric_date', '>=', date('Y-m-d', strtotime($metricDate . ' -6 days')))
            ->where('material_type', '<>', 'ignore');
        if ($campaignId !== '') {
            $query->where('campaign_id', $campaignId);
        }
        $rows = $query->fieldRaw('
            metric_date,
            SUM(cost) AS total_cost,
            SUM(sku_orders) AS total_orders,
            SUM(gross_revenue) AS total_revenue,
            SUM(product_ad_impressions) AS total_impressions,
            AVG(roi) AS avg_roi,
            AVG(product_ad_click_rate) AS avg_ctr,
            AVG(ad_conversion_rate) AS avg_cvr
        ')
            ->group('metric_date')
            ->order('metric_date', 'asc')
            ->select()
            ->toArray();
        $series = array_map(static function ($row) {
            $impressions = max(0.0, (float) ($row['total_impressions'] ?? 0));
            $cost = (float) ($row['total_cost'] ?? 0);
            $cpm = $impressions > 0 ? ($cost * 1000.0 / $impressions) : 0.0;
            return [
                'metric_date' => (string) ($row['metric_date'] ?? ''),
                'total_cost' => round($cost, 4),
                'total_orders' => (int) ($row['total_orders'] ?? 0),
                'total_revenue' => round((float) ($row['total_revenue'] ?? 0), 4),
                'avg_roi' => round((float) ($row['avg_roi'] ?? 0), 4),
                'avg_ctr' => round((float) ($row['avg_ctr'] ?? 0), 4),
                'avg_cvr' => round((float) ($row['avg_cvr'] ?? 0), 4),
                'avg_cpm' => round($cpm, 4),
            ];
        }, $rows);

        $recent = array_slice($series, -3);
        $previous = array_slice($series, -6, 3);

        $avgField = static function (array $items, string $key): float {
            if ($items === []) {
                return 0.0;
            }
            $sum = array_reduce($items, static function ($carry, $row) use ($key) {
                return $carry + (float) ($row[$key] ?? 0);
            }, 0.0);
            return $sum / max(1, count($items));
        };

        $recentAvg = [
            'roi' => round($avgField($recent, 'avg_roi'), 4),
            'ctr' => round($avgField($recent, 'avg_ctr'), 4),
            'cvr' => round($avgField($recent, 'avg_cvr'), 4),
            'cpm' => round($avgField($recent, 'avg_cpm'), 4),
            'orders' => round($avgField($recent, 'total_orders'), 4),
        ];
        $previousAvg = [
            'roi' => round($avgField($previous, 'avg_roi'), 4),
            'ctr' => round($avgField($previous, 'avg_ctr'), 4),
            'cvr' => round($avgField($previous, 'avg_cvr'), 4),
            'cpm' => round($avgField($previous, 'avg_cpm'), 4),
            'orders' => round($avgField($previous, 'total_orders'), 4),
        ];
        $changePct = static function (float $newValue, float $oldValue): float {
            if (abs($oldValue) < 0.0001) {
                return 0.0;
            }
            return round((($newValue - $oldValue) / $oldValue) * 100.0, 2);
        };

        return [
            'days' => count($series),
            'series' => $series,
            'recent_avg' => $recentAvg,
            'previous_avg' => $previousAvg,
            'delta_pct' => [
                'roi' => $changePct((float) $recentAvg['roi'], (float) $previousAvg['roi']),
                'ctr' => $changePct((float) $recentAvg['ctr'], (float) $previousAvg['ctr']),
                'cvr' => $changePct((float) $recentAvg['cvr'], (float) $previousAvg['cvr']),
                'cpm' => $changePct((float) $recentAvg['cpm'], (float) $previousAvg['cpm']),
                'orders' => $changePct((float) $recentAvg['orders'], (float) $previousAvg['orders']),
            ],
        ];
    }

    /**
     * @param array<string,mixed> $stats
     * @param array<string,mixed> $trend
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function buildFatigueAlert(array $stats, array $trend, array $rows): array
    {
        $delta = is_array($trend['delta_pct'] ?? null) ? $trend['delta_pct'] : [];
        $signals = [];
        if ((float) ($delta['ctr'] ?? 0) <= -20) {
            $signals[] = 'ctr_drop_20';
        }
        if ((float) ($delta['cvr'] ?? 0) <= -20) {
            $signals[] = 'cvr_drop_20';
        }
        if ((float) ($delta['roi'] ?? 0) <= -20) {
            $signals[] = 'roi_drop_20';
        }
        if ((float) ($delta['cpm'] ?? 0) >= 18) {
            $signals[] = 'cpm_rise_18';
        }

        $level = 'normal';
        if (count($signals) >= 3) {
            $level = 'high';
        } elseif (count($signals) >= 2) {
            $level = 'medium';
        } elseif (count($signals) === 1) {
            $level = 'low';
        }

        $affected = array_values(array_filter(array_map(static function ($row) {
            $cost = (float) ($row['cost'] ?? 0);
            $roi = (float) ($row['roi'] ?? 0);
            $orders = (int) ($row['sku_orders'] ?? 0);
            if ($cost >= 1.5 && ($roi < 1.0 || $orders <= 0)) {
                return (string) ($row['video_id'] ?? '');
            }
            return '';
        }, $rows)));

        $summary = '暂无明显疲劳信号，继续观察素材变化。';
        if ($level === 'high') {
            $summary = '疲劳风险高：近期点击/转化/ROI明显走弱，同时竞价成本上升，建议立即补新素材并回收低效预算。';
        } elseif ($level === 'medium') {
            $summary = '疲劳风险中等：核心指标出现下滑，建议优先替换前3秒和成交段并限制加预算。';
        } elseif ($level === 'low') {
            $summary = '出现早期疲劳信号：建议开始上新素材，避免后续掉量。';
        }

        return [
            'level' => $level,
            'signals' => $signals,
            'summary' => $summary,
            'affected_video_ids' => array_slice(array_values(array_unique($affected)), 0, 30),
            'garbage_count' => (int) ($stats['garbage_count'] ?? 0),
        ];
    }

    /**
     * @param array<string,mixed> $stats
     * @param array<string,mixed> $trend
     * @return array<string,mixed>
     */
    private static function buildScaleGuard(array $stats, array $trend, float $targetRoi, string $actionLevel): array
    {
        $delta = is_array($trend['delta_pct'] ?? null) ? $trend['delta_pct'] : [];
        $roiGate = $targetRoi > 0 ? max(1.0, $targetRoi * 0.9) : 1.6;
        $evaluated = max(1, (int) ($stats['evaluated_count'] ?? 0));
        $garbageRatio = ((int) ($stats['garbage_count'] ?? 0)) / $evaluated;

        $checks = [
            'roi_safe' => (float) ($stats['avg_roi'] ?? 0) >= $roiGate,
            'order_safe' => (int) ($stats['total_orders'] ?? 0) >= 3,
            'fatigue_safe' => (float) ($delta['roi'] ?? 0) > -20 && (float) ($delta['ctr'] ?? 0) > -20,
            'garbage_safe' => $garbageRatio <= 0.35,
            'action_safe' => $actionLevel !== 'stop_loss',
        ];

        $blockers = [];
        if (!$checks['roi_safe']) {
            $blockers[] = 'roi_not_safe';
        }
        if (!$checks['order_safe']) {
            $blockers[] = 'orders_not_enough';
        }
        if (!$checks['fatigue_safe']) {
            $blockers[] = 'fatigue_trend';
        }
        if (!$checks['garbage_safe']) {
            $blockers[] = 'garbage_ratio_high';
        }
        if (!$checks['action_safe']) {
            $blockers[] = 'stop_loss_mode';
        }

        $passCount = count(array_filter($checks));
        $score = (int) round(($passCount / max(1, count($checks))) * 100);

        return [
            'can_scale' => $blockers === [],
            'score' => $score,
            'checks' => $checks,
            'blockers' => $blockers,
            'summary' => $blockers === []
                ? '通过放量护栏，可按20%-30%小步加预算并观察24小时。'
                : '未通过放量护栏，先处理阻塞项再加预算。',
        ];
    }

    /**
     * @param array<string,mixed> $scaleGuard
     * @param array<string,mixed> $stats
     * @return array<string,mixed>
     */
    private static function buildBudgetSplit(array $scaleGuard, array $stats): array
    {
        $canScale = (bool) ($scaleGuard['can_scale'] ?? false);
        $garbageCount = (int) ($stats['garbage_count'] ?? 0);
        $scaleCount = (int) ($stats['scale_count'] ?? 0);
        $optCount = (int) ($stats['optimize_count'] ?? 0);

        if (!$canScale) {
            $split = ['scale' => 20, 'potential' => 35, 'observe' => 30, 'test' => 10, 'waste_control' => 5];
            if ($garbageCount > max(2, (int) floor(((int) ($stats['evaluated_count'] ?? 0)) * 0.4))) {
                $split = ['scale' => 10, 'potential' => 30, 'observe' => 25, 'test' => 10, 'waste_control' => 25];
            }
            return ['mode' => 'defensive', 'split' => $split];
        }

        $base = ['scale' => 55, 'potential' => 25, 'observe' => 12, 'test' => 8, 'waste_control' => 0];
        if ($scaleCount <= 0 && $optCount > 0) {
            $base = ['scale' => 35, 'potential' => 35, 'observe' => 18, 'test' => 12, 'waste_control' => 0];
        }

        return ['mode' => 'scale', 'split' => $base];
    }

    /**
     * @return array<string,mixed>
     */
    private static function normalizeRow(int $tenantId, int $storeId, string $campaignId, string $campaignName, string $metricDate, string $dateRange, string $sourcePage, array $row): array
    {
        $videoId = self::cleanText($row['video_id'] ?? ($row['id'] ?? ''), 64);
        if ($videoId === '' || strtolower($videoId) === 'n/a') {
            return ['ok' => false, 'message' => 'video_id_required'];
        }
        $metrics = is_array($row['metrics'] ?? null) ? $row['metrics'] : $row;
        $diagnosis = is_array($row['diagnosis'] ?? null) ? $row['diagnosis'] : [];
        $data = [
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignName,
            'metric_date' => $metricDate,
            'date_range' => $dateRange !== '' ? $dateRange : null,
            'video_id' => $videoId,
            'title' => self::cleanText($row['title'] ?? '', 500),
            'tiktok_account' => self::cleanText($row['tiktok_account'] ?? '', 128),
            'status_text' => self::cleanText($row['status'] ?? ($row['status_text'] ?? ''), 64),
            'cost' => self::toFloat($metrics['cost'] ?? 0),
            'sku_orders' => max(0, (int) floor(self::toFloat($metrics['sku_orders'] ?? 0))),
            'cost_per_order' => self::toFloat($metrics['cost_per_order'] ?? 0),
            'gross_revenue' => self::toFloat($metrics['gross_revenue'] ?? 0),
            'roi' => self::toFloat($metrics['roi'] ?? 0),
            'product_ad_impressions' => max(0, (int) floor(self::toFloat($metrics['product_ad_impressions'] ?? 0))),
            'product_ad_clicks' => max(0, (int) floor(self::toFloat($metrics['product_ad_clicks'] ?? 0))),
            'product_ad_click_rate' => self::toFloat($metrics['product_ad_click_rate'] ?? 0),
            'ad_conversion_rate' => self::toFloat($metrics['ad_conversion_rate'] ?? 0),
            'view_rate_2s' => self::toFloat($metrics['view_rate_2s'] ?? 0),
            'view_rate_6s' => self::toFloat($metrics['view_rate_6s'] ?? 0),
            'view_rate_25' => self::toFloat($metrics['view_rate_25'] ?? 0),
            'view_rate_50' => self::toFloat($metrics['view_rate_50'] ?? 0),
            'view_rate_75' => self::toFloat($metrics['view_rate_75'] ?? 0),
            'view_rate_100' => self::toFloat($metrics['view_rate_100'] ?? 0),
            'hook_score' => self::cleanText($row['hook_score'] ?? ($diagnosis['hook_score'] ?? ''), 24),
            'retention_score' => self::cleanText($row['retention_score'] ?? ($diagnosis['retention_score'] ?? ''), 24),
            'conversion_score' => self::cleanText($row['conversion_score'] ?? ($diagnosis['conversion_score'] ?? ''), 24),
            'material_type' => self::normalizeMaterialType((string) ($row['material_type'] ?? ($diagnosis['material_type'] ?? ($row['auto_label'] ?? 'observe')))),
            'problem_position' => self::cleanText($row['problem_position'] ?? ($diagnosis['problem_position'] ?? ''), 32),
            'diagnosis_json' => self::jsonText($diagnosis !== [] ? $diagnosis : [
                'core_conclusion' => $row['core_conclusion'] ?? '',
                'actions' => $row['actions'] ?? [],
            ]),
            'raw_metrics_json' => self::jsonText($metrics),
            'source_page' => self::cleanText($row['source_page'] ?? $sourcePage, 500),
        ];

        return ['ok' => true, 'data' => $data];
    }

    /**
     * @return array<string,mixed>
     */
    private static function rebuildBaselines(int $tenantId, int $storeId, string $metricDate): array
    {
        $latest = [];
        foreach (self::WINDOWS as $window) {
            $rows = self::loadBaselineRows($tenantId, $storeId, $metricDate, $window);
            $baseline = self::calculateBaseline($rows);
            $baseline['tenant_id'] = $tenantId;
            $baseline['store_id'] = $storeId;
            $baseline['window_days'] = $window;
            $baseline['metric_date'] = $metricDate;
            self::upsertBaseline($baseline);
            if ($window === 30) {
                $latest = $baseline;
            }
        }
        return $latest !== [] ? $latest : ['sample_count' => 0, 'p70' => [], 'p90' => []];
    }

    private static function resolveStoreId(int $tenantId, $ref): int
    {
        $raw = trim((string) $ref);
        if ($raw === '') {
            return 0;
        }
        if (ctype_digit($raw)) {
            $row = Db::name('growth_profit_stores')->where('tenant_id', $tenantId)->where('id', (int) $raw)->find();
            if (is_array($row)) {
                return (int) $raw;
            }
        }
        try {
            $mapped = Db::name('growth_profit_plugin_store_maps')
                ->where('tenant_id', $tenantId)
                ->where('store_alias', $raw)
                ->where('status', 1)
                ->find();
            if (is_array($mapped) && (int) ($mapped['store_id'] ?? 0) > 0) {
                return (int) $mapped['store_id'];
            }
        } catch (\Throwable $e) {
            // ignore optional table
        }
        $row = Db::name('growth_profit_stores')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($raw) {
                $q->where('store_code', $raw)->whereOr('store_name', $raw);
            })
            ->order('id', 'asc')
            ->find();
        return is_array($row) ? (int) ($row['id'] ?? 0) : 0;
    }

    private static function resolveStage(array $stats, string $sampleLevel): string
    {
        if ((float) $stats['total_cost'] < 5 && (int) $stats['total_orders'] < 3) {
            return 'cold_start';
        }
        if ((int) $stats['total_orders'] < 20 || $sampleLevel === 'insufficient') {
            return 'learning';
        }
        if ((float) $stats['avg_roi'] >= 2.0 && (int) $stats['scale_count'] > 0) {
            return 'scale';
        }
        if ((int) $stats['garbage_count'] > max(2, (int) floor((int) $stats['evaluated_count'] * 0.45))) {
            return 'fatigue_risk';
        }
        return 'stable';
    }

    private static function resolveMainProblem(array $stats, array $p70): string
    {
        $ctrGate = (float) ($p70['product_ad_click_rate'] ?? 1.2);
        $cvrGate = (float) ($p70['ad_conversion_rate'] ?? 1.5);
        $view75Gate = (float) ($p70['view_rate_75'] ?? 3.5);
        if ((float) $stats['avg_ctr'] > 0 && (float) $stats['avg_ctr'] < max(0.6, $ctrGate * 0.75)) {
            return 'hook';
        }
        if ((float) $stats['avg_cvr'] > 0 && (float) $stats['avg_cvr'] < max(0.8, $cvrGate * 0.75)) {
            return 'conversion';
        }
        if ((float) $stats['avg_view75'] > 0 && (float) $stats['avg_view75'] < max(2.0, $view75Gate * 0.75)) {
            return 'retention';
        }
        if ((int) $stats['evaluated_count'] <= 0) {
            return 'insufficient_data';
        }
        return 'mixed';
    }

    private static function resolveActionLevel(array $stats, float $targetRoi): string
    {
        if ((int) $stats['garbage_count'] > 0 && (float) $stats['total_cost'] >= 1.2 && (int) $stats['total_orders'] <= 0) {
            return 'stop_loss';
        }
        $roiGate = $targetRoi > 0 ? $targetRoi : 2.0;
        if ((int) $stats['scale_count'] > 0 && (float) $stats['avg_roi'] >= $roiGate * 0.9 && (int) $stats['total_orders'] >= 3) {
            return 'scale';
        }
        if ((int) $stats['optimize_count'] > 0) {
            return 'optimize';
        }
        return 'observe';
    }

    private static function conclusion(string $stage, string $problem, string $action): string
    {
        if ($action === 'scale') {
            return '已有可放量素材，建议小步加预算并复制素材结构。';
        }
        if ($action === 'stop_loss') {
            return '当前预算被低效素材消耗，先止损再测试新素材。';
        }
        if ($problem === 'hook') {
            return '主要问题在前3秒，商品可能能卖但点击不足。';
        }
        if ($problem === 'conversion') {
            return '点击不差但卖货弱，优先补成交结构。';
        }
        if ($stage === 'cold_start') {
            return '仍处于冷启动，先积累有效点击和首单信号。';
        }
        return '当前素材表现混合，按弱项分段优化后再放量。';
    }

    private static function aggregateRows(array $rows): array
    {
        $evaluated = array_values(array_filter($rows, static function ($row) {
            return (string) ($row['material_type'] ?? '') !== 'ignore';
        }));
        $count = count($evaluated);
        $sum = static function (string $key) use ($evaluated): float {
            return array_reduce($evaluated, static function ($carry, $row) use ($key) {
                return $carry + (float) ($row[$key] ?? 0);
            }, 0.0);
        };
        $avg = static function (string $key) use ($evaluated, $count): float {
            if ($count <= 0) {
                return 0.0;
            }
            return array_reduce($evaluated, static function ($carry, $row) use ($key) {
                return $carry + (float) ($row[$key] ?? 0);
            }, 0.0) / $count;
        };
        $typeCount = static function (string $type) use ($evaluated): int {
            return count(array_filter($evaluated, static function ($row) use ($type) {
                return (string) ($row['material_type'] ?? '') === $type;
            }));
        };
        return [
            'total_count' => count($rows),
            'evaluated_count' => $count,
            'total_cost' => round($sum('cost'), 4),
            'total_orders' => (int) $sum('sku_orders'),
            'total_revenue' => round($sum('gross_revenue'), 4),
            'avg_roi' => round($avg('roi'), 4),
            'avg_ctr' => round($avg('product_ad_click_rate'), 4),
            'avg_cvr' => round($avg('ad_conversion_rate'), 4),
            'avg_view2' => round($avg('view_rate_2s'), 4),
            'avg_view75' => round($avg('view_rate_75'), 4),
            'scale_count' => $typeCount('scale'),
            'optimize_count' => $typeCount('optimize'),
            'observe_count' => $typeCount('observe'),
            'garbage_count' => $typeCount('bad'),
        ];
    }

    private static function calculateBaseline(array $rows): array
    {
        $metrics = ['roi', 'product_ad_click_rate', 'ad_conversion_rate', 'view_rate_2s', 'view_rate_6s', 'view_rate_75', 'cost_per_order'];
        $p50 = [];
        $p70 = [];
        $p90 = [];
        foreach ($metrics as $metric) {
            $values = array_map(static function ($row) use ($metric) {
                return (float) ($row[$metric] ?? 0);
            }, $rows);
            $values = array_values(array_filter($values, static function ($v) {
                return is_finite($v) && $v > 0;
            }));
            $p50[$metric] = self::percentile($values, 0.5);
            $p70[$metric] = self::percentile($values, 0.7);
            $p90[$metric] = self::percentile($values, 0.9);
        }
        $stats = self::aggregateRows($rows);
        return [
            'sample_count' => (int) $stats['evaluated_count'],
            'total_cost' => (float) $stats['total_cost'],
            'total_orders' => (int) $stats['total_orders'],
            'total_revenue' => (float) $stats['total_revenue'],
            'avg_roi' => (float) $stats['avg_roi'],
            'avg_ctr' => (float) $stats['avg_ctr'],
            'avg_cvr' => (float) $stats['avg_cvr'],
            'p50' => $p50,
            'p70' => $p70,
            'p90' => $p90,
        ];
    }

    private static function upsertBaseline(array $baseline): void
    {
        $payload = [
            'tenant_id' => (int) $baseline['tenant_id'],
            'store_id' => (int) $baseline['store_id'],
            'window_days' => (int) $baseline['window_days'],
            'metric_date' => (string) $baseline['metric_date'],
            'sample_count' => (int) ($baseline['sample_count'] ?? 0),
            'total_cost' => (float) ($baseline['total_cost'] ?? 0),
            'total_orders' => (int) ($baseline['total_orders'] ?? 0),
            'total_revenue' => (float) ($baseline['total_revenue'] ?? 0),
            'avg_roi' => (float) ($baseline['avg_roi'] ?? 0),
            'avg_ctr' => (float) ($baseline['avg_ctr'] ?? 0),
            'avg_cvr' => (float) ($baseline['avg_cvr'] ?? 0),
            'p50_json' => self::jsonText($baseline['p50'] ?? []),
            'p70_json' => self::jsonText($baseline['p70'] ?? []),
            'p90_json' => self::jsonText($baseline['p90'] ?? []),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $existing = Db::name('gmv_max_store_baselines')
            ->where('tenant_id', $payload['tenant_id'])
            ->where('store_id', $payload['store_id'])
            ->where('window_days', $payload['window_days'])
            ->where('metric_date', $payload['metric_date'])
            ->find();
        if (is_array($existing)) {
            Db::name('gmv_max_store_baselines')->where('id', (int) ($existing['id'] ?? 0))->update($payload);
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            Db::name('gmv_max_store_baselines')->insert($payload);
        }
    }

    private static function saveRecommendationSnapshot(int $tenantId, int $storeId, string $campaignId, string $campaignName, string $date, array $recommendation): void
    {
        $payload = [
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'campaign_id' => $campaignId,
            'campaign_name' => $campaignName,
            'snapshot_date' => $date,
            'baseline_mode' => (string) ($recommendation['baseline_mode'] ?? 'regional_default'),
            'sample_level' => (string) ($recommendation['sample_level'] ?? 'insufficient'),
            'stage' => (string) ($recommendation['stage'] ?? 'cold_start'),
            'main_problem' => (string) ($recommendation['main_problem'] ?? 'insufficient_data'),
            'action_level' => (string) ($recommendation['action_level'] ?? 'observe'),
            'recommendation_json' => self::jsonText($recommendation),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $existing = Db::name('gmv_max_recommendation_snapshots')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('campaign_id', $campaignId)
            ->where('snapshot_date', $date)
            ->find();
        if (is_array($existing)) {
            Db::name('gmv_max_recommendation_snapshots')->where('id', (int) ($existing['id'] ?? 0))->update($payload);
        } else {
            $payload['created_at'] = date('Y-m-d H:i:s');
            Db::name('gmv_max_recommendation_snapshots')->insert($payload);
        }
    }

    private static function loadDailyRows(int $tenantId, int $storeId, string $campaignId, string $date, int $limit): array
    {
        return Db::name('gmv_max_creative_daily')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('campaign_id', $campaignId)
            ->where('metric_date', $date)
            ->limit($limit)
            ->select()
            ->toArray();
    }

    private static function loadBaselineRows(int $tenantId, int $storeId, string $metricDate, int $windowDays): array
    {
        $query = Db::name('gmv_max_creative_daily')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('metric_date', '<=', $metricDate);
        if ($windowDays > 0) {
            $from = date('Y-m-d', strtotime($metricDate . ' -' . ($windowDays - 1) . ' days'));
            $query->where('metric_date', '>=', $from);
        }
        return $query->where('material_type', '<>', 'ignore')->limit(5000)->select()->toArray();
    }

    private static function formatBaselineRow(array $row): array
    {
        return [
            'baseline_mode' => (int) ($row['sample_count'] ?? 0) >= 30 ? 'store_history' : 'regional_default',
            'sample_level' => (int) ($row['sample_count'] ?? 0) >= 100 ? 'strong' : ((int) ($row['sample_count'] ?? 0) >= 30 ? 'usable' : 'insufficient'),
            'store_id' => (int) ($row['store_id'] ?? 0),
            'window_days' => (int) ($row['window_days'] ?? 0),
            'metric_date' => (string) ($row['metric_date'] ?? ''),
            'sample_count' => (int) ($row['sample_count'] ?? 0),
            'total_cost' => (float) ($row['total_cost'] ?? 0),
            'total_orders' => (int) ($row['total_orders'] ?? 0),
            'total_revenue' => (float) ($row['total_revenue'] ?? 0),
            'avg_roi' => (float) ($row['avg_roi'] ?? 0),
            'avg_ctr' => (float) ($row['avg_ctr'] ?? 0),
            'avg_cvr' => (float) ($row['avg_cvr'] ?? 0),
            'p50' => self::decodeJson($row['p50_json'] ?? null, []),
            'p70' => self::decodeJson($row['p70_json'] ?? null, []),
            'p90' => self::decodeJson($row['p90_json'] ?? null, []),
        ];
    }

    private static function formatDailyRow(array $row): array
    {
        $out = $row;
        $out['id'] = (int) ($row['id'] ?? 0);
        $out['store_id'] = (int) ($row['store_id'] ?? 0);
        $out['sku_orders'] = (int) ($row['sku_orders'] ?? 0);
        foreach (['cost', 'cost_per_order', 'gross_revenue', 'roi', 'product_ad_click_rate', 'ad_conversion_rate', 'view_rate_2s', 'view_rate_6s', 'view_rate_25', 'view_rate_50', 'view_rate_75', 'view_rate_100'] as $field) {
            $out[$field] = round((float) ($row[$field] ?? 0), 4);
        }
        $out['diagnosis'] = self::decodeJson($row['diagnosis_json'] ?? null, []);
        unset($out['diagnosis_json'], $out['raw_metrics_json']);
        return $out;
    }

    private static function percentile(array $values, float $p): float
    {
        $values = array_values(array_filter(array_map('floatval', $values), static function ($v) {
            return is_finite($v);
        }));
        sort($values);
        $count = count($values);
        if ($count <= 0) {
            return 0.0;
        }
        if ($count === 1) {
            return round($values[0], 4);
        }
        $pos = ($count - 1) * max(0, min(1, $p));
        $lower = (int) floor($pos);
        $upper = (int) ceil($pos);
        if ($lower === $upper) {
            return round($values[$lower], 4);
        }
        $weight = $pos - $lower;
        return round($values[$lower] * (1 - $weight) + $values[$upper] * $weight, 4);
    }

    private static function normalizeDate(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        return $ts === false ? '' : date('Y-m-d', $ts);
    }

    private static function resolveDateFromRange(string $value): string
    {
        if (preg_match('/(20\d{2})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        return '';
    }

    private static function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 4);
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }
        $raw = str_replace([',', '%'], '', $raw);
        return is_numeric($raw) ? round((float) $raw, 4) : 0.0;
    }

    private static function cleanText($value, int $maxLen): string
    {
        return mb_substr(trim((string) ($value ?? '')), 0, $maxLen);
    }

    private static function jsonText($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        return is_string($json) ? $json : '{}';
    }

    private static function decodeJson($value, array $default): array
    {
        if (!is_string($value) || trim($value) === '') {
            return $default;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $default;
    }

    private static function normalizeMaterialType(string $value): string
    {
        $raw = strtolower(trim($value));
        if (in_array($raw, ['scale', 'excellent'], true)) {
            return 'scale';
        }
        if (in_array($raw, ['optimize'], true)) {
            return 'optimize';
        }
        if (in_array($raw, ['bad', 'garbage', 'exclude_candidate'], true)) {
            return 'bad';
        }
        if ($raw === 'ignore') {
            return 'ignore';
        }
        return 'observe';
    }
}
