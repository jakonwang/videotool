<?php
declare(strict_types=1);

namespace app\service;

use app\model\GrowthLiveSession as GrowthLiveSessionModel;
use app\model\GrowthStoreProductCatalog as GrowthStoreProductCatalogModel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use think\facade\Db;
use think\facade\Log;

class LiveStyleAnalysisService
{
    private const WINDOW_SESSION = 'session';
    private const WINDOW_7D = 'd7';
    private const WINDOW_30D = 'd30';
    private const WINDOW_ALL = 'all';

    private const SCOPE_STORE = 'store';
    private const SCOPE_GLOBAL = 'global';

    private const TIER_BIG = 'big_hit';
    private const TIER_BEST = 'best_seller';
    private const TIER_SMALL = 'small_hit';
    private const TIER_NONE = 'normal';

    private int $tenantId;

    public function __construct(?int $tenantId = null)
    {
        $tid = $tenantId ?? AdminAuthService::tenantId();
        $this->tenantId = $tid > 0 ? $tid : 1;
    }

    /**
     * @return array{total:int,inserted:int,updated:int,skipped:int}
     */
    public function importCatalogFile(int $storeId, string $filePath, string $fileName, int $jobId = 0): array
    {
        $rows = $this->readCatalogImportRows($filePath, $fileName);
        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $index => $row) {
            $styleCode = $this->normalizeStyleCode((string) $this->pickFirstValue($row, [
                'style_code',
                'style_no',
                'style_id',
                'style',
                'product_code',
                'sku_code',
                'code',
                'stylecode',
                '编号',
                '款号',
                '货号',
                '款式编号',
                '产品编号',
                '商品编号',
                '商品编码',
            ]));
            if ($styleCode === '') {
                $skipped++;
                if ($jobId > 0 && $skipped <= 20) {
                    $sourceRow = (int) ($row['source_row'] ?? 0);
                    DataImportService::addJobLog($jobId, 'warn', 'catalog_row_skipped_missing_style', [
                        'row' => $sourceRow > 0 ? $sourceRow : ($index + 2),
                    ]);
                }
                continue;
            }

            $productName = trim((string) $this->pickFirstValue($row, [
                'product_name',
                'product_title',
                'title',
                'name',
                '商品名称',
                '商品标题',
                '产品名称',
            ]));
            $imageUrl = trim((string) $this->pickFirstValue($row, [
                'image_url',
                'image',
                'image_ref',
                'product_image',
                'image_link',
                '图片',
                '商品图片',
                '主图',
                '图',
                '图片路径',
                '图片链接',
            ]));
            if ($this->isInvalidImageValue($imageUrl)) {
                $imageUrl = '';
            }
            $imageUrl = $this->normalizeAndUploadCatalogImageUrl($imageUrl, $storeId, $styleCode, $jobId);
            $notes = trim((string) $this->pickFirstValue($row, [
                'notes',
                'remark',
                'remarks',
                'hot_type',
                'category',
                '爆款类型',
                '备注',
                '分类',
            ]));

            $existing = GrowthStoreProductCatalogModel::where('tenant_id', $this->tenantId)
                ->where('store_id', $storeId)
                ->where('style_code', $styleCode)
                ->find();

            $payload = [
                'tenant_id' => $this->tenantId,
                'store_id' => $storeId,
                'style_code' => $styleCode,
                'product_name' => $productName !== '' ? mb_substr($productName, 0, 255) : null,
                'image_url' => $imageUrl !== '' ? mb_substr($imageUrl, 0, 1024) : null,
                'notes' => $notes !== '' ? mb_substr($notes, 0, 255) : null,
                'status' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($existing) {
                Db::name('growth_store_product_catalog')
                    ->where('id', (int) $existing->id)
                    ->where('tenant_id', $this->tenantId)
                    ->update($payload);
                $updated++;
                continue;
            }

            $payload['created_at'] = date('Y-m-d H:i:s');
            Db::name('growth_store_product_catalog')->insert($payload);
            $inserted++;
        }

        if ($jobId > 0) {
            DataImportService::addJobLog($jobId, 'info', 'catalog_import_done', [
                'store_id' => $storeId,
                'inserted' => $inserted,
                'updated' => $updated,
                'skipped' => $skipped,
            ]);
        }

        return [
            'total' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{session_id:int,total:int,inserted:int,updated:int,deleted:int,matched:int,unmatched:int,session_date:string,session_name:string}
     */
    public function importLiveSessionFile(
        int $storeId,
        string $sessionDate,
        string $sessionName,
        string $filePath,
        string $fileName,
        int $jobId = 0
    ): array {
        $date = $this->normalizeDate($sessionDate);
        if ($date === '') {
            throw new \InvalidArgumentException('invalid_session_date');
        }
        $name = trim($sessionName);
        if ($name === '') {
            $name = $date;
        }
        $name = mb_substr($name, 0, 128);

        $rows = $this->readFileRows($filePath, $fileName);
        $catalogMap = $this->storeCatalogMap($storeId);
        $now = date('Y-m-d H:i:s');
        $fileHash = is_file($filePath) ? (string) @hash_file('sha256', $filePath) : '';

        $session = GrowthLiveSessionModel::where('tenant_id', $this->tenantId)
            ->where('store_id', $storeId)
            ->where('session_date', $date)
            ->where('session_name', $name)
            ->find();

        $sessionPayload = [
            'tenant_id' => $this->tenantId,
            'store_id' => $storeId,
            'session_date' => $date,
            'session_name' => $name,
            'source_file' => mb_substr($fileName, 0, 255),
            'file_hash' => mb_substr($fileHash, 0, 64),
            'import_job_id' => $jobId > 0 ? $jobId : null,
            'updated_at' => $now,
        ];

        $sessionId = 0;
        if ($session) {
            $sessionId = (int) $session->id;
            Db::name('growth_live_sessions')
                ->where('id', $sessionId)
                ->where('tenant_id', $this->tenantId)
                ->update($sessionPayload);
        } else {
            $sessionPayload['created_at'] = $now;
            $sessionId = (int) Db::name('growth_live_sessions')->insertGetId($sessionPayload);
        }

        $inserted = 0;
        $updated = 0;
        $matched = 0;
        $unmatched = 0;
        $seenProductIds = [];

        foreach ($rows as $index => $row) {
            $productId = trim((string) $this->pickFirstValue($row, [
                'product_id',
                'productid',
                'item_id',
                'sku_id',
                'goods_id',
                'goodsid',
                '鍟嗗搧id',
                '鍟嗗搧ID',
                '鍟嗗搧缂栧彿',
                '鍟嗗搧缂栫爜',
            ]));
            $productName = trim((string) $this->pickFirstValue($row, [
                'product_name',
                'product_title',
                'productname',
                'title',
                'name',
                '商品名称',
                '商品标题',
                '商品名',
            ]));

            if ($productId === '') {
                if ($productName === '') {
                    continue;
                }
                $productId = 'AUTO_' . strtoupper(substr(md5($productName . '#' . $index), 0, 16));
            }
            $productId = mb_substr($productId, 0, 128);
            $productName = mb_substr($productName, 0, 255);
            $seenProductIds[$productId] = true;

            $extractedStyle = $this->extractStyleCode($productName);
            $catalog = ($extractedStyle !== '' && isset($catalogMap[$extractedStyle])) ? $catalogMap[$extractedStyle] : null;

            $gmv = $this->toFloat($this->pickFirstValue($row, [
                'gmv',
                'sales_amount',
                'sales',
                '成交额',
                '销售额',
                '支付金额',
            ]));
            $itemsSold = $this->toInt($this->pickFirstValue($row, [
                'items_sold',
                'items_sold_count',
                'quantity_sold',
                '销量',
                '支付件数',
            ]));
            $customers = $this->toInt($this->pickFirstValue($row, [
                'customers',
                'buyers',
                '买家数',
                '支付买家数',
            ]));
            $createdSkuOrders = $this->toInt($this->pickFirstValue($row, ['created_sku_orders']));
            $skuOrders = $this->toInt($this->pickFirstValue($row, ['sku_orders']));
            $orders = $this->toInt($this->pickFirstValue($row, [
                'orders',
                'order_count',
                'paid_orders',
                '订单数',
                '支付订单数',
            ]));
            $impressions = $this->toInt($this->pickFirstValue($row, [
                'product_impressions',
                'impressions',
                '商品曝光',
                '曝光',
            ]));
            $clicks = $this->toInt($this->pickFirstValue($row, [
                'product_clicks',
                'clicks',
                '商品点击',
                '点击',
            ]));
            $atcCount = $this->toInt($this->pickFirstValue($row, [
                'add_to_cart_count',
                'add_to_cart',
                'cart_add',
                '加购',
                '加购数',
            ]));
            $paymentRate = $this->toRate($this->pickFirstValue($row, [
                'payment_rate',
                'pay_rate',
                'pay_cvr',
                '支付转化率',
                '支付率',
            ]));
            $ctr = $this->toRate($this->pickFirstValue($row, [
                'click_through_rate',
                'ctr',
                '点击率',
            ]));
            $ctorSku = $this->toRate($this->pickFirstValue($row, ['ctor_sku_orders', 'ctor_sku']));
            $ctor = $this->toRate($this->pickFirstValue($row, ['ctor']));
            $watchGpm = $this->toFloat($this->pickFirstValue($row, ['watch_gpm']));
            $aov = $this->toFloat($this->pickFirstValue($row, ['aov']));
            $availableStock = $this->toInt($this->pickFirstValue($row, ['available_stock', 'stock']));

            if ($impressions > 0 && $ctr <= 0 && $clicks > 0) {
                $ctr = $clicks / $impressions;
            }
            $atcRate = $clicks > 0 ? ($atcCount / $clicks) : 0.0;
            $derivedPayCvr = $clicks > 0 ? ($orders / $clicks) : 0.0;
            $payCvr = $clicks > 0 ? $derivedPayCvr : $paymentRate;
            if ($clicks > 0 && $orders <= 0 && $paymentRate > 0 && $paymentRate < 0.99) {
                $payCvr = $paymentRate;
            }
            $payCvr = $this->clampRate($payCvr);

            $payload = [
                'tenant_id' => $this->tenantId,
                'session_id' => $sessionId,
                'store_id' => $storeId,
                'session_date' => $date,
                'session_name' => $name,
                'product_id' => $productId,
                'product_name' => $productName,
                'extracted_style_code' => $extractedStyle !== '' ? $extractedStyle : null,
                'catalog_id' => $catalog['id'] ?? null,
                'catalog_style_code' => $catalog['style_code'] ?? null,
                'image_url' => $catalog['image_url'] ?? null,
                'is_matched' => $catalog ? 1 : 0,
                'gmv' => round($gmv, 4),
                'items_sold' => $itemsSold,
                'customers' => $customers,
                'created_sku_orders' => $createdSkuOrders,
                'sku_orders' => $skuOrders,
                'orders_count' => $orders,
                'impressions' => max(0, $impressions),
                'clicks' => max(0, $clicks),
                'add_to_cart_count' => $atcCount,
                'payment_rate' => round($paymentRate, 6),
                'ctr' => round($ctr, 6),
                'add_to_cart_rate' => round($atcRate, 6),
                'pay_cvr' => round($payCvr, 6),
                'ctor_sku' => round($ctorSku, 6),
                'ctor' => round($ctor, 6),
                'watch_gpm' => round($watchGpm, 4),
                'aov' => round($aov, 4),
                'available_stock' => $availableStock,
                'raw_payload_json' => json_encode($row, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ];

            $exists = Db::name('growth_live_product_metrics')
                ->where('tenant_id', $this->tenantId)
                ->where('store_id', $storeId)
                ->where('session_date', $date)
                ->where('session_name', $name)
                ->where('product_id', $productId)
                ->find();

            if ($exists) {
                Db::name('growth_live_product_metrics')
                    ->where('id', (int) ($exists['id'] ?? 0))
                    ->where('tenant_id', $this->tenantId)
                    ->update($payload);
                $updated++;
            } else {
                $payload['created_at'] = $now;
                Db::name('growth_live_product_metrics')->insert($payload);
                $inserted++;
            }

            if (($payload['is_matched'] ?? 0) === 1) {
                $matched++;
            } else {
                $unmatched++;
            }
        }

        $deleteQuery = Db::name('growth_live_product_metrics')
            ->where('tenant_id', $this->tenantId)
            ->where('store_id', $storeId)
            ->where('session_date', $date)
            ->where('session_name', $name);
        if ($seenProductIds !== []) {
            $deleteQuery->whereNotIn('product_id', array_keys($seenProductIds));
        }
        $deleted = (int) $deleteQuery->delete();

        $stats = Db::name('growth_live_product_metrics')
            ->where('tenant_id', $this->tenantId)
            ->where('session_id', $sessionId)
            ->fieldRaw('COUNT(*) AS total_rows, COALESCE(SUM(CASE WHEN is_matched=1 THEN 1 ELSE 0 END),0) AS matched_rows')
            ->find();
        $sessionTotal = (int) ($stats['total_rows'] ?? 0);
        $sessionMatched = (int) ($stats['matched_rows'] ?? 0);
        $sessionUnmatched = max(0, $sessionTotal - $sessionMatched);

        Db::name('growth_live_sessions')
            ->where('id', $sessionId)
            ->where('tenant_id', $this->tenantId)
            ->update([
                'total_rows' => $sessionTotal,
                'matched_rows' => $sessionMatched,
                'unmatched_rows' => $sessionUnmatched,
                'updated_at' => $now,
            ]);

        $this->rebuildSnapshotsForAnchor($storeId, $date, $sessionId);

        if ($jobId > 0) {
            DataImportService::addJobLog($jobId, 'info', 'live_session_import_done', [
                'session_id' => $sessionId,
                'store_id' => $storeId,
                'session_date' => $date,
                'session_name' => $name,
                'inserted' => $inserted,
                'updated' => $updated,
                'deleted' => $deleted,
                'matched' => $sessionMatched,
                'unmatched' => $sessionUnmatched,
            ]);
        }

        return [
            'session_id' => $sessionId,
            'total' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'deleted' => $deleted,
            'matched' => $sessionMatched,
            'unmatched' => $sessionUnmatched,
            'session_date' => $date,
            'session_name' => $name,
        ];
    }

    /**
     * @param int[] $metricIds
     * @return array{updated:int,style_code:string,catalog_id:int}
     */
    public function bindUnmatchedMetrics(int $storeId, array $metricIds, string $styleCode): array
    {
        $style = $this->normalizeStyleCode($styleCode);
        if ($style === '') {
            throw new \InvalidArgumentException('invalid_style_code');
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $metricIds), static function ($v) {
            return $v > 0;
        })));
        if ($ids === []) {
            throw new \InvalidArgumentException('invalid_metric_ids');
        }

        $catalog = Db::name('growth_store_product_catalog')
            ->where('tenant_id', $this->tenantId)
            ->where('store_id', $storeId)
            ->where('style_code', $style)
            ->find();

        $catalogId = (int) ($catalog['id'] ?? 0);
        if ($catalogId <= 0) {
            $catalogId = (int) Db::name('growth_store_product_catalog')->insertGetId([
                'tenant_id' => $this->tenantId,
                'store_id' => $storeId,
                'style_code' => $style,
                'status' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $catalog = Db::name('growth_store_product_catalog')
                ->where('id', $catalogId)
                ->where('tenant_id', $this->tenantId)
                ->find();
        }

        $affectedSessions = Db::name('growth_live_product_metrics')
            ->where('tenant_id', $this->tenantId)
            ->where('store_id', $storeId)
            ->whereIn('id', $ids)
            ->field('session_date,session_id')
            ->group('session_date,session_id')
            ->select()
            ->toArray();

        $updated = Db::name('growth_live_product_metrics')
            ->where('tenant_id', $this->tenantId)
            ->where('store_id', $storeId)
            ->whereIn('id', $ids)
            ->update([
                'catalog_id' => $catalogId,
                'catalog_style_code' => $style,
                'extracted_style_code' => $style,
                'image_url' => $catalog['image_url'] ?? null,
                'is_matched' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        foreach ($affectedSessions as $row) {
            $sid = (int) ($row['session_id'] ?? 0);
            if ($sid > 0) {
                $stats = Db::name('growth_live_product_metrics')
                    ->where('tenant_id', $this->tenantId)
                    ->where('session_id', $sid)
                    ->fieldRaw('COUNT(*) AS total_rows, COALESCE(SUM(CASE WHEN is_matched=1 THEN 1 ELSE 0 END),0) AS matched_rows')
                    ->find();
                $totalRows = (int) ($stats['total_rows'] ?? 0);
                $matchedRows = (int) ($stats['matched_rows'] ?? 0);
                Db::name('growth_live_sessions')
                    ->where('tenant_id', $this->tenantId)
                    ->where('id', $sid)
                    ->update([
                        'total_rows' => $totalRows,
                        'matched_rows' => $matchedRows,
                        'unmatched_rows' => max(0, $totalRows - $matchedRows),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }
            $date = $this->normalizeDate((string) ($row['session_date'] ?? ''));
            if ($date === '') {
                continue;
            }
            $this->rebuildSnapshotsForAnchor($storeId, $date, $sid);
        }

        return [
            'updated' => (int) $updated,
            'style_code' => $style,
            'catalog_id' => $catalogId,
        ];
    }

    /**
     * @return array{items:array<int,array<string,mixed>>,total:int,page:int,page_size:int,anchor_date:string,anchor_session_id:int,summary:array<string,int>}
     */
    public function getRankings(
        string $scope,
        int $storeId,
        string $windowType,
        string $anchorDate,
        int $sessionId,
        int $page,
        int $pageSize
    ): array {
        $scope = $this->normalizeScope($scope);
        $window = $this->normalizeWindowType($windowType);

        if ($scope === self::SCOPE_STORE && $storeId <= 0) {
            throw new \InvalidArgumentException('store_required');
        }

        $resolvedDate = $this->normalizeDate($anchorDate);
        if ($resolvedDate === '') {
            $resolvedDate = $this->latestAnchorDate($scope, $storeId);
        }
        if ($resolvedDate === '') {
            return [
                'items' => [],
                'total' => 0,
                'page' => max(1, $page),
                'page_size' => max(1, $pageSize),
                'anchor_date' => '',
                'anchor_session_id' => 0,
                'summary' => [
                    self::TIER_BIG => 0,
                    self::TIER_BEST => 0,
                    self::TIER_SMALL => 0,
                ],
            ];
        }

        $resolvedSessionId = 0;
        if ($window === self::WINDOW_SESSION) {
            $resolvedSessionId = $sessionId > 0 ? $sessionId : $this->latestSessionId($scope, $storeId, $resolvedDate);
        }
        $this->ensureSnapshot($scope, $storeId, $window, $resolvedDate, $resolvedSessionId);

        $storeKey = $scope === self::SCOPE_STORE ? $storeId : 0;
        $anchorSessionKey = $window === self::WINDOW_SESSION ? $resolvedSessionId : 0;
        $page = max(1, $page);
        $pageSize = max(1, min(200, $pageSize));

        $query = Db::name('growth_live_style_agg')
            ->where('tenant_id', $this->tenantId)
            ->where('scope', $scope)
            ->where('store_id', $storeKey)
            ->where('window_type', $window)
            ->where('window_end', $resolvedDate)
            ->where('anchor_session_id', $anchorSessionKey)
            ->order('ranking', 'asc');

        $total = (int) $query->count();
        if ($total <= 0) {
            // Fallback: when selected anchor has no snapshot rows, use latest anchor with data.
            $fallbackDate = $this->latestAnchorDate($scope, $storeId);
            if ($fallbackDate !== '' && $fallbackDate !== $resolvedDate) {
                $fallbackSessionId = $window === self::WINDOW_SESSION
                    ? ($sessionId > 0 ? $sessionId : $this->latestSessionId($scope, $storeId, $fallbackDate))
                    : 0;
                $this->ensureSnapshot($scope, $storeId, $window, $fallbackDate, $fallbackSessionId);

                $resolvedDate = $fallbackDate;
                $resolvedSessionId = $fallbackSessionId;
                $anchorSessionKey = $window === self::WINDOW_SESSION ? $resolvedSessionId : 0;

                $query = Db::name('growth_live_style_agg')
                    ->where('tenant_id', $this->tenantId)
                    ->where('scope', $scope)
                    ->where('store_id', $storeKey)
                    ->where('window_type', $window)
                    ->where('window_end', $resolvedDate)
                    ->where('anchor_session_id', $anchorSessionKey)
                    ->order('ranking', 'asc');
                $total = (int) $query->count();
            }
        }
        $rows = $query->page($page, $pageSize)->select()->toArray();

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'style_code' => (string) ($row['style_code'] ?? ''),
                'image_url' => (string) ($row['image_url'] ?? ''),
                'ranking' => (int) ($row['ranking'] ?? 0),
                'tier' => (string) ($row['tier'] ?? self::TIER_NONE),
                'score' => round((float) ($row['score'] ?? 0), 6),
                'product_count' => (int) ($row['product_count'] ?? 0),
                'session_count' => (int) ($row['session_count'] ?? 0),
                'gmv_sum' => round((float) ($row['gmv_sum'] ?? 0), 4),
                'impressions_sum' => (int) ($row['impressions_sum'] ?? 0),
                'clicks_sum' => (int) ($row['clicks_sum'] ?? 0),
                'add_to_cart_sum' => (int) ($row['add_to_cart_sum'] ?? 0),
                'orders_sum' => (int) ($row['orders_sum'] ?? 0),
                'ctr' => round((float) ($row['ctr'] ?? 0), 6),
                'add_to_cart_rate' => round((float) ($row['add_to_cart_rate'] ?? 0), 6),
                'pay_cvr' => round((float) ($row['pay_cvr'] ?? 0), 6),
            ];
        }

        $summaryRows = Db::name('growth_live_style_agg')
            ->where('tenant_id', $this->tenantId)
            ->where('scope', $scope)
            ->where('store_id', $storeKey)
            ->where('window_type', $window)
            ->where('window_end', $resolvedDate)
            ->where('anchor_session_id', $anchorSessionKey)
            ->fieldRaw('tier, COUNT(*) as cnt')
            ->group('tier')
            ->select()
            ->toArray();

        $summary = [
            self::TIER_BIG => 0,
            self::TIER_BEST => 0,
            self::TIER_SMALL => 0,
        ];
        foreach ($summaryRows as $row) {
            $tier = (string) ($row['tier'] ?? '');
            if (array_key_exists($tier, $summary)) {
                $summary[$tier] = (int) ($row['cnt'] ?? 0);
            }
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
            'anchor_date' => $resolvedDate,
            'anchor_session_id' => $resolvedSessionId,
            'summary' => $summary,
        ];
    }

    public function updateCatalogImage(int $storeId, string $styleCode, string $imageUrl): int
    {
        $style = $this->normalizeStyleCode($styleCode);
        if ($style === '') {
            throw new \InvalidArgumentException('invalid_style_code');
        }
        $image = trim($imageUrl);
        if ($image === '') {
            throw new \InvalidArgumentException('invalid_image_url');
        }
        $image = mb_substr($image, 0, 1024);

        $updated = Db::name('growth_store_product_catalog')
            ->where('tenant_id', $this->tenantId)
            ->where('store_id', $storeId)
            ->where('style_code', $style)
            ->update([
                'image_url' => $image,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        Db::name('growth_live_product_metrics')
            ->where('tenant_id', $this->tenantId)
            ->where('store_id', $storeId)
            ->where('catalog_style_code', $style)
            ->update([
                'image_url' => $image,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        Db::name('growth_live_style_agg')
            ->where('tenant_id', $this->tenantId)
            ->where('scope', self::SCOPE_STORE)
            ->where('store_id', $storeId)
            ->where('style_code', $style)
            ->update([
                'image_url' => $image,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        Db::name('growth_live_style_agg')
            ->where('tenant_id', $this->tenantId)
            ->where('scope', self::SCOPE_GLOBAL)
            ->where('store_id', 0)
            ->where('style_code', $style)
            ->update([
                'image_url' => $image,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return (int) $updated;
    }

    public function rebuildSnapshotsForAnchor(int $storeId, string $anchorDate, int $anchorSessionId = 0): void
    {
        $date = $this->normalizeDate($anchorDate);
        if ($date === '') {
            return;
        }
        $storeSessionId = $anchorSessionId > 0 ? $anchorSessionId : $this->latestSessionId(self::SCOPE_STORE, $storeId, $date);
        $globalSessionId = $anchorSessionId > 0 ? $anchorSessionId : $this->latestSessionId(self::SCOPE_GLOBAL, 0, $date);

        $this->rebuildSingleSnapshot(self::SCOPE_STORE, $storeId, self::WINDOW_SESSION, $date, $storeSessionId);
        $this->rebuildSingleSnapshot(self::SCOPE_STORE, $storeId, self::WINDOW_7D, $date, 0);
        $this->rebuildSingleSnapshot(self::SCOPE_STORE, $storeId, self::WINDOW_30D, $date, 0);
        $this->rebuildSingleSnapshot(self::SCOPE_STORE, $storeId, self::WINDOW_ALL, $date, 0);

        $this->rebuildSingleSnapshot(self::SCOPE_GLOBAL, 0, self::WINDOW_SESSION, $date, $globalSessionId);
        $this->rebuildSingleSnapshot(self::SCOPE_GLOBAL, 0, self::WINDOW_7D, $date, 0);
        $this->rebuildSingleSnapshot(self::SCOPE_GLOBAL, 0, self::WINDOW_30D, $date, 0);
        $this->rebuildSingleSnapshot(self::SCOPE_GLOBAL, 0, self::WINDOW_ALL, $date, 0);
    }

    private function ensureSnapshot(string $scope, int $storeId, string $windowType, string $anchorDate, int $anchorSessionId): void
    {
        $storeKey = $scope === self::SCOPE_STORE ? $storeId : 0;
        $sessionKey = $windowType === self::WINDOW_SESSION ? $anchorSessionId : 0;
        $exists = (int) Db::name('growth_live_style_agg')
            ->where('tenant_id', $this->tenantId)
            ->where('scope', $scope)
            ->where('store_id', $storeKey)
            ->where('window_type', $windowType)
            ->where('window_end', $anchorDate)
            ->where('anchor_session_id', $sessionKey)
            ->count();
        if ($exists > 0) {
            if ($this->snapshotNeedsPayCvrRepair($scope, $storeKey, $windowType, $anchorDate, $sessionKey)) {
                if ($scope === self::SCOPE_STORE) {
                    $this->rebuildSnapshotsForAnchor($storeId, $anchorDate, $anchorSessionId);
                } else {
                    $this->rebuildSingleSnapshot(self::SCOPE_GLOBAL, 0, $windowType, $anchorDate, $sessionKey);
                }
            }
            return;
        }

        if ($scope === self::SCOPE_STORE) {
            $this->rebuildSnapshotsForAnchor($storeId, $anchorDate, $anchorSessionId);
            return;
        }

        $this->rebuildSingleSnapshot(self::SCOPE_GLOBAL, 0, $windowType, $anchorDate, $sessionKey);
    }

    private function rebuildSingleSnapshot(
        string $scope,
        int $storeId,
        string $windowType,
        string $anchorDate,
        int $anchorSessionId
    ): void {
        [$windowStart, $windowEnd] = $this->resolveWindowRange($windowType, $anchorDate);
        if ($windowEnd === '') {
            return;
        }

        $styleExpr = "COALESCE(NULLIF(TRIM(IFNULL(m.catalog_style_code, '')), ''), NULLIF(TRIM(IFNULL(m.extracted_style_code, '')), ''))";
        $query = Db::name('growth_live_product_metrics')->alias('m')
            ->where('m.tenant_id', $this->tenantId)
            ->whereRaw($styleExpr . " IS NOT NULL");

        if ($scope === self::SCOPE_STORE) {
            $query->where('m.store_id', $storeId);
        }

        if ($windowType === self::WINDOW_SESSION) {
            if ($anchorSessionId > 0) {
                $query->where('m.session_id', $anchorSessionId);
            } else {
                $query->where('m.session_date', $anchorDate);
            }
        } elseif ($windowType === self::WINDOW_ALL) {
            $query->where('m.session_date', '<=', $windowEnd);
        } else {
            $query->where('m.session_date', '>=', $windowStart)->where('m.session_date', '<=', $windowEnd);
        }

        $rows = $query->fieldRaw('
                ' . $styleExpr . ' as style_code,
                MAX(NULLIF(m.image_url, "")) as image_url,
                COUNT(*) as product_count,
                COUNT(DISTINCT m.session_id) as session_count,
                COALESCE(SUM(m.gmv),0) as gmv_sum,
                COALESCE(SUM(m.impressions),0) as impressions_sum,
                COALESCE(SUM(m.clicks),0) as clicks_sum,
                COALESCE(SUM(m.add_to_cart_count),0) as add_to_cart_sum,
                COALESCE(SUM(m.orders_count),0) as orders_sum
            ')
            ->group($styleExpr)
            ->select()
            ->toArray();

        $prepared = [];
        foreach ($rows as $row) {
            $style = $this->normalizeAggStyleCode((string) ($row['style_code'] ?? ''));
            if ($style === '') {
                continue;
            }
            $impressions = max(0, (float) ($row['impressions_sum'] ?? 0));
            $clicks = max(0, (float) ($row['clicks_sum'] ?? 0));
            $addToCart = max(0, (float) ($row['add_to_cart_sum'] ?? 0));
            $orders = max(0, (float) ($row['orders_sum'] ?? 0));
            $ctr = $impressions > 0 ? $clicks / $impressions : 0.0;
            $atcRate = $clicks > 0 ? $addToCart / $clicks : 0.0;
            $payCvr = $clicks > 0 ? ($orders / $clicks) : 0.0;

            $prepared[] = [
                'style_code' => $style,
                'image_url' => trim((string) ($row['image_url'] ?? '')),
                'product_count' => (int) ($row['product_count'] ?? 0),
                'session_count' => (int) ($row['session_count'] ?? 0),
                'gmv_sum' => round((float) ($row['gmv_sum'] ?? 0), 4),
                'impressions_sum' => (int) $impressions,
                'clicks_sum' => (int) $clicks,
                'add_to_cart_sum' => (int) $addToCart,
                'orders_sum' => (int) $orders,
                'ctr' => round($ctr, 6),
                'add_to_cart_rate' => round($atcRate, 6),
                'pay_cvr' => round($this->clampRate($payCvr), 6),
            ];
        }

        $storeKey = $scope === self::SCOPE_STORE ? $storeId : 0;
        $sessionKey = $windowType === self::WINDOW_SESSION ? $anchorSessionId : 0;

        Db::name('growth_live_style_agg')
            ->where('tenant_id', $this->tenantId)
            ->where('scope', $scope)
            ->where('store_id', $storeKey)
            ->where('window_type', $windowType)
            ->where('window_end', $windowEnd)
            ->where('anchor_session_id', $sessionKey)
            ->delete();

        if ($prepared === []) {
            return;
        }

        $gmvScores = $this->buildRankScoreMap($prepared, 'gmv_sum');
        $ctrScores = $this->buildRankScoreMap($prepared, 'ctr');
        $atcScores = $this->buildRankScoreMap($prepared, 'add_to_cart_rate');
        $payScores = $this->buildRankScoreMap($prepared, 'pay_cvr');

        foreach ($prepared as $idx => &$row) {
            $row['score'] = round(
                (0.4 * ($gmvScores[$idx] ?? 0))
                + (0.2 * ($ctrScores[$idx] ?? 0))
                + (0.2 * ($atcScores[$idx] ?? 0))
                + (0.2 * ($payScores[$idx] ?? 0)),
                6
            );
        }
        unset($row);

        usort($prepared, static function (array $a, array $b): int {
            $scoreCmp = ((float) ($b['score'] ?? 0)) <=> ((float) ($a['score'] ?? 0));
            if ($scoreCmp !== 0) {
                return $scoreCmp;
            }
            return ((float) ($b['gmv_sum'] ?? 0)) <=> ((float) ($a['gmv_sum'] ?? 0));
        });

        $n = count($prepared);
        $now = date('Y-m-d H:i:s');
        $insertRows = [];
        foreach ($prepared as $pos => $row) {
            $percentile = $n <= 1 ? 100.0 : ((1 - ($pos / max(1, $n - 1))) * 100);
            $tier = self::TIER_NONE;
            if ($percentile >= 90) {
                $tier = self::TIER_BIG;
            } elseif ($percentile >= 70) {
                $tier = self::TIER_BEST;
            } elseif ($percentile >= 50) {
                $tier = self::TIER_SMALL;
            }

            $insertRows[] = [
                'tenant_id' => $this->tenantId,
                'scope' => $scope,
                'store_id' => $storeKey,
                'window_type' => $windowType,
                'window_start' => $windowStart !== '' ? $windowStart : null,
                'window_end' => $windowEnd !== '' ? $windowEnd : null,
                'anchor_session_id' => $sessionKey,
                'style_code' => (string) ($row['style_code'] ?? ''),
                'image_url' => (string) ($row['image_url'] ?? ''),
                'product_count' => (int) ($row['product_count'] ?? 0),
                'session_count' => (int) ($row['session_count'] ?? 0),
                'gmv_sum' => round((float) ($row['gmv_sum'] ?? 0), 4),
                'impressions_sum' => (int) ($row['impressions_sum'] ?? 0),
                'clicks_sum' => (int) ($row['clicks_sum'] ?? 0),
                'add_to_cart_sum' => (int) ($row['add_to_cart_sum'] ?? 0),
                'orders_sum' => (int) ($row['orders_sum'] ?? 0),
                'ctr' => round((float) ($row['ctr'] ?? 0), 6),
                'add_to_cart_rate' => round((float) ($row['add_to_cart_rate'] ?? 0), 6),
                'pay_cvr' => round((float) ($row['pay_cvr'] ?? 0), 6),
                'score' => round((float) ($row['score'] ?? 0), 6),
                'tier' => $tier,
                'ranking' => $pos + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        Db::name('growth_live_style_agg')->insertAll($insertRows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, float>
     */
    private function buildRankScoreMap(array $rows, string $field): array
    {
        $pairs = [];
        foreach ($rows as $idx => $row) {
            $pairs[] = [
                'idx' => $idx,
                'value' => (float) ($row[$field] ?? 0),
            ];
        }
        usort($pairs, static function (array $a, array $b): int {
            $cmp = ($b['value'] <=> $a['value']);
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a['idx'] <=> $b['idx'];
        });

        $n = count($pairs);
        $out = [];
        foreach ($pairs as $pos => $item) {
            $score = $n <= 1 ? 100.0 : ((1 - ($pos / max(1, $n - 1))) * 100);
            $out[(int) ($item['idx'] ?? 0)] = round($score, 6);
        }
        return $out;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveWindowRange(string $windowType, string $anchorDate): array
    {
        $date = $this->normalizeDate($anchorDate);
        if ($date === '') {
            return ['', ''];
        }
        if ($windowType === self::WINDOW_ALL) {
            return ['1970-01-01', $date];
        }
        if ($windowType === self::WINDOW_7D) {
            $start = date('Y-m-d', strtotime($date . ' -6 day'));
            return [$start, $date];
        }
        if ($windowType === self::WINDOW_30D) {
            $start = date('Y-m-d', strtotime($date . ' -29 day'));
            return [$start, $date];
        }
        return [$date, $date];
    }

    private function latestAnchorDate(string $scope, int $storeId): string
    {
        $query = Db::name('growth_live_product_metrics')
            ->where('tenant_id', $this->tenantId)
            ->whereRaw("(TRIM(IFNULL(catalog_style_code, '')) <> '' OR TRIM(IFNULL(extracted_style_code, '')) <> '')");
        if ($scope === self::SCOPE_STORE && $storeId > 0) {
            $query->where('store_id', $storeId);
        }
        $date = (string) ($query->max('session_date') ?? '');
        return $this->normalizeDate($date);
    }

    private function latestSessionId(string $scope, int $storeId, string $anchorDate): int
    {
        $query = Db::name('growth_live_sessions')
            ->where('tenant_id', $this->tenantId)
            ->where('session_date', $anchorDate)
            ->order('id', 'desc');
        if ($scope === self::SCOPE_STORE && $storeId > 0) {
            $query->where('store_id', $storeId);
        }
        $row = $query->find();
        return (int) ($row['id'] ?? 0);
    }

    /**
     * @return array<string, array{id:int,style_code:string,image_url:string}>
     */
    private function storeCatalogMap(int $storeId): array
    {
        $rows = Db::name('growth_store_product_catalog')
            ->where('tenant_id', $this->tenantId)
            ->where('store_id', $storeId)
            ->where('status', 1)
            ->field('id,style_code,image_url')
            ->select()
            ->toArray();
        $map = [];
        foreach ($rows as $row) {
            $style = $this->normalizeStyleCode((string) ($row['style_code'] ?? ''));
            if ($style === '') {
                continue;
            }
            $map[$style] = [
                'id' => (int) ($row['id'] ?? 0),
                'style_code' => $style,
                'image_url' => trim((string) ($row['image_url'] ?? '')),
            ];
        }
        return $map;
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function readCatalogImportRows(string $filePath, string $fileName): array
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        }

        if (in_array($ext, ['xlsx', 'xls', 'xlsm'], true) && class_exists(ProductStyleXlsxImportService::class)) {
            try {
                $rows = $this->readCatalogRowsViaStyleImporter($filePath);
                if ($rows !== []) {
                    return $rows;
                }
            } catch (\Throwable $e) {
                // Fallback to generic parser below.
            }
        }

        return $this->normalizeCatalogRows($this->readFileRows($filePath, $fileName));
    }

    /**
     * @return array<int, array<string, string|int>>
     */
    private function readCatalogRowsViaStyleImporter(string $filePath): array
    {
        $publicRoot = root_path() . 'public';
        $rows = [];
        foreach (ProductStyleXlsxImportService::iterateRows($filePath) as $record) {
            $styleRaw = trim((string) ($record['code'] ?? ''));
            $imageRaw = trim((string) ($record['imageRaw'] ?? ''));
            $noteRaw = trim((string) ($record['hot'] ?? ''));
            $imageUrl = '';

            $tempPath = trim((string) ($record['imageTemp'] ?? ''));
            if ($tempPath !== '' && is_file($tempPath)) {
                $saved = ProductStyleImportService::saveImportImageToProductsDir($tempPath, $publicRoot);
                if (is_string($saved) && $saved !== '') {
                    $imageUrl = $saved;
                }
                if (strpos($tempPath, 'style_import') !== false && is_file($tempPath)) {
                    @unlink($tempPath);
                }
            }

            if ($imageUrl === '' && !$this->isInvalidImageValue($imageRaw)) {
                $normalizedRaw = $this->normalizeCatalogImage($imageRaw);
                if (preg_match('#^https?://#i', $normalizedRaw)) {
                    $imageUrl = $this->uploadRemoteCatalogImageUrl($normalizedRaw, 0, '', 0);
                }
                if ($imageUrl === '') {
                    $resolved = ProductStyleImportService::resolveImage($imageRaw, $publicRoot);
                    $resolvedTemp = trim((string) ($resolved['temp'] ?? ''));
                    if (($resolved['ok'] ?? false) && $resolvedTemp !== '' && is_file($resolvedTemp)) {
                        $saved = ProductStyleImportService::saveImportImageToProductsDir($resolvedTemp, $publicRoot);
                        if (is_string($saved) && $saved !== '') {
                            $imageUrl = $saved;
                        }
                        if (strpos($resolvedTemp, 'style_import') !== false && is_file($resolvedTemp)) {
                            @unlink($resolvedTemp);
                        }
                    } else {
                        $imageUrl = $normalizedRaw;
                    }
                }
            }

            if ($styleRaw === '' && $imageUrl === '' && $noteRaw === '') {
                continue;
            }
            $rows[] = [
                'style_code' => $styleRaw,
                'product_name' => '',
                'image_url' => $imageUrl,
                'notes' => $noteRaw,
                'source_row' => (int) ($record['row'] ?? 0),
            ];
        }
        return $rows;
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @return array<int, array<string, string|int>>
     */
    private function normalizeCatalogRows(array $rows): array
    {
        $out = [];
        foreach ($rows as $index => $row) {
            $styleRaw = trim((string) $this->pickFirstValue($row, [
                'style_code',
                'style_no',
                'style_id',
                'style',
                'product_code',
                'sku_code',
                'code',
                'stylecode',
                '编号',
                '款号',
                '货号',
                '款式编号',
                '产品编号',
                '商品编号',
                '商品编码',
                'col_1',
            ]));
            $productName = trim((string) $this->pickFirstValue($row, [
                'product_name',
                'product_title',
                'title',
                'name',
                '商品名称',
                '商品标题',
                '产品名称',
                'col_2',
            ]));
            $imageUrl = trim((string) $this->pickFirstValue($row, [
                'image_url',
                'image',
                'image_ref',
                'product_image',
                'image_link',
                '图片',
                '商品图片',
                '主图',
                '图',
                '图片路径',
                '图片链接',
                'col_3',
            ]));
            $notes = trim((string) $this->pickFirstValue($row, [
                'notes',
                'remark',
                'remarks',
                'hot_type',
                'category',
                '爆款类型',
                '备注',
                '分类',
            ]));
            if ($this->isInvalidImageValue($imageUrl)) {
                $imageUrl = '';
            } else {
                $imageUrl = $this->normalizeCatalogImage($imageUrl);
            }

            if ($styleRaw === '' && $productName === '' && $imageUrl === '' && $notes === '') {
                continue;
            }
            $out[] = [
                'style_code' => $styleRaw,
                'product_name' => $productName,
                'image_url' => $imageUrl,
                'notes' => $notes,
                'source_row' => $index + 2,
            ];
        }
        return $out;
    }

    private function normalizeCatalogImage(string $raw): string
    {
        $value = trim($raw);
        if ($value === '') {
            return '';
        }
        if ($this->isInvalidImageValue($value)) {
            return '';
        }
        return mb_substr($value, 0, 1024);
    }

    private function normalizeAndUploadCatalogImageUrl(string $rawImageUrl, int $storeId, string $styleCode, int $jobId = 0): string
    {
        $normalized = $this->normalizeCatalogImage($rawImageUrl);
        if ($normalized === '') {
            return '';
        }
        if (!preg_match('#^https?://#i', $normalized)) {
            return $normalized;
        }
        if ($this->isQiniuHostedUrl($normalized)) {
            return $normalized;
        }
        $uploaded = $this->uploadRemoteCatalogImageUrl($normalized, $storeId, $styleCode, $jobId);
        if ($uploaded !== '') {
            return $uploaded;
        }
        return $normalized;
    }

    private function uploadRemoteCatalogImageUrl(string $url, int $storeId, string $styleCode, int $jobId = 0): string
    {
        $sourceUrl = trim($url);
        if ($sourceUrl === '' || !preg_match('#^https?://#i', $sourceUrl)) {
            return '';
        }

        try {
            $qiniu = new QiniuService();
            if (!$qiniu->isEnabled()) {
                return '';
            }

            $tmpPath = ProductStyleImportService::createTempImagePath($this->guessImageExtFromUrl($sourceUrl));
            $timeout = 25;
            $ctx = stream_context_create([
                'http' => ['timeout' => $timeout],
                'https' => ['timeout' => $timeout],
            ]);
            $bin = @file_get_contents($sourceUrl, false, $ctx);
            if ($bin === false || $bin === '') {
                return '';
            }
            @file_put_contents($tmpPath, $bin);
            if (!is_file($tmpPath) || filesize($tmpPath) <= 0) {
                return '';
            }

            $hash = md5($bin);
            $stylePart = $styleCode !== '' ? $styleCode : 'style_unknown';
            $datePart = date('Ymd');
            $key = sprintf(
                'catalog/live/%d/store_%d/%s/%s.%s',
                $this->tenantId,
                max(0, $storeId),
                $datePart,
                $hash . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $stylePart),
                $this->guessImageExtFromUrl($sourceUrl)
            );

            $ret = $qiniu->upload($tmpPath, $key);
            @unlink($tmpPath);
            if (!is_array($ret) || empty($ret['success']) || empty($ret['url'])) {
                if ($jobId > 0) {
                    DataImportService::addJobLog($jobId, 'warn', 'catalog_image_upload_failed', [
                        'store_id' => $storeId,
                        'style_code' => $styleCode,
                        'url' => $sourceUrl,
                    ]);
                }
                return '';
            }
            return mb_substr((string) $ret['url'], 0, 1024);
        } catch (\Throwable $e) {
            if ($jobId > 0) {
                DataImportService::addJobLog($jobId, 'warn', 'catalog_image_upload_error', [
                    'store_id' => $storeId,
                    'style_code' => $styleCode,
                    'url' => $sourceUrl,
                    'message' => $e->getMessage(),
                ]);
            }
            try {
                Log::warning('live_catalog_image_upload_error: ' . $e->getMessage());
            } catch (\Throwable $ignore) {
            }
            return '';
        }
    }

    private function guessImageExtFromUrl(string $url): string
    {
        $ext = strtolower((string) pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        if (!in_array($ext, ['jpg', 'png', 'gif', 'webp', 'bmp'], true)) {
            $ext = 'jpg';
        }
        return $ext;
    }

    private function isQiniuHostedUrl(string $url): bool
    {
        $domain = trim((string) (QiniuService::getMergedQiniuConfig()['domain'] ?? ''));
        if ($domain === '') {
            return false;
        }
        $urlHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        $domainHost = strtolower((string) parse_url($domain, PHP_URL_HOST));
        if ($domainHost === '') {
            $domainHost = strtolower(preg_replace('#^https?://#i', '', $domain));
            $domainHost = strtolower(trim((string) preg_replace('#/.*$#', '', $domainHost)));
        }
        return $urlHost !== '' && $domainHost !== '' && $urlHost === $domainHost;
    }

    private function isInvalidImageValue(string $value): bool
    {
        $v = strtoupper(trim($value));
        if ($v === '') {
            return true;
        }
        return in_array($v, ['#NAME?', '#VALUE!', '#N/A', 'N/A', 'NA', '-', '--'], true);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readFileRows(string $filePath, string $fileName): array
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext === '') {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        }

        if (in_array($ext, ['csv', 'txt'], true)) {
            return $this->readCsvRows($filePath);
        }
        if (in_array($ext, ['xlsx', 'xls', 'xlsm'], true)) {
            return $this->readXlsxRows($filePath);
        }
        throw new \InvalidArgumentException('unsupported_file_type');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readCsvRows(string $filePath): array
    {
        $rows = [];
        $f = new \SplFileObject($filePath, 'r');
        $f->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $f->setCsvControl(',');
        $headers = [];
        $lineNo = 0;
        while (!$f->eof()) {
            $line = $f->fgetcsv();
            if (!is_array($line)) {
                continue;
            }
            $lineNo++;
            $cells = [];
            foreach ($line as $cell) {
                $cells[] = $this->normalizeTextCell((string) $cell);
            }
            if ($lineNo === 1) {
                $headers = $this->normalizeHeaders($cells);
                continue;
            }
            if ($headers === []) {
                continue;
            }

            $assoc = [];
            $hasValue = false;
            foreach ($headers as $idx => $key) {
                $value = $cells[$idx] ?? '';
                $assoc[$key] = $value;
                if ($value !== '') {
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $rows[] = $assoc;
            }
        }
        return $rows;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readXlsxRows(string $filePath): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = (int) $sheet->getHighestDataRow();
        $highestCol = (string) $sheet->getHighestDataColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        $rows = [];
        $headers = [];
        for ($row = 1; $row <= $highestRow; $row++) {
            $cells = [];
            for ($col = 1; $col <= $highestColIndex; $col++) {
                $address = Coordinate::stringFromColumnIndex($col) . $row;
                $cell = $sheet->getCell($address);
                try {
                    $value = $cell->getCalculatedValue();
                } catch (\Throwable $e) {
                    $value = $cell->getValue();
                }
                if ($value === null || $value === '') {
                    $value = $cell->getValue();
                }
                if ($value instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
                    $value = $value->getPlainText();
                }
                $cells[] = $this->normalizeTextCell((string) $value);
            }

            if ($row === 1) {
                $headers = $this->normalizeHeaders($cells);
                continue;
            }
            if ($headers === []) {
                continue;
            }

            $assoc = [];
            $hasValue = false;
            foreach ($headers as $idx => $key) {
                $value = $cells[$idx] ?? '';
                $assoc[$key] = $value;
                if ($value !== '') {
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $rows[] = $assoc;
            }
        }

        return $rows;
    }

    /**
     * @param array<int, string> $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $idx => $header) {
            $h = mb_strtolower(trim($header), 'UTF-8');
            $h = preg_replace('/^\xEF\xBB\xBF/u', '', $h) ?? $h;
            $h = preg_replace('/[^\p{L}\p{N}]+/u', '_', $h) ?? $h;
            $h = trim($h, '_');
            if ($h === '') {
                $h = 'col_' . ($idx + 1);
            }
            $out[] = $h;
        }
        return $out;
    }

    private function normalizeTextCell(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $value = (string) preg_replace('/^\xEF\xBB\xBF/', '', $value);
        if (!mb_check_encoding($value, 'UTF-8')) {
            $detected = mb_detect_encoding($value, ['UTF-8', 'GB18030', 'GBK', 'BIG5', 'Windows-1252', 'ISO-8859-1'], true);
            if (is_string($detected) && strtoupper($detected) !== 'UTF-8') {
                $converted = @mb_convert_encoding($value, 'UTF-8', $detected);
                if (is_string($converted) && $converted !== '') {
                    $value = $converted;
                }
            }
        }
        return trim($value);
    }

    private function pickFirstValue(array $row, array $aliases): string
    {
        foreach ($aliases as $alias) {
            $key = $this->normalizeAliasKey($alias);
            if ($key !== '' && array_key_exists($key, $row)) {
                $value = trim((string) ($row[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return '';
    }

    private function normalizeAliasKey(string $key): string
    {
        $k = mb_strtolower(trim($key), 'UTF-8');
        $k = preg_replace('/[^\p{L}\p{N}]+/u', '_', $k) ?? $k;
        return trim($k, '_');
    }

    private function extractStyleCode(string $productName): string
    {
        if ($productName === '') {
            return '';
        }
        // hash 款号：#01 / #66 / #123
        if (preg_match('/#\s*(\d{1,8})\b/u', $productName, $m)) {
            return $this->normalizeHashStyleCode((string) ($m[1] ?? ''));
        }
        // 连写款号：A139 / A01 / AB189
        if (preg_match('/\b([A-Za-z]{1,12}\d{1,8})\b/u', $productName, $m)) {
            return $this->normalizeStyleCode((string) ($m[1] ?? ''));
        }
        // 空格分隔款号：A 139 / AB 01
        if (preg_match('/\b([A-Za-z]{1,12})\s+(\d{1,8})\b/u', $productName, $m)) {
            return $this->normalizeStyleCode(((string) ($m[1] ?? '')) . '-' . ((string) ($m[2] ?? '')));
        }
        if (preg_match('/\b([A-Za-z]{1,12})\s*[-_]\s*(\d{1,8})\b/u', $productName, $m)) {
            return $this->normalizeStyleCode(((string) ($m[1] ?? '')) . '-' . ((string) ($m[2] ?? '')));
        }
        if (preg_match('/\b([A-Za-z]{1,12}-\d{1,8})\b/u', $productName, $m)) {
            return $this->normalizeStyleCode((string) ($m[1] ?? ''));
        }
        return '';
    }

    private function normalizeAggStyleCode(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^#\s*(\d{1,8})$/', $raw, $m)) {
            return $this->normalizeHashStyleCode((string) ($m[1] ?? ''));
        }
        return $this->normalizeStyleCode($raw);
    }

    private function normalizeHashStyleCode(string $digits): string
    {
        $num = preg_replace('/\D+/', '', trim($digits)) ?? '';
        if ($num === '') {
            return '';
        }
        return '#' . substr($num, 0, 8);
    }

    private function normalizeStyleCode(string $value): string
    {
        $raw = strtoupper(trim($value));
        if ($raw === '') {
            return '';
        }
        $raw = preg_replace('/\s+/u', '', $raw) ?? $raw;
        $raw = str_replace(['_', '—', '－', '–'], '-', $raw);
        if (preg_match('/^([A-Z]{1,12})-?(\d{1,8})$/', $raw, $m)) {
            return ((string) ($m[1] ?? '')) . '-' . ((string) ($m[2] ?? ''));
        }
        return '';
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

    private function toFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }
        $raw = str_replace([',', ' '], '', $raw);
        $raw = preg_replace('/[^0-9\.\-]/', '', $raw) ?? $raw;
        if ($raw === '' || $raw === '-' || $raw === '.' || $raw === '-.') {
            return 0.0;
        }
        if (!is_numeric($raw)) {
            return 0.0;
        }
        return (float) $raw;
    }

    private function toInt($value): int
    {
        return (int) round($this->toFloat($value));
    }

    private function toRate($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        $raw = trim((string) $value);
        if ($raw === '') {
            return 0.0;
        }
        $compact = str_replace([',', ' '], '', $raw);
        $hasPercent = strpos($raw, '%') !== false;
        $number = $this->toFloat($compact);
        if ($hasPercent) {
            $number /= 100;
        } elseif ($number > 1 && $number <= 100) {
            $number /= 100;
        } elseif (abs($number - 1.0) < 0.0000001 && preg_match('/^[+-]?1(?:\\.0+)?$/', $compact)) {
            // Treat plain "1" as 1% for spreadsheet percentage fields.
            $number = 0.01;
        }
        return $this->clampRate((float) $number);
    }

    private function clampRate(float $value): float
    {
        if ($value < 0) {
            return 0.0;
        }
        if ($value > 1) {
            return 1.0;
        }
        return $value;
    }

    private function snapshotNeedsPayCvrRepair(
        string $scope,
        int $storeId,
        string $windowType,
        string $anchorDate,
        int $anchorSessionId
    ): bool {
        $row = Db::name('growth_live_style_agg')
            ->where('tenant_id', $this->tenantId)
            ->where('scope', $scope)
            ->where('store_id', $storeId)
            ->where('window_type', $windowType)
            ->where('window_end', $anchorDate)
            ->where('anchor_session_id', $anchorSessionId)
            ->where('pay_cvr', '>=', 0.999)
            ->where('clicks_sum', '>', 0)
            ->whereRaw('orders_sum < clicks_sum')
            ->field('id')
            ->find();

        return is_array($row) && (int) ($row['id'] ?? 0) > 0;
    }

    private function normalizeScope(string $scope): string
    {
        $v = mb_strtolower(trim($scope), 'UTF-8');
        if ($v === self::SCOPE_GLOBAL) {
            return self::SCOPE_GLOBAL;
        }
        return self::SCOPE_STORE;
    }

    private function normalizeWindowType(string $windowType): string
    {
        $v = mb_strtolower(trim($windowType), 'UTF-8');
        if (in_array($v, [self::WINDOW_SESSION, self::WINDOW_7D, self::WINDOW_30D, self::WINDOW_ALL], true)) {
            return $v;
        }
        return self::WINDOW_7D;
    }
}

