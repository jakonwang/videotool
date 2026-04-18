<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\GrowthProfitAccount as GrowthProfitAccountModel;
use app\model\GrowthProfitDailyEntry as GrowthProfitDailyEntryModel;
use app\model\GrowthProfitStore as GrowthProfitStoreModel;
use app\service\FxRateService;
use app\service\ProfitCalculatorService;
use app\service\StoreCurrencyService;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use think\facade\Db;
use think\facade\View;

class ProfitCenter extends BaseController
{
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
        return View::fetch('admin/profit_center/index', []);
    }

    public function summaryJson()
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange(
            (string) $this->request->param('date_from', ''),
            (string) $this->request->param('date_to', '')
        );
        $storeId = (int) $this->request->param('store_id', 0);
        $accountId = (int) $this->request->param('account_id', 0);
        $channelType = ProfitCalculatorService::normalizeChannelType((string) $this->request->param('channel_type', ''));

        $base = Db::name('growth_profit_daily_entries')->alias('e')
            ->leftJoin('growth_profit_stores s', 's.id=e.store_id')
            ->leftJoin('growth_profit_accounts a', 'a.id=e.account_id')
            ->where('e.entry_date', '>=', $dateFrom)
            ->where('e.entry_date', '<=', $dateTo)
            ->where('e.tenant_id', $this->currentTenantId());

        if ($storeId > 0) {
            $base->where('e.store_id', $storeId);
        }
        if ($accountId > 0) {
            $base->where('e.account_id', $accountId);
        }
        if ($channelType !== '') {
            $base->where('e.channel_type', $channelType);
        }

        $totalRow = (clone $base)->fieldRaw('
            COUNT(*) as entry_count,
            COALESCE(SUM(e.net_profit_cny),0) as net_profit_cny,
            COALESCE(SUM(e.ad_spend_cny),0) as ad_spend_cny,
            COALESCE(SUM(e.ad_compensation_cny),0) as ad_compensation_cny,
            COALESCE(SUM(e.gmv_cny),0) as gmv_cny,
            COALESCE(SUM(e.order_count),0) as order_count
        ')->find();
        $total = is_array($totalRow) ? $totalRow : [];
        $totalAd = (float) ($total['ad_spend_cny'] ?? 0);
        $totalCompensation = (float) ($total['ad_compensation_cny'] ?? 0);
        $totalGmv = (float) ($total['gmv_cny'] ?? 0);

        $storeRows = (clone $base)->fieldRaw('
            e.store_id,
            COALESCE(s.store_name, "") as store_name,
            COUNT(*) as entry_count,
            COALESCE(SUM(e.net_profit_cny),0) as net_profit_cny,
            COALESCE(SUM(e.ad_spend_cny),0) as ad_spend_cny,
            COALESCE(SUM(e.ad_compensation_cny),0) as ad_compensation_cny,
            COALESCE(SUM(e.gmv_cny),0) as gmv_cny,
            COALESCE(SUM(e.order_count),0) as order_count
        ')->group('e.store_id,s.store_name')->order('net_profit_cny', 'desc')->select()->toArray();

        $channelRows = (clone $base)->fieldRaw('
            e.channel_type,
            COUNT(*) as entry_count,
            COALESCE(SUM(e.net_profit_cny),0) as net_profit_cny,
            COALESCE(SUM(e.ad_spend_cny),0) as ad_spend_cny,
            COALESCE(SUM(e.ad_compensation_cny),0) as ad_compensation_cny,
            COALESCE(SUM(e.gmv_cny),0) as gmv_cny,
            COALESCE(SUM(e.order_count),0) as order_count
        ')->group('e.channel_type')->select()->toArray();

        $dailyRows = (clone $base)->fieldRaw('
            e.entry_date,
            COUNT(*) as entry_count,
            COALESCE(SUM(e.net_profit_cny),0) as net_profit_cny,
            COALESCE(SUM(e.ad_spend_cny),0) as ad_spend_cny,
            COALESCE(SUM(e.ad_compensation_cny),0) as ad_compensation_cny,
            COALESCE(SUM(e.gmv_cny),0) as gmv_cny,
            COALESCE(SUM(e.order_count),0) as order_count
        ')->group('e.entry_date')->order('e.entry_date', 'asc')->select()->toArray();

        $storeSummary = [];
        foreach ($storeRows as $row) {
            $ad = (float) ($row['ad_spend_cny'] ?? 0);
            $gmv = (float) ($row['gmv_cny'] ?? 0);
            $storeSummary[] = [
                'store_id' => (int) ($row['store_id'] ?? 0),
                'store_name' => (string) ($row['store_name'] ?? ''),
                'entry_count' => (int) ($row['entry_count'] ?? 0),
                'net_profit_cny' => round((float) ($row['net_profit_cny'] ?? 0), 2),
                'ad_spend_cny' => round($ad, 2),
                'ad_compensation_cny' => round((float) ($row['ad_compensation_cny'] ?? 0), 2),
                'gmv_cny' => round($gmv, 2),
                'order_count' => (int) ($row['order_count'] ?? 0),
                'avg_roi' => $ad > 0 ? round($gmv / $ad, 6) : null,
            ];
        }

        $channelSummary = [];
        foreach ($channelRows as $row) {
            $ad = (float) ($row['ad_spend_cny'] ?? 0);
            $gmv = (float) ($row['gmv_cny'] ?? 0);
            $channel = ProfitCalculatorService::normalizeChannelType((string) ($row['channel_type'] ?? ''));
            $channelSummary[] = [
                'channel_type' => $channel,
                'channel_label' => $this->channelLabel($channel),
                'entry_count' => (int) ($row['entry_count'] ?? 0),
                'net_profit_cny' => round((float) ($row['net_profit_cny'] ?? 0), 2),
                'ad_spend_cny' => round($ad, 2),
                'ad_compensation_cny' => round((float) ($row['ad_compensation_cny'] ?? 0), 2),
                'gmv_cny' => round($gmv, 2),
                'order_count' => (int) ($row['order_count'] ?? 0),
                'avg_roi' => $ad > 0 ? round($gmv / $ad, 6) : null,
            ];
        }

        $dailySummary = [];
        foreach ($dailyRows as $row) {
            $ad = (float) ($row['ad_spend_cny'] ?? 0);
            $gmv = (float) ($row['gmv_cny'] ?? 0);
            $dailySummary[] = [
                'entry_date' => (string) ($row['entry_date'] ?? ''),
                'entry_count' => (int) ($row['entry_count'] ?? 0),
                'net_profit_cny' => round((float) ($row['net_profit_cny'] ?? 0), 2),
                'ad_spend_cny' => round($ad, 2),
                'ad_compensation_cny' => round((float) ($row['ad_compensation_cny'] ?? 0), 2),
                'gmv_cny' => round($gmv, 2),
                'order_count' => (int) ($row['order_count'] ?? 0),
                'avg_roi' => $ad > 0 ? round($gmv / $ad, 6) : null,
            ];
        }

        return $this->jsonOk([
            'range' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'kpi' => [
                'entry_count' => (int) ($total['entry_count'] ?? 0),
                'net_profit_cny' => round((float) ($total['net_profit_cny'] ?? 0), 2),
                'ad_spend_cny' => round($totalAd, 2),
                'ad_compensation_cny' => round($totalCompensation, 2),
                'gmv_cny' => round($totalGmv, 2),
                'order_count' => (int) ($total['order_count'] ?? 0),
                'avg_roi' => $totalAd > 0 ? round($totalGmv / $totalAd, 6) : null,
            ],
            'by_store' => $storeSummary,
            'by_channel' => $channelSummary,
            'by_day' => $dailySummary,
        ]);
    }

    public function entryListJson()
    {
        $page = max(1, (int) $this->request->param('page', 1));
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }
        [$dateFrom, $dateTo] = $this->resolveDateRange(
            (string) $this->request->param('date_from', ''),
            (string) $this->request->param('date_to', '')
        );
        $storeId = (int) $this->request->param('store_id', 0);
        $accountId = (int) $this->request->param('account_id', 0);
        $channelType = ProfitCalculatorService::normalizeChannelType((string) $this->request->param('channel_type', ''));

        $query = Db::name('growth_profit_daily_entries')->alias('e')
            ->leftJoin('growth_profit_stores s', 's.id=e.store_id')
            ->leftJoin('growth_profit_accounts a', 'a.id=e.account_id')
            ->where('e.tenant_id', $this->currentTenantId())
            ->where('e.entry_date', '>=', $dateFrom)
            ->where('e.entry_date', '<=', $dateTo)
            ->field('
                e.*,
                s.store_name,
                s.store_code,
                a.account_name,
                a.account_code
            ')
            ->order('e.entry_date', 'desc')
            ->order('e.id', 'desc');

        if ($storeId > 0) {
            $query->where('e.store_id', $storeId);
        }
        if ($accountId > 0) {
            $query->where('e.account_id', $accountId);
        }
        if ($channelType !== '') {
            $query->where('e.channel_type', $channelType);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);
        $items = [];
        foreach ($list as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $fxSnapshotRaw = (string) ($arr['fx_snapshot_json'] ?? '');
            $fxSnapshot = json_decode($fxSnapshotRaw, true);
            if (!is_array($fxSnapshot)) {
                $fxSnapshot = [];
            }
            $items[] = [
                'id' => (int) ($arr['id'] ?? 0),
                'entry_date' => (string) ($arr['entry_date'] ?? ''),
                'store_id' => (int) ($arr['store_id'] ?? 0),
                'store_name' => (string) ($arr['store_name'] ?? ''),
                'store_code' => (string) ($arr['store_code'] ?? ''),
                'account_id' => (int) ($arr['account_id'] ?? 0),
                'account_name' => (string) ($arr['account_name'] ?? ''),
                'account_code' => (string) ($arr['account_code'] ?? ''),
                'channel_type' => (string) ($arr['channel_type'] ?? ''),
                'channel_label' => $this->channelLabel((string) ($arr['channel_type'] ?? '')),
                'sale_price_cny' => round((float) ($arr['sale_price_cny'] ?? 0), 2),
                'product_cost_cny' => round((float) ($arr['product_cost_cny'] ?? 0), 2),
                'cancel_rate' => (float) ($arr['cancel_rate'] ?? 0),
                'platform_fee_rate' => (float) ($arr['platform_fee_rate'] ?? 0),
                'influencer_commission_rate' => (float) ($arr['influencer_commission_rate'] ?? 0),
                'live_hours' => round((float) ($arr['live_hours'] ?? 0), 2),
                'wage_hourly_cny' => round((float) ($arr['wage_hourly_cny'] ?? 0), 2),
                'wage_cost_cny' => round((float) ($arr['wage_cost_cny'] ?? 0), 2),
                'ad_spend_amount' => round((float) ($arr['ad_spend_amount'] ?? 0), 2),
                'ad_spend_currency' => (string) ($arr['ad_spend_currency'] ?? 'CNY'),
                'ad_spend_cny' => round((float) ($arr['ad_spend_cny'] ?? 0), 2),
                'ad_compensation_amount' => round((float) ($arr['ad_compensation_amount'] ?? 0), 2),
                'ad_compensation_currency' => (string) ($arr['ad_compensation_currency'] ?? 'CNY'),
                'ad_compensation_cny' => round((float) ($arr['ad_compensation_cny'] ?? 0), 2),
                'gmv_amount' => round((float) ($arr['gmv_amount'] ?? 0), 2),
                'gmv_currency' => (string) ($arr['gmv_currency'] ?? 'CNY'),
                'gmv_cny' => round((float) ($arr['gmv_cny'] ?? 0), 2),
                'order_count' => (int) ($arr['order_count'] ?? 0),
                'roi' => round((float) ($arr['roi'] ?? 0), 6),
                'net_profit_cny' => round((float) ($arr['net_profit_cny'] ?? 0), 2),
                'break_even_roi' => $arr['break_even_roi'] === null ? null : round((float) $arr['break_even_roi'], 6),
                'per_order_profit_cny' => round((float) ($arr['per_order_profit_cny'] ?? 0), 2),
                'fx_status' => (string) ($arr['fx_status'] ?? ''),
                'fx_snapshot' => $fxSnapshot,
                'raw_metrics_json' => (string) ($arr['raw_metrics_json'] ?? ''),
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

    public function entrySave()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $saved = $this->upsertEntry($payload, $this->currentTenantId());
        if (!($saved['ok'] ?? false)) {
            return $this->jsonErr((string) ($saved['message'] ?? 'save_failed'), 1, $saved, 'common.saveFailed');
        }

        return $this->jsonOk($saved['data'] ?? [], 'saved');
    }

    public function entryBatchSave()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $items = $payload['items'] ?? null;
        if (!is_array($items)) {
            $isListPayload = false;
            if (is_array($payload) && $payload !== []) {
                $keys = array_keys($payload);
                $isListPayload = ($keys === range(0, count($payload) - 1));
            }
            $items = $isListPayload ? $payload : [];
        }
        if ($items === []) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        $total = count($items);
        if ($total > 300) {
            return $this->jsonErr('batch_too_large', 1, ['max' => 300], 'common.invalidParams');
        }

        $tenantId = $this->currentTenantId();
        $savedCount = 0;
        $savedItems = [];
        $failedItems = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                $failedItems[] = [
                    'index' => (int) $index + 1,
                    'message' => 'invalid_item',
                ];
                continue;
            }
            $saved = $this->upsertEntry($item, $tenantId);
            if ($saved['ok'] ?? false) {
                ++$savedCount;
                $data = is_array($saved['data'] ?? null) ? $saved['data'] : [];
                $savedItems[] = [
                    'index' => (int) $index + 1,
                    'id' => (int) ($data['id'] ?? 0),
                    'entry_date' => (string) ($data['entry_date'] ?? ''),
                    'store_id' => (int) ($data['store_id'] ?? 0),
                    'account_id' => (int) ($data['account_id'] ?? 0),
                    'channel_type' => (string) ($data['channel_type'] ?? ''),
                ];
                continue;
            }

            $failedItems[] = [
                'index' => (int) $index + 1,
                'message' => (string) ($saved['message'] ?? 'save_failed'),
            ];
        }

        return $this->jsonOk([
            'total' => $total,
            'saved_count' => $savedCount,
            'failed_count' => count($failedItems),
            'saved_items' => array_slice($savedItems, 0, 300),
            'failed_items' => array_slice($failedItems, 0, 300),
        ], 'saved');
    }

    public function entryDelete()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $query = GrowthProfitDailyEntryModel::where('id', $id);
        $query = $this->scopeTenant($query, 'growth_profit_daily_entries');
        $query->delete();
        return $this->jsonOk([], 'deleted');
    }

    public function storeListJson()
    {
        $status = trim((string) $this->request->param('status', ''));
        $query = GrowthProfitStoreModel::order('id', 'desc');
        $query = $this->scopeTenant($query, 'growth_profit_stores');
        if ($status !== '') {
            $query->where('status', (int) $status === 0 ? 0 : 1);
        }
        $rows = $query->select();
        $items = [];
        foreach ($rows as $row) {
            $baseCancelRate = (float) ($row->default_cancel_rate ?? 0);
            $storeDefaultCurrency = $this->parseStoreDefaultGmvCurrency((string) ($row->default_gmv_currency ?? 'VND'));
            $items[] = [
                'id' => (int) $row->id,
                'store_code' => (string) ($row->store_code ?? ''),
                'store_name' => (string) ($row->store_name ?? ''),
                'default_sale_price_cny' => round((float) ($row->default_sale_price_cny ?? 0), 2),
                'default_product_cost_cny' => round((float) ($row->default_product_cost_cny ?? 0), 2),
                'default_cancel_rate' => $baseCancelRate,
                'default_cancel_rate_live' => (float) ($row->default_cancel_rate_live ?? $baseCancelRate),
                'default_cancel_rate_video' => (float) ($row->default_cancel_rate_video ?? $baseCancelRate),
                'default_cancel_rate_influencer' => (float) ($row->default_cancel_rate_influencer ?? $baseCancelRate),
                'default_platform_fee_rate' => (float) ($row->default_platform_fee_rate ?? 0),
                'default_influencer_commission_rate' => (float) ($row->default_influencer_commission_rate ?? 0),
                'default_live_wage_hourly_cny' => round((float) ($row->default_live_wage_hourly_cny ?? 0), 2),
                'default_timezone' => (string) ($row->default_timezone ?? 'Asia/Bangkok'),
                'default_gmv_currency' => $storeDefaultCurrency,
                'status' => (int) ($row->status ?? 1),
                'notes' => (string) ($row->notes ?? ''),
                'updated_at' => (string) ($row->updated_at ?? ''),
            ];
        }

        return $this->jsonOk(['items' => $items]);
    }

    public function storeSave()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $storeCode = trim((string) ($payload['store_code'] ?? ''));
        $storeName = trim((string) ($payload['store_name'] ?? ''));
        if ($storeName === '') {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $cancelRateBase = $this->parseRate($payload['default_cancel_rate'] ?? 0);
        $cancelRateLive = $this->parseRate($payload['default_cancel_rate_live'] ?? $cancelRateBase, $cancelRateBase);
        $cancelRateVideo = $this->parseRate($payload['default_cancel_rate_video'] ?? $cancelRateBase, $cancelRateBase);
        $cancelRateInfluencer = $this->parseRate($payload['default_cancel_rate_influencer'] ?? $cancelRateBase, $cancelRateBase);
        $defaultGmvCurrency = $this->parseStoreDefaultGmvCurrency((string) ($payload['default_gmv_currency'] ?? 'VND'));
        $storeHasDefaultGmvCurrency = $this->hasTableColumnCached('growth_profit_stores', 'default_gmv_currency');
        $savePayload = [
            'store_code' => $storeCode !== '' ? mb_substr($storeCode, 0, 64) : null,
            'store_name' => mb_substr($storeName, 0, 128),
            'default_sale_price_cny' => round($this->parseDecimal($payload['default_sale_price_cny'] ?? 0), 2),
            'default_product_cost_cny' => round($this->parseDecimal($payload['default_product_cost_cny'] ?? 0), 2),
            'default_cancel_rate' => $cancelRateBase,
            'default_cancel_rate_live' => $cancelRateLive,
            'default_cancel_rate_video' => $cancelRateVideo,
            'default_cancel_rate_influencer' => $cancelRateInfluencer,
            'default_platform_fee_rate' => $this->parseRate($payload['default_platform_fee_rate'] ?? 0),
            'default_influencer_commission_rate' => $this->parseRate($payload['default_influencer_commission_rate'] ?? 0),
            'default_live_wage_hourly_cny' => round($this->parseDecimal($payload['default_live_wage_hourly_cny'] ?? 0), 2),
            'default_timezone' => mb_substr(trim((string) ($payload['default_timezone'] ?? 'Asia/Bangkok')), 0, 32),
            'status' => (int) ($payload['status'] ?? 1) === 0 ? 0 : 1,
            'notes' => $this->cleanNullableText($payload['notes'] ?? null, 255),
        ];
        if ($storeHasDefaultGmvCurrency) {
            $savePayload['default_gmv_currency'] = $defaultGmvCurrency;
        }

        if ($id > 0) {
            $query = GrowthProfitStoreModel::where('id', $id);
            $query = $this->scopeTenant($query, 'growth_profit_stores');
            $row = $query->find();
            if (!$row) {
                return $this->jsonErr('not_found', 1, null, 'common.notFound');
            }
            foreach ($savePayload as $k => $v) {
                $row->$k = $v;
            }
            $row->save();
            $syncCurrency = $storeHasDefaultGmvCurrency
                ? $this->parseStoreDefaultGmvCurrency((string) ($row->default_gmv_currency ?? $defaultGmvCurrency))
                : $defaultGmvCurrency;
            $this->syncStoreAccountDefaultGmvCurrency($id, $syncCurrency);
            return $this->jsonOk(['id' => $id], 'saved');
        }

        $createPayload = $this->withTenantPayload($savePayload, 'growth_profit_stores');
        $created = GrowthProfitStoreModel::create($createPayload);
        $createdId = (int) ($created->id ?? 0);
        if ($createdId > 0) {
            $syncCurrency = $storeHasDefaultGmvCurrency
                ? $this->parseStoreDefaultGmvCurrency((string) ($created->default_gmv_currency ?? $defaultGmvCurrency))
                : $defaultGmvCurrency;
            $this->syncStoreAccountDefaultGmvCurrency($createdId, $syncCurrency);
        }
        return $this->jsonOk(['id' => $createdId], 'saved');
    }

    public function storeDelete()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        $accountCount = (int) Db::name('growth_profit_accounts')
            ->where('tenant_id', $this->currentTenantId())
            ->where('store_id', $id)
            ->count();
        if ($accountCount > 0) {
            return $this->jsonErr('store_has_accounts', 1, ['count' => $accountCount], 'common.operationFailed');
        }
        $entryCount = (int) Db::name('growth_profit_daily_entries')
            ->where('tenant_id', $this->currentTenantId())
            ->where('store_id', $id)
            ->count();
        if ($entryCount > 0) {
            return $this->jsonErr('store_has_entries', 1, ['count' => $entryCount], 'common.operationFailed');
        }

        $query = GrowthProfitStoreModel::where('id', $id);
        $query = $this->scopeTenant($query, 'growth_profit_stores');
        $query->delete();
        return $this->jsonOk([], 'deleted');
    }

    public function accountListJson()
    {
        $storeId = (int) $this->request->param('store_id', 0);
        $status = trim((string) $this->request->param('status', ''));

        $query = Db::name('growth_profit_accounts')->alias('a')
            ->leftJoin('growth_profit_stores s', 's.id=a.store_id')
            ->where('a.tenant_id', $this->currentTenantId())
            ->field('a.*, s.store_name')
            ->order('a.id', 'desc');
        if ($storeId > 0) {
            $query->where('a.store_id', $storeId);
        }
        if ($status !== '') {
            $query->where('a.status', (int) $status === 0 ? 0 : 1);
        }
        $rows = $query->select();
        $items = [];
        foreach ($rows as $row) {
            $r = is_array($row) ? $row : $row->toArray();
            $items[] = [
                'id' => (int) ($r['id'] ?? 0),
                'store_id' => (int) ($r['store_id'] ?? 0),
                'store_name' => (string) ($r['store_name'] ?? ''),
                'account_name' => (string) ($r['account_name'] ?? ''),
                'account_code' => (string) ($r['account_code'] ?? ''),
                'account_currency' => (string) ($r['account_currency'] ?? 'USD'),
                'default_gmv_currency' => (string) ($r['default_gmv_currency'] ?? 'VND'),
                'status' => (int) ($r['status'] ?? 1),
                'notes' => (string) ($r['notes'] ?? ''),
                'updated_at' => (string) ($r['updated_at'] ?? ''),
            ];
        }

        return $this->jsonOk(['items' => $items]);
    }

    public function accountSave()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $storeId = (int) ($payload['store_id'] ?? 0);
        $accountName = trim((string) ($payload['account_name'] ?? ''));
        if ($storeId <= 0 || $accountName === '') {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $storeQuery = GrowthProfitStoreModel::where('id', $storeId);
        $storeQuery = $this->scopeTenant($storeQuery, 'growth_profit_stores');
        $store = $storeQuery->find();
        if (!$store) {
            return $this->jsonErr('store_not_found', 1, null, 'common.notFound');
        }

        $tenantId = $this->currentTenantId();
        $savePayload = [
            'store_id' => $storeId,
            'account_name' => mb_substr($accountName, 0, 128),
            'account_code' => $this->cleanNullableText($payload['account_code'] ?? null, 64),
            // GMV MAX account is shared by live/video channels; keep legacy column for compatibility.
            'channel_type' => ProfitCalculatorService::CHANNEL_VIDEO,
            'account_currency' => FxRateService::normalizeCurrency((string) ($payload['account_currency'] ?? 'USD')),
            'status' => (int) ($payload['status'] ?? 1) === 0 ? 0 : 1,
            'notes' => $this->cleanNullableText($payload['notes'] ?? null, 255),
        ];
        if ($this->hasTableColumnCached('growth_profit_accounts', 'default_gmv_currency')) {
            $storeDefaultCurrency = $this->parseStoreDefaultGmvCurrency((string) ($store->default_gmv_currency ?? 'VND'));
            $payloadGmvCurrency = trim((string) ($payload['default_gmv_currency'] ?? ''));
            $savePayload['default_gmv_currency'] = $payloadGmvCurrency !== ''
                ? FxRateService::normalizeCurrency($payloadGmvCurrency)
                : $storeDefaultCurrency;
        }

        $sameStoreQuery = Db::name('growth_profit_accounts')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId);
        if ($id > 0) {
            $sameStoreQuery->where('id', '<>', $id);
        }
        $sameStoreAccount = $sameStoreQuery->order('id', 'desc')->find();
        if ($id > 0 && is_array($sameStoreAccount)) {
            return $this->jsonErr(
                'single_account_per_store',
                1,
                ['store_id' => $storeId, 'existing_account_id' => (int) ($sameStoreAccount['id'] ?? 0)],
                'page.profitCenter.singleAccountPerStore'
            );
        }
        if ($id <= 0 && is_array($sameStoreAccount)) {
            // Create request on same store becomes update, ensuring "one store one GMV MAX account".
            $id = (int) ($sameStoreAccount['id'] ?? 0);
        }

        if ($id > 0) {
            $query = GrowthProfitAccountModel::where('id', $id);
            $query = $this->scopeTenant($query, 'growth_profit_accounts');
            $row = $query->find();
            if (!$row) {
                return $this->jsonErr('not_found', 1, null, 'common.notFound');
            }
            foreach ($savePayload as $k => $v) {
                $row->$k = $v;
            }
            $row->save();
            return $this->jsonOk(['id' => $id], 'saved');
        }

        $createPayload = $this->withTenantPayload($savePayload, 'growth_profit_accounts');
        $created = GrowthProfitAccountModel::create($createPayload);
        return $this->jsonOk(['id' => (int) ($created->id ?? 0)], 'saved');
    }

    public function accountDelete()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        if ($id <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $entryCount = (int) Db::name('growth_profit_daily_entries')
            ->where('tenant_id', $this->currentTenantId())
            ->where('account_id', $id)
            ->count();
        if ($entryCount > 0) {
            return $this->jsonErr('account_has_entries', 1, ['count' => $entryCount], 'common.operationFailed');
        }

        $query = GrowthProfitAccountModel::where('id', $id);
        $query = $this->scopeTenant($query, 'growth_profit_accounts');
        $query->delete();
        return $this->jsonOk([], 'deleted');
    }

    public function fxRateListJson()
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange(
            (string) $this->request->param('date_from', ''),
            (string) $this->request->param('date_to', '')
        );
        $fromCurrency = trim((string) $this->request->param('from_currency', ''));
        $fromCurrency = $fromCurrency !== '' ? FxRateService::normalizeCurrency($fromCurrency) : '';

        $query = Db::name('growth_fx_rates')
            ->where('tenant_id', $this->currentTenantId())
            ->where('rate_date', '>=', $dateFrom)
            ->where('rate_date', '<=', $dateTo)
            ->order('rate_date', 'desc')
            ->order('id', 'desc')
            ->limit(500);
        if ($fromCurrency !== '') {
            $query->where('from_currency', $fromCurrency);
        }

        $rows = $query->select()->toArray();
        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'rate_date' => (string) ($row['rate_date'] ?? ''),
                'from_currency' => (string) ($row['from_currency'] ?? ''),
                'to_currency' => (string) ($row['to_currency'] ?? ''),
                'rate' => round((float) ($row['rate'] ?? 0), 8),
                'source' => (string) ($row['source'] ?? ''),
                'is_fallback' => (int) ($row['is_fallback'] ?? 0),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }
        return $this->jsonOk([
            'range' => ['date_from' => $dateFrom, 'date_to' => $dateTo],
            'items' => $items,
        ]);
    }

    public function fxSync()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $rateDate = FxRateService::normalizeDate((string) ($payload['rate_date'] ?? ''));
        $currencies = $this->parseCurrencyList($payload['currencies'] ?? null);
        $items = FxRateService::syncRatesForDate($rateDate, $currencies, $this->currentTenantId());
        return $this->jsonOk([
            'rate_date' => $rateDate,
            'items' => $items,
        ]);
    }

    public function templateXlsx()
    {
        $sheets = [
            [
                'title' => '直播GMV利润表',
                'headers' => ['日期', '售价(CNY)', '产品成本(CNY)', '取消率', '平台扣点', '广告花费', '成交金额', '广告赔付金额', '订单数量', '直播时长', '人员工资'],
            ],
            [
                'title' => '视频GMV利润表',
                'headers' => ['日期', '售价(CNY)', '产品成本(CNY)', '取消率', '平台扣点', '广告花费', '成交金额', '广告赔付金额', '订单数量'],
            ],
            [
                'title' => '达人GMV利润表',
                'headers' => ['日期', '售价(CNY)', '产品成本(CNY)', '取消率', '平台扣点', '达人佣金率', '广告赔付金额', '订单数量'],
            ],
        ];

        try {
            $spreadsheet = new Spreadsheet();
            foreach ($sheets as $idx => $sheetDef) {
                $sheet = $idx === 0 ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet($idx);
                $sheet->setTitle((string) $sheetDef['title']);
                $headers = is_array($sheetDef['headers']) ? $sheetDef['headers'] : [];
                $colCount = count($headers);
                foreach ($headers as $i => $header) {
                    $col = Coordinate::stringFromColumnIndex($i + 1);
                    $sheet->setCellValue($col . '1', (string) $header);
                    $sheet->getColumnDimension($col)->setWidth($i === 0 ? 14 : 16);
                }
                if ($colCount > 0) {
                    $lastCol = Coordinate::stringFromColumnIndex($colCount);
                    $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
                    $sheet->setAutoFilter('A1:' . $lastCol . '1');
                }
                $sheet->freezePane('A2');
            }
            $spreadsheet->setActiveSheetIndex(0);

            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $filename = 'profit_import_template.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new XlsxWriter($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                return $this->jsonErr('export_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
            }
        }
        exit;
    }

    public function importXlsx()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $storeId = (int) $this->request->param('store_id', 0);
        $accountId = (int) $this->request->param('account_id', 0);
        if ($storeId <= 0 || $accountId <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $storeQuery = GrowthProfitStoreModel::where('id', $storeId);
        $storeQuery = $this->scopeTenant($storeQuery, 'growth_profit_stores');
        $store = $storeQuery->find();
        $accountQuery = GrowthProfitAccountModel::where('id', $accountId);
        $accountQuery = $this->scopeTenant($accountQuery, 'growth_profit_accounts');
        $account = $accountQuery->find();
        if (!$store || !$account) {
            return $this->jsonErr('not_found', 1, null, 'common.notFound');
        }
        if ((int) $account->store_id !== $storeId) {
            return $this->jsonErr('account_store_mismatch', 1, null, 'common.invalidParams');
        }

        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonErr('file_required', 1, null, 'common.pickFile');
        }
        $ext = strtolower((string) $file->extension());
        if ($ext === '') {
            $ext = strtolower(pathinfo((string) $file->getOriginalName(), PATHINFO_EXTENSION));
        }
        if (!in_array($ext, ['xlsx', 'xls', 'xlsm'], true)) {
            return $this->jsonErr('xlsx_only', 1, null, 'common.invalidParams');
        }
        $tmpPath = $file->getPathname();
        if (!is_readable($tmpPath)) {
            return $this->jsonErr('file_unreadable', 1, null, 'common.loadingFailed');
        }

        $adCurrencyOverride = trim((string) $this->request->param('ad_spend_currency', ''));
        $gmvCurrencyOverride = trim((string) $this->request->param('gmv_currency', ''));

        try {
            $spreadsheet = IOFactory::load($tmpPath);
        } catch (\Throwable $e) {
            return $this->jsonErr('import_failed', 1, ['message' => $e->getMessage()], 'page.dataImport.importFailed');
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $title = trim((string) $sheet->getTitle());
            $channel = $this->resolveImportSheetChannel($sheet);
            if ($channel === '') {
                continue;
            }

            $colCount = Coordinate::columnIndexFromString((string) $sheet->getHighestColumn());
            $liveVideoHasComp = $colCount >= 8;
            $influencerHasComp = $colCount >= 8;

            $highestRow = (int) $sheet->getHighestRow();
            for ($r = 2; $r <= $highestRow; $r++) {
                $entryDate = $this->excelValueToDate($sheet->getCell('A' . $r)->getValue());
                if ($entryDate === '') {
                    ++$skipped;
                    continue;
                }

                $payload = [
                    'entry_date' => $entryDate,
                    'store_id' => $storeId,
                    'account_id' => $accountId,
                    'channel_type' => $channel,
                    'sale_price_cny' => $this->parseDecimal($sheet->getCell('B' . $r)->getValue(), 0),
                    'product_cost_cny' => $this->parseDecimal($sheet->getCell('C' . $r)->getValue(), 0),
                    'cancel_rate' => $this->parseRate($sheet->getCell('D' . $r)->getValue(), 0),
                    'platform_fee_rate' => $this->parseRate($sheet->getCell('E' . $r)->getValue(), 0),
                    'ad_spend_amount' => 0,
                    'ad_compensation_amount' => 0,
                    'gmv_amount' => 0,
                    'order_count' => 0,
                    'live_hours' => 0,
                    'wage_hourly_cny' => 0,
                    'influencer_commission_rate' => 0,
                ];

                if ($channel === ProfitCalculatorService::CHANNEL_LIVE) {
                    $payload['ad_spend_amount'] = $this->parseDecimal($sheet->getCell('F' . $r)->getValue(), 0);
                    $payload['gmv_amount'] = $this->parseDecimal($sheet->getCell('G' . $r)->getValue(), 0);
                    $payload['ad_compensation_amount'] = $liveVideoHasComp
                        ? $this->parseDecimal($sheet->getCell('H' . $r)->getValue(), 0)
                        : 0.0;
                    $payload['order_count'] = $this->parseInt($sheet->getCell('I' . $r)->getValue(), 0);
                    $payload['live_hours'] = $this->parseDecimal($sheet->getCell('J' . $r)->getValue(), 0);
                    $payload['wage_hourly_cny'] = $this->parseDecimal($sheet->getCell('K' . $r)->getValue(), 0);
                } elseif ($channel === ProfitCalculatorService::CHANNEL_VIDEO) {
                    $payload['ad_spend_amount'] = $this->parseDecimal($sheet->getCell('F' . $r)->getValue(), 0);
                    $payload['gmv_amount'] = $this->parseDecimal($sheet->getCell('G' . $r)->getValue(), 0);
                    $payload['ad_compensation_amount'] = $liveVideoHasComp
                        ? $this->parseDecimal($sheet->getCell('H' . $r)->getValue(), 0)
                        : 0.0;
                    $payload['order_count'] = $this->parseInt($sheet->getCell('I' . $r)->getValue(), 0);
                } else {
                    $payload['influencer_commission_rate'] = $this->parseRate($sheet->getCell('F' . $r)->getValue(), 0);
                    if ($influencerHasComp) {
                        $payload['ad_compensation_amount'] = $this->parseDecimal($sheet->getCell('G' . $r)->getValue(), 0);
                        $payload['order_count'] = $this->parseInt($sheet->getCell('H' . $r)->getValue(), 0);
                    } else {
                        $payload['order_count'] = $this->parseInt($sheet->getCell('G' . $r)->getValue(), 0);
                    }
                }

                if ($channel === ProfitCalculatorService::CHANNEL_INFLUENCER) {
                    if ((int) $payload['order_count'] <= 0) {
                        ++$skipped;
                        continue;
                    }
                } else {
                    $hasCore = (float) $payload['ad_spend_amount'] > 0
                        || (float) $payload['gmv_amount'] > 0
                        || (int) $payload['order_count'] > 0;
                    if (!$hasCore) {
                        ++$skipped;
                        continue;
                    }
                }

                if ($adCurrencyOverride !== '') {
                    $payload['ad_spend_currency'] = $adCurrencyOverride;
                }
                if ($gmvCurrencyOverride !== '') {
                    $payload['gmv_currency'] = $gmvCurrencyOverride;
                }

                $saved = $this->upsertEntry($payload, $this->currentTenantId());
                if ($saved['ok'] ?? false) {
                    ++$imported;
                } else {
                    $errors[] = [
                        'sheet' => $title,
                        'row' => $r,
                        'message' => (string) ($saved['message'] ?? 'save_failed'),
                    ];
                }
            }
        }
        $spreadsheet->disconnectWorksheets();

        return $this->jsonOk([
            'imported' => $imported,
            'skipped' => $skipped,
            'error_count' => count($errors),
            'errors' => array_slice($errors, 0, 120),
        ]);
    }

    public function exportCsv()
    {
        [$dateFrom, $dateTo] = $this->resolveDateRange(
            (string) $this->request->param('date_from', ''),
            (string) $this->request->param('date_to', '')
        );
        $storeId = (int) $this->request->param('store_id', 0);
        $accountId = (int) $this->request->param('account_id', 0);
        $channelType = ProfitCalculatorService::normalizeChannelType((string) $this->request->param('channel_type', ''));

        $query = Db::name('growth_profit_daily_entries')->alias('e')
            ->leftJoin('growth_profit_stores s', 's.id=e.store_id')
            ->leftJoin('growth_profit_accounts a', 'a.id=e.account_id')
            ->where('e.tenant_id', $this->currentTenantId())
            ->where('e.entry_date', '>=', $dateFrom)
            ->where('e.entry_date', '<=', $dateTo)
            ->field('e.*, s.store_name, a.account_name')
            ->order('e.entry_date', 'desc')
            ->order('e.id', 'desc');
        if ($storeId > 0) {
            $query->where('e.store_id', $storeId);
        }
        if ($accountId > 0) {
            $query->where('e.account_id', $accountId);
        }
        if ($channelType !== '') {
            $query->where('e.channel_type', $channelType);
        }
        $rows = $query->limit(20000)->select()->toArray();

        try {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            $filename = 'profit_center_' . date('Ymd_His') . '.csv';
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return $this->jsonErr('export_failed', 1, null, 'common.operationFailed');
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'entry_date', 'store_name', 'account_name', 'channel_type', 'sale_price_cny', 'product_cost_cny',
                'cancel_rate', 'platform_fee_rate', 'influencer_commission_rate', 'live_hours', 'wage_hourly_cny',
                'ad_spend_amount', 'ad_spend_currency', 'ad_spend_cny',
                'ad_compensation_amount', 'ad_compensation_currency', 'ad_compensation_cny',
                'gmv_amount', 'gmv_currency', 'gmv_cny',
                'order_count', 'roi', 'net_profit_cny', 'break_even_roi', 'per_order_profit_cny', 'fx_status', 'updated_at',
            ]);
            foreach ($rows as $row) {
                fputcsv($out, [
                    (string) ($row['entry_date'] ?? ''),
                    (string) ($row['store_name'] ?? ''),
                    (string) ($row['account_name'] ?? ''),
                    (string) ($row['channel_type'] ?? ''),
                    (float) ($row['sale_price_cny'] ?? 0),
                    (float) ($row['product_cost_cny'] ?? 0),
                    (float) ($row['cancel_rate'] ?? 0),
                    (float) ($row['platform_fee_rate'] ?? 0),
                    (float) ($row['influencer_commission_rate'] ?? 0),
                    (float) ($row['live_hours'] ?? 0),
                    (float) ($row['wage_hourly_cny'] ?? 0),
                    (float) ($row['ad_spend_amount'] ?? 0),
                    (string) ($row['ad_spend_currency'] ?? ''),
                    (float) ($row['ad_spend_cny'] ?? 0),
                    (float) ($row['ad_compensation_amount'] ?? 0),
                    (string) ($row['ad_compensation_currency'] ?? ''),
                    (float) ($row['ad_compensation_cny'] ?? 0),
                    (float) ($row['gmv_amount'] ?? 0),
                    (string) ($row['gmv_currency'] ?? ''),
                    (float) ($row['gmv_cny'] ?? 0),
                    (int) ($row['order_count'] ?? 0),
                    (float) ($row['roi'] ?? 0),
                    (float) ($row['net_profit_cny'] ?? 0),
                    $row['break_even_roi'] === null ? '' : (float) $row['break_even_roi'],
                    (float) ($row['per_order_profit_cny'] ?? 0),
                    (string) ($row['fx_status'] ?? ''),
                    (string) ($row['updated_at'] ?? ''),
                ]);
            }
            fclose($out);
        } catch (\Throwable $e) {
            if (!headers_sent()) {
                return $this->jsonErr('export_failed', 1, null, 'common.operationFailed');
            }
        }
        exit;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{ok:bool,message:string,data?:array<string,mixed>}
     */
    private function upsertEntry(array $payload, int $tenantId): array
    {
        $tenantId = max(1, $tenantId);
        $id = (int) ($payload['id'] ?? 0);
        $entryDate = $this->normalizeDate((string) ($payload['entry_date'] ?? ''));
        if ($entryDate === '') {
            return ['ok' => false, 'message' => 'invalid_entry_date'];
        }
        $storeId = (int) ($payload['store_id'] ?? 0);
        $accountId = (int) ($payload['account_id'] ?? 0);
        if ($storeId <= 0) {
            return ['ok' => false, 'message' => 'invalid_store_or_account'];
        }

        $storeQuery = GrowthProfitStoreModel::where('id', $storeId);
        $storeQuery = $this->scopeTenant($storeQuery, 'growth_profit_stores');
        $store = $storeQuery->find();
        if (!$store) {
            return ['ok' => false, 'message' => 'store_not_found'];
        }

        if ($accountId <= 0) {
            $primaryAccount = Db::name('growth_profit_accounts')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->order('status', 'desc')
                ->order('id', 'asc')
                ->find();
            if (is_array($primaryAccount)) {
                $accountId = (int) ($primaryAccount['id'] ?? 0);
            }
        }
        if ($accountId <= 0) {
            return ['ok' => false, 'message' => 'store_account_required'];
        }

        $accountQuery = GrowthProfitAccountModel::where('id', $accountId);
        $accountQuery = $this->scopeTenant($accountQuery, 'growth_profit_accounts');
        $account = $accountQuery->find();
        if (!$account) {
            return ['ok' => false, 'message' => 'account_not_found'];
        }
        if ((int) $account->store_id !== $storeId) {
            return ['ok' => false, 'message' => 'account_store_mismatch'];
        }

        $channelType = ProfitCalculatorService::normalizeChannelType((string) ($payload['channel_type'] ?? ''));
        if ($channelType === '') {
            return ['ok' => false, 'message' => 'invalid_channel_type'];
        }

        $salePriceCny = round($this->parseDecimal(
            $payload['sale_price_cny'] ?? ($store->default_sale_price_cny ?? 0),
            (float) ($store->default_sale_price_cny ?? 0)
        ), 2);
        $productCostCny = round($this->parseDecimal(
            $payload['product_cost_cny'] ?? ($store->default_product_cost_cny ?? 0),
            (float) ($store->default_product_cost_cny ?? 0)
        ), 2);
        $storeDefaultCancelRate = $this->storeDefaultCancelRate($store, $channelType);
        $cancelRate = $this->parseRate(
            $payload['cancel_rate'] ?? $storeDefaultCancelRate,
            $storeDefaultCancelRate
        );
        $platformFeeRate = $this->parseRate(
            $payload['platform_fee_rate'] ?? ($store->default_platform_fee_rate ?? 0),
            (float) ($store->default_platform_fee_rate ?? 0)
        );
        $commissionRate = $this->parseRate(
            $payload['influencer_commission_rate'] ?? ($store->default_influencer_commission_rate ?? 0),
            (float) ($store->default_influencer_commission_rate ?? 0)
        );
        $liveHours = round($this->parseDecimal($payload['live_hours'] ?? 0, 0), 2);
        $wageHourly = round($this->parseDecimal(
            $payload['wage_hourly_cny'] ?? ($store->default_live_wage_hourly_cny ?? 0),
            (float) ($store->default_live_wage_hourly_cny ?? 0)
        ), 2);
        $adSpendAmount = round($this->parseDecimal($payload['ad_spend_amount'] ?? 0, 0), 2);
        $adCompensationAmount = round($this->parseDecimal($payload['ad_compensation_amount'] ?? 0, 0), 2);
        $gmvAmount = round($this->parseDecimal($payload['gmv_amount'] ?? 0, 0), 2);
        $orderCount = $this->parseInt($payload['order_count'] ?? 0, 0);

        $adSpendCurrency = FxRateService::normalizeCurrency((string) ($payload['ad_spend_currency'] ?? ($account->account_currency ?? 'CNY')));
        $adCompensationCurrency = FxRateService::normalizeCurrency((string) ($payload['ad_compensation_currency'] ?? ($account->account_currency ?? 'CNY')));
        $gmvCurrency = FxRateService::normalizeCurrency((string) ($payload['gmv_currency'] ?? ($account->default_gmv_currency ?? 'CNY')));

        $adFx = FxRateService::convertToCny($adSpendAmount, $adSpendCurrency, $entryDate, $tenantId);
        $adCompensationFx = $adCompensationAmount > 0
            ? FxRateService::convertToCny($adCompensationAmount, $adCompensationCurrency, $entryDate, $tenantId)
            : [
                'amount' => round($adCompensationAmount, 2),
                'currency' => $adCompensationCurrency,
                'rate_date' => $entryDate,
                'rate' => 1.0,
                'amount_cny' => 0.0,
                'source' => 'zero_amount',
                'status' => FxRateService::STATUS_IDENTITY,
                'is_fallback' => 0,
            ];
        $gmvFx = FxRateService::convertToCny($gmvAmount, $gmvCurrency, $entryDate, $tenantId);
        $fxStatus = $this->mergeFxStatus(
            (string) ($adFx['status'] ?? ''),
            (string) ($adCompensationFx['status'] ?? ''),
            (string) ($gmvFx['status'] ?? '')
        );

        $calc = ProfitCalculatorService::calculate([
            'channel_type' => $channelType,
            'sale_price_cny' => $salePriceCny,
            'product_cost_cny' => $productCostCny,
            'cancel_rate' => $cancelRate,
            'platform_fee_rate' => $platformFeeRate,
            'influencer_commission_rate' => $commissionRate,
            'live_hours' => $liveHours,
            'wage_hourly_cny' => $wageHourly,
            'ad_spend_cny' => (float) ($adFx['amount_cny'] ?? 0),
            'ad_compensation_cny' => (float) ($adCompensationFx['amount_cny'] ?? 0),
            'gmv_cny' => (float) ($gmvFx['amount_cny'] ?? 0),
            'order_count' => $orderCount,
        ]);
        if (!($calc['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($calc['message'] ?? 'calc_failed')];
        }
        $calculated = $calc['data'] ?? [];
        if (!is_array($calculated)) {
            return ['ok' => false, 'message' => 'calc_failed'];
        }

        $rawMetricsJson = $this->buildRawMetricsJson($payload['raw_metrics_json'] ?? ($payload['raw_metrics'] ?? null));
        $fxSnapshot = [
            'base_currency' => 'CNY',
            'ad' => $adFx,
            'ad_compensation' => $adCompensationFx,
            'gmv' => $gmvFx,
        ];
        $savePayload = [
            'tenant_id' => $tenantId,
            'entry_date' => $entryDate,
            'store_id' => $storeId,
            'account_id' => $accountId,
            'channel_type' => $channelType,
            'sale_price_cny' => $salePriceCny,
            'product_cost_cny' => $productCostCny,
            'cancel_rate' => $cancelRate,
            'platform_fee_rate' => $platformFeeRate,
            'influencer_commission_rate' => $commissionRate,
            'live_hours' => $liveHours,
            'wage_hourly_cny' => $wageHourly,
            'wage_cost_cny' => round((float) ($calculated['wage_cost_cny'] ?? 0), 2),
            'ad_spend_amount' => $adSpendAmount,
            'ad_spend_currency' => $adSpendCurrency,
            'ad_spend_cny' => round((float) ($adFx['amount_cny'] ?? 0), 2),
            'ad_compensation_amount' => $adCompensationAmount,
            'ad_compensation_currency' => $adCompensationCurrency,
            'ad_compensation_cny' => round((float) ($adCompensationFx['amount_cny'] ?? 0), 2),
            'gmv_amount' => $gmvAmount,
            'gmv_currency' => $gmvCurrency,
            'gmv_cny' => round((float) ($gmvFx['amount_cny'] ?? 0), 2),
            'order_count' => $orderCount,
            'fx_snapshot_json' => json_encode($fxSnapshot, JSON_UNESCAPED_UNICODE),
            'fx_status' => $fxStatus,
            'roi' => round((float) ($calculated['roi'] ?? 0), 6),
            'net_profit_cny' => round((float) ($calculated['net_profit_cny'] ?? 0), 2),
            'break_even_roi' => array_key_exists('break_even_roi', $calculated) ? $calculated['break_even_roi'] : null,
            'per_order_profit_cny' => round((float) ($calculated['per_order_profit_cny'] ?? 0), 2),
            'raw_metrics_json' => $rawMetricsJson,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $targetId = 0;
        if ($id > 0) {
            $existingQuery = GrowthProfitDailyEntryModel::where('id', $id);
            $existingQuery = $this->scopeTenant($existingQuery, 'growth_profit_daily_entries');
            $existing = $existingQuery->find();
            if (!$existing) {
                return ['ok' => false, 'message' => 'not_found'];
            }
            $targetId = (int) $existing->id;
        } else {
            $existing = Db::name('growth_profit_daily_entries')
                ->where('tenant_id', $tenantId)
                ->where('entry_date', $entryDate)
                ->where('store_id', $storeId)
                ->where('account_id', $accountId)
                ->where('channel_type', $channelType)
                ->find();
            if (is_array($existing)) {
                $targetId = (int) ($existing['id'] ?? 0);
            }
        }

        if ($targetId > 0) {
            Db::name('growth_profit_daily_entries')
                ->where('id', $targetId)
                ->where('tenant_id', $tenantId)
                ->update($savePayload);
        } else {
            $savePayload['created_at'] = date('Y-m-d H:i:s');
            $targetId = (int) Db::name('growth_profit_daily_entries')->insertGetId($savePayload);
        }

        $savePayload['id'] = $targetId;
        $savePayload['store_name'] = (string) ($store->store_name ?? '');
        $savePayload['account_name'] = (string) ($account->account_name ?? '');
        $savePayload['channel_label'] = $this->channelLabel($channelType);

        return ['ok' => true, 'message' => 'ok', 'data' => $savePayload];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJsonOrPost(): array
    {
        $raw = (string) $this->request->getContent();
        if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }
        return $this->request->post();
    }

    private function normalizeDate(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return '';
        }
        return date('Y-m-d', $ts);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveDateRange(string $dateFrom, string $dateTo): array
    {
        $from = $this->normalizeDate($dateFrom);
        $to = $this->normalizeDate($dateTo);
        $today = FxRateService::todayDate();
        if ($from === '' && $to === '') {
            return [$today, $today];
        }
        if ($from === '') {
            $from = $to;
        }
        if ($to === '') {
            $to = $from;
        }
        if ($from === '' || $to === '') {
            return [$today, $today];
        }
        if (strtotime($from) > strtotime($to)) {
            [$from, $to] = [$to, $from];
        }
        return [$from, $to];
    }

    private function parseDecimal($value, float $default = 0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $raw = trim((string) $value);
        if ($raw === '' || str_starts_with($raw, '=')) {
            return $default;
        }
        $raw = str_replace(',', '', $raw);
        if (!is_numeric($raw)) {
            return $default;
        }
        return (float) $raw;
    }

    private function parseInt($value, int $default = 0): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_float($value)) {
            return (int) floor($value);
        }
        $raw = trim((string) $value);
        if ($raw === '' || str_starts_with($raw, '=')) {
            return $default;
        }
        $raw = str_replace(',', '', $raw);
        if (!is_numeric($raw)) {
            return $default;
        }
        return (int) floor((float) $raw);
    }

    private function parseRate($value, float $default = 0): float
    {
        $rate = $this->parseDecimal($value, $default);
        if ($rate < 0) {
            return 0.0;
        }
        if ($rate > 1) {
            return 1.0;
        }
        return $rate;
    }

    private function parseStoreDefaultGmvCurrency(string $currency): string
    {
        return StoreCurrencyService::normalize($currency);
    }

    private function hasTableColumnCached(string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (array_key_exists($key, $cache)) {
            return (bool) $cache[$key];
        }
        try {
            $fields = Db::name($table)->getFields();
            $cache[$key] = is_array($fields) && array_key_exists($column, $fields);
        } catch (\Throwable $e) {
            $cache[$key] = false;
        }
        return (bool) $cache[$key];
    }

    private function syncStoreAccountDefaultGmvCurrency(int $storeId, string $currency): void
    {
        StoreCurrencyService::syncStoreAccountDefaultGmvCurrency($storeId, $currency, $this->currentTenantId());
    }

    private function channelLabel(string $channelType): string
    {
        $channel = ProfitCalculatorService::normalizeChannelType($channelType);
        if ($channel === ProfitCalculatorService::CHANNEL_LIVE) {
            return 'Live';
        }
        if ($channel === ProfitCalculatorService::CHANNEL_VIDEO) {
            return 'Video';
        }
        if ($channel === ProfitCalculatorService::CHANNEL_INFLUENCER) {
            return 'Influencer';
        }
        return '';
    }

    private function storeDefaultCancelRate($store, string $channelType): float
    {
        $base = (float) ($store->default_cancel_rate ?? 0);
        $channel = ProfitCalculatorService::normalizeChannelType($channelType);
        if ($channel === ProfitCalculatorService::CHANNEL_LIVE) {
            return (float) ($store->default_cancel_rate_live ?? $base);
        }
        if ($channel === ProfitCalculatorService::CHANNEL_VIDEO) {
            return (float) ($store->default_cancel_rate_video ?? $base);
        }
        if ($channel === ProfitCalculatorService::CHANNEL_INFLUENCER) {
            return (float) ($store->default_cancel_rate_influencer ?? $base);
        }

        return $base;
    }

    /**
     * @param mixed $input
     * @return string[]
     */
    private function parseCurrencyList($input): array
    {
        $items = [];
        if (is_array($input)) {
            foreach ($input as $v) {
                $items[] = FxRateService::normalizeCurrency((string) $v);
            }
        } elseif (is_string($input)) {
            $parts = preg_split('/[\s,]+/', $input, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($parts as $v) {
                $items[] = FxRateService::normalizeCurrency((string) $v);
            }
        }
        $items = array_values(array_unique(array_filter($items, static function ($v) {
            return in_array($v, FxRateService::supportedCurrencies(), true);
        })));
        if ($items === []) {
            $items = ['USD', 'VND'];
        }
        return $items;
    }


    private function resolveImportSheetChannel($sheet): string
    {
        $title = '';
        try {
            $title = trim((string) $sheet->getTitle());
        } catch (\Throwable $e) {
            $title = '';
        }
        $lower = strtolower($title);
        if (str_contains($lower, 'live')) {
            return ProfitCalculatorService::CHANNEL_LIVE;
        }
        if (str_contains($lower, 'video')) {
            return ProfitCalculatorService::CHANNEL_VIDEO;
        }
        if (str_contains($lower, 'influencer') || str_contains($lower, 'creator')) {
            return ProfitCalculatorService::CHANNEL_INFLUENCER;
        }

        $colCount = 0;
        try {
            $colCount = Coordinate::columnIndexFromString((string) $sheet->getHighestColumn());
        } catch (\Throwable $e) {
            $colCount = 0;
        }
        if ($colCount >= 10) {
            return ProfitCalculatorService::CHANNEL_LIVE;
        }
        if ($colCount >= 9) {
            return ProfitCalculatorService::CHANNEL_VIDEO;
        }
        if ($colCount >= 7) {
            return ProfitCalculatorService::CHANNEL_INFLUENCER;
        }

        return '';
    }

    private function mergeFxStatus(string ...$statuses): string
    {
        $normalized = array_values(array_filter(array_map('trim', $statuses), static function ($v) {
            return $v !== '';
        }));
        if ($normalized === []) {
            return FxRateService::STATUS_EXACT;
        }
        if (in_array(FxRateService::STATUS_MISSING, $normalized, true)) {
            return FxRateService::STATUS_MISSING;
        }
        if (in_array(FxRateService::STATUS_FALLBACK_LATEST, $normalized, true)) {
            return FxRateService::STATUS_FALLBACK_LATEST;
        }
        $allIdentity = true;
        foreach ($normalized as $status) {
            if ($status !== FxRateService::STATUS_IDENTITY) {
                $allIdentity = false;
                break;
            }
        }
        if ($allIdentity) {
            return FxRateService::STATUS_IDENTITY;
        }
        return FxRateService::STATUS_EXACT;
    }

    private function cleanNullableText($value, int $maxLen): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        return mb_substr($raw, 0, $maxLen);
    }

    /**
     * @param mixed $value
     */
    private function excelValueToDate($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_int($value) || is_float($value)) {
            return $this->excelSerialToDate((float) $value);
        }
        $raw = trim((string) $value);
        if ($raw === '' || str_starts_with($raw, '=')) {
            return '';
        }
        if (is_numeric($raw)) {
            return $this->excelSerialToDate((float) $raw);
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return '';
        }
        return date('Y-m-d', $ts);
    }

    private function excelSerialToDate(float $serial): string
    {
        if ($serial <= 0) {
            return '';
        }
        try {
            $dt = ExcelDate::excelToDateTimeObject($serial, new \DateTimeZone('Asia/Bangkok'));
            return $dt->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * @param mixed $rawMetrics
     */
    private function buildRawMetricsJson($rawMetrics): ?string
    {
        if ($rawMetrics === null || $rawMetrics === '') {
            return null;
        }
        if (is_array($rawMetrics)) {
            $json = json_encode($rawMetrics, JSON_UNESCAPED_UNICODE);
            return is_string($json) ? $json : null;
        }
        if (is_string($rawMetrics)) {
            $raw = trim($rawMetrics);
            if ($raw === '') {
                return null;
            }
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $json = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                return is_string($json) ? $json : null;
            }
            return mb_substr($raw, 0, 2000);
        }
        return null;
    }
}
