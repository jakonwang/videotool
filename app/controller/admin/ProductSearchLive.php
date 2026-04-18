<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\ChunkUploadService;
use app\service\DataImportService;
use app\service\FxRateService;
use app\service\LiveStyleAnalysisService;
use think\facade\Db;
use think\facade\Log;
use think\facade\View;

class ProductSearchLive extends BaseController
{
    private const CATALOG_ALLOWED_EXT = ['csv', 'txt', 'xlsx', 'xls', 'xlsm'];
    private const CATALOG_CHUNK_SCOPE = 'live_catalog';

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
        return View::fetch('admin/product_search/live', []);
    }

    public function storesJson()
    {
        $status = trim((string) $this->request->param('status', '1'));
        try {
            $fields = Db::name('growth_profit_stores')->getFields();
        } catch (\Throwable $e) {
            return $this->jsonOk(['items' => []]);
        }

        try {
            $query = Db::name('growth_profit_stores')->order('id', 'desc');
            $query = $this->scopeTenant($query, 'growth_profit_stores');

            $hasStatus = is_array($fields) && array_key_exists('status', $fields);
            if ($status !== '' && $hasStatus) {
                $query->where('status', (int) $status === 0 ? 0 : 1);
            }
            $rows = $query->select()->toArray();
            $storeIds = [];
            foreach ($rows as $row) {
                $sid = (int) ($row['id'] ?? 0);
                if ($sid > 0) {
                    $storeIds[] = $sid;
                }
            }
            $storeCurrencyMap = $this->loadStoreGmvCurrencyMap($storeIds);
            $items = [];
            foreach ($rows as $row) {
                $sid = (int) ($row['id'] ?? 0);
                $storeName = (string) ($row['store_name'] ?? '');
                if ($storeName === '') {
                    $storeName = (string) ($row['name'] ?? '');
                }
                $defaultGmvCurrency = (string) ($storeCurrencyMap[$sid] ?? 'VND');
                $items[] = [
                    'id' => $sid,
                    'store_code' => (string) ($row['store_code'] ?? ''),
                    'store_name' => $storeName,
                    'default_gmv_currency' => $defaultGmvCurrency,
                    'gmv_currency' => $defaultGmvCurrency,
                    'status' => $hasStatus ? (int) ($row['status'] ?? 1) : 1,
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                ];
            }

            return $this->jsonOk(['items' => $items]);
        } catch (\Throwable $e) {
            try {
                Log::error('live_stores_json_failed ' . $e->getMessage());
            } catch (\Throwable $ignore) {
            }

            return $this->jsonOk(['items' => []]);
        }
    }

    public function storeListJson()
    {
        $status = trim((string) $this->request->param('status', ''));
        $keyword = trim((string) $this->request->param('keyword', ''));
        try {
            $fields = Db::name('growth_profit_stores')->getFields();
        } catch (\Throwable $e) {
            return $this->jsonOk(['items' => []]);
        }

        $hasStatus = is_array($fields) && array_key_exists('status', $fields);
        $hasStoreCode = is_array($fields) && array_key_exists('store_code', $fields);
        $hasStoreName = is_array($fields) && array_key_exists('store_name', $fields);
        $hasLegacyName = is_array($fields) && array_key_exists('name', $fields);
        $nameCol = $hasStoreName ? 'store_name' : ($hasLegacyName ? 'name' : '');
        if ($nameCol === '') {
            return $this->jsonOk(['items' => []]);
        }

        try {
            $query = Db::name('growth_profit_stores')->order('id', 'desc');
            $query = $this->scopeTenant($query, 'growth_profit_stores');
            if ($status !== '' && $hasStatus) {
                $query->where('status', (int) $status === 0 ? 0 : 1);
            }
            if ($keyword !== '') {
                $query->where(function ($q) use ($keyword, $nameCol, $hasStoreCode): void {
                    $kw = '%' . $keyword . '%';
                    $q->where($nameCol, 'like', $kw);
                    if ($hasStoreCode) {
                        $q->whereOr('store_code', 'like', $kw);
                    }
                });
            }

            $rows = $query->select()->toArray();
            $storeIds = [];
            foreach ($rows as $row) {
                $sid = (int) ($row['id'] ?? 0);
                if ($sid > 0) {
                    $storeIds[] = $sid;
                }
            }
            $storeCurrencyMap = $this->loadStoreGmvCurrencyMap($storeIds);
            $items = [];
            foreach ($rows as $row) {
                $sid = (int) ($row['id'] ?? 0);
                $storeName = (string) ($row['store_name'] ?? '');
                if ($storeName === '') {
                    $storeName = (string) ($row['name'] ?? '');
                }
                $defaultGmvCurrency = (string) ($storeCurrencyMap[$sid] ?? 'VND');
                $items[] = [
                    'id' => $sid,
                    'store_code' => (string) ($row['store_code'] ?? ''),
                    'store_name' => $storeName,
                    'default_gmv_currency' => $defaultGmvCurrency,
                    'gmv_currency' => $defaultGmvCurrency,
                    'status' => $hasStatus ? (int) ($row['status'] ?? 1) : 1,
                    'notes' => (string) ($row['notes'] ?? ''),
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                ];
            }

            return $this->jsonOk(['items' => $items]);
        } catch (\Throwable $e) {
            try {
                Log::error('live_store_list_json_failed ' . $e->getMessage());
            } catch (\Throwable $ignore) {
            }

            return $this->jsonOk(['items' => []]);
        }
    }

    public function storeSave()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $storeNameInput = trim((string) ($payload['store_name'] ?? ''));
        if ($storeNameInput === '') {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        try {
            $fields = Db::name('growth_profit_stores')->getFields();
        } catch (\Throwable $e) {
            return $this->jsonErr('store_table_missing', 1, null, 'common.operationFailed');
        }
        $hasTenantId = is_array($fields) && array_key_exists('tenant_id', $fields);
        $hasStoreCode = is_array($fields) && array_key_exists('store_code', $fields);
        $hasStatus = is_array($fields) && array_key_exists('status', $fields);
        $hasNotes = is_array($fields) && array_key_exists('notes', $fields);
        $hasStoreName = is_array($fields) && array_key_exists('store_name', $fields);
        $hasLegacyName = is_array($fields) && array_key_exists('name', $fields);
        $hasDefaultGmvCurrency = is_array($fields) && array_key_exists('default_gmv_currency', $fields);
        $nameCol = $hasStoreName ? 'store_name' : ($hasLegacyName ? 'name' : '');
        if ($nameCol === '') {
            return $this->jsonErr('store_name_missing', 1, null, 'common.operationFailed');
        }

        $tenantId = $this->currentTenantId();
        $storeCode = trim((string) ($payload['store_code'] ?? ''));
        $status = (int) ($payload['status'] ?? 1) === 0 ? 0 : 1;
        $notes = trim((string) ($payload['notes'] ?? ''));
        $defaultGmvCurrency = $this->normalizeStoreGmvCurrency((string) ($payload['default_gmv_currency'] ?? 'VND'));

        try {
            if ($hasStoreCode && $storeCode !== '') {
                $dupQuery = Db::name('growth_profit_stores')
                    ->where('store_code', mb_substr($storeCode, 0, 64));
                if ($hasTenantId) {
                    $dupQuery->where('tenant_id', $tenantId);
                }
                if ($id > 0) {
                    $dupQuery->where('id', '<>', $id);
                }
                if ((int) $dupQuery->count() > 0) {
                    return $this->jsonErr('store_code_exists', 1, null, 'common.invalidParams');
                }
            }

            $saveData = [];
            if ($hasTenantId) {
                $saveData['tenant_id'] = $tenantId;
            }
            $saveData[$nameCol] = mb_substr($storeNameInput, 0, 128);
            if ($hasStoreCode) {
                $saveData['store_code'] = $storeCode === '' ? null : mb_substr($storeCode, 0, 64);
            }
            if ($hasStatus) {
                $saveData['status'] = $status;
            }
            if ($hasNotes) {
                $saveData['notes'] = $notes === '' ? null : mb_substr($notes, 0, 255);
            }
            if ($hasDefaultGmvCurrency) {
                $saveData['default_gmv_currency'] = $defaultGmvCurrency;
            }
            if (is_array($fields) && array_key_exists('updated_at', $fields)) {
                $saveData['updated_at'] = date('Y-m-d H:i:s');
            }

            if ($id > 0) {
                $existingQuery = Db::name('growth_profit_stores')->where('id', $id);
                if ($hasTenantId) {
                    $existingQuery->where('tenant_id', $tenantId);
                }
                $existing = $existingQuery->find();
                if (!is_array($existing)) {
                    return $this->jsonErr('store_not_found', 1, null, 'page.profitCenter.msg.storeNotFound');
                }
                $updateQuery = Db::name('growth_profit_stores')->where('id', $id);
                if ($hasTenantId) {
                    $updateQuery->where('tenant_id', $tenantId);
                }
                $updateQuery->update($saveData);
                $this->syncStoreAccountDefaultGmvCurrency($id, $defaultGmvCurrency);
            } else {
                if (is_array($fields) && array_key_exists('created_at', $fields)) {
                    $saveData['created_at'] = date('Y-m-d H:i:s');
                }
                $id = (int) Db::name('growth_profit_stores')->insertGetId($saveData);
                if ($id > 0) {
                    $this->syncStoreAccountDefaultGmvCurrency($id, $defaultGmvCurrency);
                }
            }

            $rowQuery = Db::name('growth_profit_stores')->where('id', $id);
            if ($hasTenantId) {
                $rowQuery->where('tenant_id', $tenantId);
            }
            $row = $rowQuery->find();
            if (!is_array($row)) {
                return $this->jsonErr('save_failed', 1, null, 'common.saveFailed');
            }

            $storeName = (string) ($row['store_name'] ?? '');
            if ($storeName === '') {
                $storeName = (string) ($row['name'] ?? '');
            }
            $savedDefaultGmvCurrency = $this->normalizeStoreGmvCurrency((string) ($row['default_gmv_currency'] ?? $defaultGmvCurrency));
            return $this->jsonOk([
                'item' => [
                    'id' => (int) ($row['id'] ?? 0),
                    'store_code' => (string) ($row['store_code'] ?? ''),
                    'store_name' => $storeName,
                    'default_gmv_currency' => $savedDefaultGmvCurrency,
                    'gmv_currency' => $savedDefaultGmvCurrency,
                    'status' => $hasStatus ? (int) ($row['status'] ?? 1) : 1,
                    'notes' => (string) ($row['notes'] ?? ''),
                    'updated_at' => (string) ($row['updated_at'] ?? ''),
                ],
            ], 'saved');
        } catch (\Throwable $e) {
            return $this->jsonErr('save_failed', 1, ['message' => $e->getMessage()], 'common.saveFailed');
        }
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

        try {
            $fields = Db::name('growth_profit_stores')->getFields();
        } catch (\Throwable $e) {
            return $this->jsonErr('store_table_missing', 1, null, 'common.operationFailed');
        }
        $hasTenantId = is_array($fields) && array_key_exists('tenant_id', $fields);
        $tenantId = $this->currentTenantId();
        $query = Db::name('growth_profit_stores')->where('id', $id);
        if ($hasTenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $store = $query->find();
        if (!is_array($store)) {
            return $this->jsonErr('store_not_found', 1, null, 'page.profitCenter.msg.storeNotFound');
        }

        $usageCount = 0;
        $usageTables = [
            'growth_store_product_catalog',
            'growth_live_sessions',
            'growth_live_product_metrics',
            'growth_live_style_agg',
            'growth_profit_accounts',
            'growth_profit_daily_entries',
        ];
        foreach ($usageTables as $table) {
            try {
                $tableFields = Db::name($table)->getFields();
                if (!is_array($tableFields) || !array_key_exists('store_id', $tableFields)) {
                    continue;
                }
                $usageQuery = Db::name($table)->where('store_id', $id);
                if (array_key_exists('tenant_id', $tableFields)) {
                    $usageQuery->where('tenant_id', $tenantId);
                }
                $usageCount += (int) $usageQuery->count();
            } catch (\Throwable $e) {
                continue;
            }
        }
        if ($usageCount > 0) {
            return $this->jsonErr('store_has_entries', 1, ['usage_count' => $usageCount], 'page.profitCenter.msg.storeHasEntries');
        }

        $deleteQuery = Db::name('growth_profit_stores')->where('id', $id);
        if ($hasTenantId) {
            $deleteQuery->where('tenant_id', $tenantId);
        }
        $deleteQuery->delete();
        return $this->jsonOk([], 'deleted');
    }

    public function catalogListJson()
    {
        $storeId = (int) $this->request->param('store_id', 0);
        if ($storeId <= 0) {
            return $this->jsonErr('store_required', 1, null, 'common.invalidParams');
        }
        $keyword = trim((string) $this->request->param('keyword', ''));
        $status = (string) $this->request->param('status', '');
        $page = max(1, (int) $this->request->param('page', 1));
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }

        $query = Db::name('growth_store_product_catalog')
            ->where('tenant_id', $this->currentTenantId())
            ->where('store_id', $storeId)
            ->order('id', 'desc');
        if ($keyword !== '') {
            $query->whereRaw('(style_code LIKE :kw OR product_name LIKE :kw)', ['kw' => '%' . $keyword . '%']);
        }
        if ($status !== '') {
            $query->where('status', (int) $status === 0 ? 0 : 1);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $items[] = [
                'id' => (int) ($arr['id'] ?? 0),
                'store_id' => (int) ($arr['store_id'] ?? 0),
                'style_code' => (string) ($arr['style_code'] ?? ''),
                'product_name' => (string) ($arr['product_name'] ?? ''),
                'image_url' => (string) ($arr['image_url'] ?? ''),
                'status' => (int) ($arr['status'] ?? 1),
                'notes' => (string) ($arr['notes'] ?? ''),
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

    public function catalogSave()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $storeId = (int) ($payload['store_id'] ?? 0);
        if ($storeId <= 0 || !$this->storeExists($storeId)) {
            return $this->jsonErr('store_required', 1, null, 'common.invalidParams');
        }

        $styleCode = $this->normalizeCatalogStyleCode((string) ($payload['style_code'] ?? ''));
        if ($styleCode === '') {
            return $this->jsonErr('style_code_required', 1, null, 'common.invalidParams');
        }

        $tenantId = $this->currentTenantId();
        $productName = trim((string) ($payload['product_name'] ?? ''));
        $imageUrl = trim((string) ($payload['image_url'] ?? ''));
        $notes = trim((string) ($payload['notes'] ?? ''));
        $status = (int) ($payload['status'] ?? 1) === 0 ? 0 : 1;
        $now = date('Y-m-d H:i:s');

        $dupQuery = Db::name('growth_store_product_catalog')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('style_code', $styleCode);
        if ($id > 0) {
            $dupQuery->where('id', '<>', $id);
        }
        if ((int) $dupQuery->count() > 0) {
            return $this->jsonErr('style_code_exists', 1, null, 'common.invalidParams');
        }

        $saveData = [
            'tenant_id' => $tenantId,
            'store_id' => $storeId,
            'style_code' => mb_substr($styleCode, 0, 64),
            'product_name' => $productName === '' ? null : mb_substr($productName, 0, 255),
            'image_url' => $imageUrl === '' ? null : mb_substr($imageUrl, 0, 1024),
            'status' => $status,
            'notes' => $notes === '' ? null : mb_substr($notes, 0, 255),
            'updated_at' => $now,
        ];

        try {
            if ($id > 0) {
                $existing = Db::name('growth_store_product_catalog')
                    ->where('tenant_id', $tenantId)
                    ->where('store_id', $storeId)
                    ->where('id', $id)
                    ->find();
                if (!is_array($existing)) {
                    return $this->jsonErr('catalog_not_found', 1, null, 'common.notFound');
                }
                Db::name('growth_store_product_catalog')
                    ->where('tenant_id', $tenantId)
                    ->where('store_id', $storeId)
                    ->where('id', $id)
                    ->update($saveData);
            } else {
                $saveData['created_at'] = $now;
                $id = (int) Db::name('growth_store_product_catalog')->insertGetId($saveData);
            }
            $row = Db::name('growth_store_product_catalog')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('id', $id)
                ->find();
            if (!is_array($row)) {
                return $this->jsonErr('save_failed', 1, null, 'common.saveFailed');
            }
            return $this->jsonOk(['item' => $this->formatCatalogItem($row)], 'saved');
        } catch (\Throwable $e) {
            return $this->jsonErr('save_failed', 1, ['message' => $e->getMessage()], 'common.saveFailed');
        }
    }

    public function catalogDelete()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $storeId = (int) ($payload['store_id'] ?? 0);
        if ($id <= 0 || $storeId <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        $tenantId = $this->currentTenantId();
        $row = Db::name('growth_store_product_catalog')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('id', $id)
            ->find();
        if (!is_array($row)) {
            return $this->jsonErr('catalog_not_found', 1, null, 'common.notFound');
        }

        Db::name('growth_store_product_catalog')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('id', $id)
            ->delete();

        return $this->jsonOk([], 'deleted');
    }

    public function catalogBatchDelete()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $storeId = (int) ($payload['store_id'] ?? 0);
        if ($storeId <= 0) {
            return $this->jsonErr('store_required', 1, null, 'common.invalidParams');
        }
        $ids = $payload['ids'] ?? [];
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        $idList = array_values(array_filter(array_map('intval', (array) $ids), static function ($v) {
            return $v > 0;
        }));
        if ($idList === []) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        $tenantId = $this->currentTenantId();
        $deleted = Db::name('growth_store_product_catalog')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->whereIn('id', $idList)
            ->delete();

        return $this->jsonOk(['deleted' => (int) $deleted], 'deleted');
    }

    public function catalogImport()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $storeId = (int) $this->request->param('store_id', 0);
        if ($storeId <= 0 || !$this->storeExists($storeId)) {
            return $this->jsonErr('store_required', 1, null, 'common.invalidParams');
        }

        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonErr('file_required', 1, null, 'common.pickFile');
        }
        $tmp = (string) $file->getPathname();
        if (!is_readable($tmp)) {
            return $this->jsonErr('file_unreadable', 1, null, 'common.operationFailed');
        }

        $originalName = (string) $file->getOriginalName();
        $ext = $this->resolveCatalogImportExt($originalName, $tmp);
        if (!in_array($ext, self::CATALOG_ALLOWED_EXT, true)) {
            return $this->jsonErr('unsupported_file_type', 1, null, 'common.invalidParams');
        }

        try {
            $data = $this->importCatalogWithJob($storeId, $tmp, $originalName);
            return $this->jsonOk($data, 'import_done');
        } catch (\Throwable $e) {
            Log::error('catalog_import_failed', [
                'tenant_id' => $this->currentTenantId(),
                'store_id' => $storeId,
                'file_name' => $originalName,
                'message' => $e->getMessage(),
            ]);
            return $this->jsonErr('catalog_import_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    public function catalogImportChunk()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $storeId = (int) $this->request->param('store_id', 0);
        if ($storeId <= 0 || !$this->storeExists($storeId)) {
            return $this->jsonErr('store_required', 1, null, 'common.invalidParams');
        }

        $uploadId = trim((string) $this->request->param('upload_id', ''));
        $fileName = trim((string) $this->request->param('file_name', ''));
        $chunkIndex = (int) $this->request->param('chunk_index', -1);
        $totalChunks = (int) $this->request->param('total_chunks', 0);
        $chunkFile = $this->request->file('chunk');
        if (
            $uploadId === '' ||
            $fileName === '' ||
            !$chunkFile ||
            $chunkIndex < 0 ||
            $totalChunks <= 0 ||
            $chunkIndex >= $totalChunks
        ) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        $ext = $this->resolveCatalogImportExt($fileName);
        if (!in_array($ext, self::CATALOG_ALLOWED_EXT, true)) {
            return $this->jsonErr('unsupported_file_type', 1, null, 'common.invalidParams');
        }

        $tenantId = $this->currentTenantId();
        $chunkService = new ChunkUploadService();
        $safeUploadId = '';
        $mergedPath = '';
        $shouldCleanup = false;

        try {
            $safeUploadId = $chunkService->normalizeUploadId($uploadId);
            $chunkService->saveChunk(
                self::CATALOG_CHUNK_SCOPE,
                $tenantId,
                $safeUploadId,
                $chunkIndex,
                (string) $chunkFile->getPathname()
            );

            $receivedChunks = $chunkService->countUploadedChunks(self::CATALOG_CHUNK_SCOPE, $tenantId, $safeUploadId);
            $allReady = $chunkService->hasAllChunks(self::CATALOG_CHUNK_SCOPE, $tenantId, $safeUploadId, $totalChunks);
            if (!$allReady) {
                return $this->jsonOk([
                    'completed' => false,
                    'chunk_index' => $chunkIndex,
                    'total_chunks' => $totalChunks,
                    'received_chunks' => $receivedChunks,
                ], 'chunk_received');
            }

            $shouldCleanup = true;
            $mergedPath = $chunkService->buildMergedFilePath(self::CATALOG_CHUNK_SCOPE, $tenantId, $safeUploadId, $ext);
            $chunkService->mergeChunks(self::CATALOG_CHUNK_SCOPE, $tenantId, $safeUploadId, $totalChunks, $mergedPath);
            $data = $this->importCatalogWithJob($storeId, $mergedPath, $fileName);
            $data['completed'] = true;
            $data['chunk_index'] = $chunkIndex;
            $data['total_chunks'] = $totalChunks;
            return $this->jsonOk($data, 'import_done');
        } catch (\Throwable $e) {
            Log::error('catalog_import_chunk_failed', [
                'tenant_id' => $tenantId,
                'store_id' => $storeId,
                'file_name' => $fileName,
                'upload_id' => $uploadId,
                'chunk_index' => $chunkIndex,
                'total_chunks' => $totalChunks,
                'message' => $e->getMessage(),
            ]);
            return $this->jsonErr('catalog_import_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        } finally {
            if ($mergedPath !== '' && is_file($mergedPath)) {
                @unlink($mergedPath);
            }
            if ($shouldCleanup && $safeUploadId !== '') {
                $chunkService->cleanup(self::CATALOG_CHUNK_SCOPE, $tenantId, $safeUploadId);
            }
        }
    }

    public function importCreate()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $storeId = (int) $this->request->param('store_id', 0);
        if ($storeId <= 0 || !$this->storeExists($storeId)) {
            return $this->jsonErr('store_required', 1, null, 'common.invalidParams');
        }
        $sessionDate = trim((string) $this->request->param('session_date', ''));
        $sessionName = trim((string) $this->request->param('session_name', ''));
        if ($sessionDate === '') {
            return $this->jsonErr('session_date_required', 1, null, 'common.invalidParams');
        }

        $file = $this->request->file('file');
        if (!$file) {
            return $this->jsonErr('file_required', 1, null, 'common.pickFile');
        }
        $ext = strtolower((string) $file->extension());
        if ($ext === '') {
            $ext = strtolower(pathinfo((string) $file->getOriginalName(), PATHINFO_EXTENSION));
        }
        if (!in_array($ext, ['csv', 'txt', 'xlsx', 'xls', 'xlsm'], true)) {
            return $this->jsonErr('unsupported_file_type', 1, null, 'common.invalidParams');
        }
        $tmp = (string) $file->getPathname();
        if (!is_readable($tmp)) {
            return $this->jsonErr('file_unreadable', 1, null, 'common.operationFailed');
        }

        $jobId = DataImportService::createJob('live_stream', $ext, (string) $file->getOriginalName(), null, [
            'store_id' => $storeId,
            'session_date' => $sessionDate,
            'session_name' => $sessionName,
            'file_name' => (string) $file->getOriginalName(),
        ]);

        try {
            $service = new LiveStyleAnalysisService($this->currentTenantId());
            $result = $service->importLiveSessionFile(
                $storeId,
                $sessionDate,
                $sessionName,
                $tmp,
                (string) $file->getOriginalName(),
                $jobId
            );

            $total = (int) ($result['total'] ?? 0);
            $success = (int) (($result['inserted'] ?? 0) + ($result['updated'] ?? 0));
            $failed = max(0, $total - $success);
            $status = $failed > 0 ? DataImportService::JOB_PARTIAL : DataImportService::JOB_SUCCESS;
            DataImportService::finishJob($jobId, $status, $total, $success, $failed, '');

            return $this->jsonOk([
                'job_id' => $jobId,
                'store_id' => $storeId,
                'result' => $result,
            ], 'import_done');
        } catch (\Throwable $e) {
            DataImportService::addJobLog($jobId, 'error', 'live_import_failed', ['message' => $e->getMessage()]);
            DataImportService::finishJob($jobId, DataImportService::JOB_FAILED, 0, 0, 0, 'live_import_failed');
            return $this->jsonErr('live_import_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    public function sessionsJson()
    {
        $storeId = (int) $this->request->param('store_id', 0);
        $dateFrom = trim((string) $this->request->param('date_from', ''));
        $dateTo = trim((string) $this->request->param('date_to', ''));
        $page = max(1, (int) $this->request->param('page', 1));
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }

        $query = Db::name('growth_live_sessions')
            ->alias('s')
            ->leftJoin('growth_profit_stores st', 'st.id=s.store_id')
            ->where('s.tenant_id', $this->currentTenantId())
            ->field('s.*, st.store_name')
            ->order('s.session_date', 'desc')
            ->order('s.id', 'desc');
        if ($storeId > 0) {
            $query->where('s.store_id', $storeId);
        }
        if ($dateFrom !== '') {
            $query->where('s.session_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->where('s.session_date', '<=', $dateTo);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $items[] = $this->formatSessionItem($arr);
        }

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ]);
    }

    public function sessionSave()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $storeId = (int) ($payload['store_id'] ?? 0);
        if ($id <= 0 || $storeId <= 0 || !$this->storeExists($storeId)) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        $sessionDate = $this->normalizeSessionDate((string) ($payload['session_date'] ?? ''));
        if ($sessionDate === '') {
            return $this->jsonErr('invalid_session_date', 1, null, 'common.invalidParams');
        }
        $sessionName = trim((string) ($payload['session_name'] ?? ''));
        if ($sessionName === '') {
            $sessionName = $sessionDate;
        }
        $sessionName = mb_substr($sessionName, 0, 128);

        $tenantId = $this->currentTenantId();
        $row = Db::name('growth_live_sessions')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('id', $id)
            ->find();
        if (!is_array($row)) {
            return $this->jsonErr('session_not_found', 1, null, 'common.notFound');
        }

        $dupCount = (int) Db::name('growth_live_sessions')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('session_date', $sessionDate)
            ->where('session_name', $sessionName)
            ->where('id', '<>', $id)
            ->count();
        if ($dupCount > 0) {
            return $this->jsonErr('session_duplicate', 1, null, 'common.invalidParams');
        }

        $oldDate = (string) ($row['session_date'] ?? '');
        $now = date('Y-m-d H:i:s');

        try {
            Db::startTrans();
            Db::name('growth_live_sessions')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('id', $id)
                ->update([
                    'session_date' => $sessionDate,
                    'session_name' => $sessionName,
                    'updated_at' => $now,
                ]);

            Db::name('growth_live_product_metrics')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('session_id', $id)
                ->update([
                    'session_date' => $sessionDate,
                    'session_name' => $sessionName,
                    'updated_at' => $now,
                ]);

            $stats = Db::name('growth_live_product_metrics')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('session_id', $id)
                ->fieldRaw('COUNT(*) as total_rows, SUM(CASE WHEN is_matched=1 THEN 1 ELSE 0 END) as matched_rows')
                ->find();
            $totalRows = (int) ($stats['total_rows'] ?? 0);
            $matchedRows = (int) ($stats['matched_rows'] ?? 0);
            $unmatchedRows = max(0, $totalRows - $matchedRows);

            Db::name('growth_live_sessions')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('id', $id)
                ->update([
                    'total_rows' => $totalRows,
                    'matched_rows' => $matchedRows,
                    'unmatched_rows' => $unmatchedRows,
                    'updated_at' => $now,
                ]);

            Db::commit();

            $service = new LiveStyleAnalysisService($tenantId);
            $oldDateNormalized = $this->normalizeSessionDate($oldDate);
            if ($oldDateNormalized !== '' && $oldDateNormalized !== $sessionDate) {
                $service->rebuildSnapshotsForAnchor($storeId, $oldDateNormalized, 0);
            }
            $service->rebuildSnapshotsForAnchor($storeId, $sessionDate, $id);

            $saved = Db::name('growth_live_sessions')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('id', $id)
                ->find();
            if (!is_array($saved)) {
                return $this->jsonErr('save_failed', 1, null, 'common.saveFailed');
            }
            return $this->jsonOk(['item' => $this->formatSessionItem($saved)], 'saved');
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->jsonErr('save_failed', 1, ['message' => $e->getMessage()], 'common.saveFailed');
        }
    }

    public function sessionDelete()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $id = (int) ($payload['id'] ?? 0);
        $storeId = (int) ($payload['store_id'] ?? 0);
        if ($id <= 0 || $storeId <= 0) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        $tenantId = $this->currentTenantId();
        $row = Db::name('growth_live_sessions')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->where('id', $id)
            ->find();
        if (!is_array($row)) {
            return $this->jsonErr('session_not_found', 1, null, 'common.notFound');
        }
        $sessionDate = $this->normalizeSessionDate((string) ($row['session_date'] ?? ''));

        try {
            Db::startTrans();
            $metricDeleted = (int) Db::name('growth_live_product_metrics')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('session_id', $id)
                ->delete();
            Db::name('growth_live_sessions')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->where('id', $id)
                ->delete();
            Db::commit();

            if ($sessionDate !== '') {
                $service = new LiveStyleAnalysisService($tenantId);
                $service->rebuildSnapshotsForAnchor($storeId, $sessionDate, 0);
            }
            return $this->jsonOk(['deleted_metrics' => $metricDeleted], 'deleted');
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->jsonErr('delete_failed', 1, ['message' => $e->getMessage()], 'common.deleteFailed');
        }
    }

    public function sessionBatchDelete()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $storeId = (int) ($payload['store_id'] ?? 0);
        if ($storeId <= 0) {
            return $this->jsonErr('store_required', 1, null, 'common.invalidParams');
        }
        $ids = $payload['ids'] ?? [];
        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }
        $idList = array_values(array_filter(array_map('intval', (array) $ids), static function ($v) {
            return $v > 0;
        }));
        if ($idList === []) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        $tenantId = $this->currentTenantId();
        $rows = Db::name('growth_live_sessions')
            ->where('tenant_id', $tenantId)
            ->where('store_id', $storeId)
            ->whereIn('id', $idList)
            ->field('id,session_date')
            ->select()
            ->toArray();
        if ($rows === []) {
            return $this->jsonErr('session_not_found', 1, null, 'common.notFound');
        }

        $sessionIds = [];
        $rebuildDates = [];
        foreach ($rows as $row) {
            $sid = (int) ($row['id'] ?? 0);
            if ($sid > 0) {
                $sessionIds[] = $sid;
            }
            $date = $this->normalizeSessionDate((string) ($row['session_date'] ?? ''));
            if ($date !== '') {
                $rebuildDates[$date] = true;
            }
        }
        if ($sessionIds === []) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        try {
            Db::startTrans();
            $metricDeleted = (int) Db::name('growth_live_product_metrics')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->whereIn('session_id', $sessionIds)
                ->delete();
            $sessionDeleted = (int) Db::name('growth_live_sessions')
                ->where('tenant_id', $tenantId)
                ->where('store_id', $storeId)
                ->whereIn('id', $sessionIds)
                ->delete();
            Db::commit();

            $service = new LiveStyleAnalysisService($tenantId);
            foreach (array_keys($rebuildDates) as $date) {
                $service->rebuildSnapshotsForAnchor($storeId, (string) $date, 0);
            }

            return $this->jsonOk([
                'deleted_sessions' => $sessionDeleted,
                'deleted_metrics' => $metricDeleted,
            ], 'deleted');
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->jsonErr('delete_failed', 1, ['message' => $e->getMessage()], 'common.deleteFailed');
        }
    }

    public function unmatchedJson()
    {
        $storeId = (int) $this->request->param('store_id', 0);
        if ($storeId <= 0) {
            return $this->jsonErr('store_required', 1, null, 'common.invalidParams');
        }
        $sessionId = (int) $this->request->param('session_id', 0);
        $keyword = trim((string) $this->request->param('keyword', ''));
        $page = max(1, (int) $this->request->param('page', 1));
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }

        $query = Db::name('growth_live_product_metrics')
            ->where('tenant_id', $this->currentTenantId())
            ->where('store_id', $storeId)
            ->where('is_matched', 0)
            ->order('session_date', 'desc')
            ->order('id', 'desc');
        if ($sessionId > 0) {
            $query->where('session_id', $sessionId);
        }
        if ($keyword !== '') {
            $query->whereRaw('(product_name LIKE :kw OR product_id LIKE :kw OR extracted_style_code LIKE :kw)', ['kw' => '%' . $keyword . '%']);
        }

        $list = $query->paginate([
            'list_rows' => $pageSize,
            'page' => $page,
            'query' => $this->request->param(),
        ]);

        $items = [];
        foreach ($list as $row) {
            $arr = is_array($row) ? $row : $row->toArray();
            $clicks = max(0.0, (float) ($arr['clicks'] ?? 0));
            $orders = max(0.0, (float) ($arr['orders_count'] ?? 0));
            $payCvr = $clicks > 0 ? ($orders / $clicks) : (float) ($arr['pay_cvr'] ?? 0);
            if ($payCvr < 0) {
                $payCvr = 0.0;
            } elseif ($payCvr > 1) {
                $payCvr = 1.0;
            }
            $items[] = [
                'id' => (int) ($arr['id'] ?? 0),
                'session_id' => (int) ($arr['session_id'] ?? 0),
                'session_date' => (string) ($arr['session_date'] ?? ''),
                'session_name' => (string) ($arr['session_name'] ?? ''),
                'product_id' => (string) ($arr['product_id'] ?? ''),
                'product_name' => (string) ($arr['product_name'] ?? ''),
                'extracted_style_code' => (string) ($arr['extracted_style_code'] ?? ''),
                'gmv' => round((float) ($arr['gmv'] ?? 0), 4),
                'ctr' => round((float) ($arr['ctr'] ?? 0), 6),
                'add_to_cart_rate' => round((float) ($arr['add_to_cart_rate'] ?? 0), 6),
                'pay_cvr' => round($payCvr, 6),
            ];
        }

        return $this->jsonOk([
            'items' => $items,
            'total' => (int) $list->total(),
            'page' => (int) $list->currentPage(),
            'page_size' => (int) $list->listRows(),
        ]);
    }

    public function unmatchedBind()
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $payload = $this->parseJsonOrPost();
        $storeId = (int) ($payload['store_id'] ?? 0);
        $styleCode = trim((string) ($payload['style_code'] ?? ''));
        $metricIds = $payload['metric_ids'] ?? [];
        if (!is_array($metricIds)) {
            $metricIds = [];
        }
        if ($storeId <= 0 || $styleCode === '' || $metricIds === []) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        try {
            $service = new LiveStyleAnalysisService($this->currentTenantId());
            $result = $service->bindUnmatchedMetrics($storeId, $metricIds, $styleCode);
            return $this->jsonOk($result, 'bind_done');
        } catch (\Throwable $e) {
            return $this->jsonErr('bind_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    public function rankingsJson()
    {
        $scope = trim((string) $this->request->param('scope', 'store'));
        $storeId = (int) $this->request->param('store_id', 0);
        $windowType = trim((string) $this->request->param('window_type', 'd7'));
        $anchorDate = trim((string) $this->request->param('anchor_date', ''));
        $sessionId = (int) $this->request->param('session_id', 0);
        $page = max(1, (int) $this->request->param('page', 1));
        $pageSize = (int) $this->request->param('page_size', 20);
        if ($pageSize <= 0) {
            $pageSize = 20;
        }
        if ($pageSize > 200) {
            $pageSize = 200;
        }

        try {
            $service = new LiveStyleAnalysisService($this->currentTenantId());
            $result = $service->getRankings(
                $scope,
                $storeId,
                $windowType,
                $anchorDate,
                $sessionId,
                $page,
                $pageSize
            );

            return $this->jsonOk($result);
        } catch (\Throwable $e) {
            return $this->jsonErr('ranking_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    public function styleDetailJson(string $style_code = '')
    {
        $tenantId = $this->currentTenantId();
        if ($tenantId <= 0) {
            $tenantId = 1;
        }
        $storeId = (int) $this->request->param('store_id', 0);
        $dateFrom = trim((string) $this->request->param('date_from', ''));
        $dateTo = trim((string) $this->request->param('date_to', ''));
        $styleInput = trim((string) $this->request->param('style_code', (string) $this->request->param('styleCode', '')));
        if ($styleInput === '') {
            $styleInput = trim((string) $style_code);
        }
        if ($styleInput === '') {
            $styleInput = trim((string) $this->request->route('style_code', ''));
        }
        $style = $this->normalizeCatalogStyleCode($styleInput);
        if ($style === '') {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $styleCandidates = $this->buildCatalogStyleCandidates($style);
        if ($styleCandidates === []) {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        try {
            $buildMetricsQuery = function (?int $sid = null) use ($tenantId, $styleCandidates, $dateFrom, $dateTo) {
                $q = Db::name('growth_live_product_metrics')
                    ->where('tenant_id', $tenantId)
                    ->where('is_matched', 1)
                    ->whereIn('catalog_style_code', $styleCandidates);
                if ($sid !== null && $sid > 0) {
                    $q->where('store_id', $sid);
                }
                if ($dateFrom !== '') {
                    $q->where('session_date', '>=', $dateFrom);
                }
                if ($dateTo !== '') {
                    $q->where('session_date', '<=', $dateTo);
                }
                return $q;
            };

            $resolvedStoreId = $storeId > 0 ? $storeId : 0;
            $metricsRows = (clone $buildMetricsQuery($resolvedStoreId > 0 ? $resolvedStoreId : null))
                ->field('session_id,session_date,store_id,gmv,impressions,clicks,add_to_cart_count,orders_count')
                ->select()
                ->toArray();
            if ($metricsRows === [] && $resolvedStoreId > 0) {
                $metricsRows = (clone $buildMetricsQuery(null))
                    ->field('session_id,session_date,store_id,gmv,impressions,clicks,add_to_cart_count,orders_count')
                    ->select()
                    ->toArray();
                if ($metricsRows !== []) {
                    $resolvedStoreId = 0;
                }
            }

            $metricsStoreIds = [];
            foreach ($metricsRows as $row) {
                $sid = (int) ($row['store_id'] ?? 0);
                if ($sid > 0) {
                    $metricsStoreIds[] = $sid;
                }
            }
            if ($storeId > 0) {
                $metricsStoreIds[] = $storeId;
            }
            $storeCurrencyMap = $this->loadStoreGmvCurrencyMap($metricsStoreIds);
            $fxCache = [];
            $sessionSet = [];
            $summaryCurrencySet = [];
            $summaryFxStatuses = [];
            $trendMap = [];

            $summary = [
                'product_count' => 0,
                'session_count' => 0,
                'gmv_sum' => 0.0,
                'gmv_cny_sum' => 0.0,
                'impressions_sum' => 0.0,
                'clicks_sum' => 0.0,
                'add_to_cart_sum' => 0.0,
                'orders_sum' => 0.0,
            ];

            foreach ($metricsRows as $row) {
                $sid = (int) ($row['store_id'] ?? 0);
                $sessionKey = (string) ($row['session_id'] ?? '');
                if ($sessionKey !== '') {
                    $sessionSet[$sessionKey] = true;
                }
                $sessionDate = FxRateService::normalizeDate((string) ($row['session_date'] ?? ''));
                $gmvCurrency = (string) ($storeCurrencyMap[$sid] ?? 'VND');
                $gmvAmount = (float) ($row['gmv'] ?? 0);
                $impressions = (float) ($row['impressions'] ?? 0);
                $clicks = (float) ($row['clicks'] ?? 0);
                $addToCart = (float) ($row['add_to_cart_count'] ?? 0);
                $orders = (float) ($row['orders_count'] ?? 0);

                $summary['product_count']++;
                $summary['gmv_sum'] += $gmvAmount;
                $summary['impressions_sum'] += $impressions;
                $summary['clicks_sum'] += $clicks;
                $summary['add_to_cart_sum'] += $addToCart;
                $summary['orders_sum'] += $orders;
                $summaryCurrencySet[$gmvCurrency] = true;

                $fx = $this->convertAmountToCnyCached($gmvAmount, $gmvCurrency, $sessionDate, $tenantId, $fxCache);
                $gmvCnyAmount = (float) ($fx['amount_cny'] ?? 0);
                $summary['gmv_cny_sum'] += $gmvCnyAmount;
                $summaryFxStatuses[] = (string) ($fx['status'] ?? FxRateService::STATUS_MISSING);

                if (!isset($trendMap[$sessionDate])) {
                    $trendMap[$sessionDate] = [
                        'session_date' => $sessionDate,
                        'gmv_sum' => 0.0,
                        'gmv_cny_sum' => 0.0,
                        'impressions_sum' => 0.0,
                        'clicks_sum' => 0.0,
                        'add_to_cart_sum' => 0.0,
                        'orders_sum' => 0.0,
                        'currency_set' => [],
                        'fx_statuses' => [],
                    ];
                }
                $trendMap[$sessionDate]['gmv_sum'] += $gmvAmount;
                $trendMap[$sessionDate]['gmv_cny_sum'] += $gmvCnyAmount;
                $trendMap[$sessionDate]['impressions_sum'] += $impressions;
                $trendMap[$sessionDate]['clicks_sum'] += $clicks;
                $trendMap[$sessionDate]['add_to_cart_sum'] += $addToCart;
                $trendMap[$sessionDate]['orders_sum'] += $orders;
                $trendMap[$sessionDate]['currency_set'][$gmvCurrency] = true;
                $trendMap[$sessionDate]['fx_statuses'][] = (string) ($fx['status'] ?? FxRateService::STATUS_MISSING);
            }

            $summary['session_count'] = count($sessionSet);
            $summaryCurrency = $this->resolveSummaryCurrency($summaryCurrencySet, $storeId > 0 ? (string) ($storeCurrencyMap[$storeId] ?? '') : '');
            $summaryFxStatus = $this->mergeFxStatuses($summaryFxStatuses);

            $trend = [];
            if ($trendMap !== []) {
                ksort($trendMap);
                foreach ($trendMap as $row) {
                    $exp = (float) ($row['impressions_sum'] ?? 0);
                    $clk = (float) ($row['clicks_sum'] ?? 0);
                    $add = (float) ($row['add_to_cart_sum'] ?? 0);
                    $currencySet = is_array($row['currency_set'] ?? null) ? $row['currency_set'] : [];
                    $gmvCurrency = $this->resolveSummaryCurrency($currencySet, $summaryCurrency);
                    $trend[] = [
                        'session_date' => (string) ($row['session_date'] ?? ''),
                        'gmv_sum' => round((float) ($row['gmv_sum'] ?? 0), 4),
                        'gmv_currency' => $gmvCurrency,
                        'gmv_cny_sum' => round((float) ($row['gmv_cny_sum'] ?? 0), 4),
                        'impressions_sum' => (int) $exp,
                        'clicks_sum' => (int) $clk,
                        'add_to_cart_sum' => (int) $add,
                        'orders_sum' => (int) ($row['orders_sum'] ?? 0),
                        'ctr' => $exp > 0 ? round($clk / $exp, 6) : 0,
                        'add_to_cart_rate' => $clk > 0 ? round($add / $clk, 6) : 0,
                        'fx_status' => $this->mergeFxStatuses(is_array($row['fx_statuses'] ?? null) ? $row['fx_statuses'] : []),
                    ];
                }
            }

            $buildCatalogQuery = function (?int $sid = null) use ($tenantId, $styleCandidates) {
                $q = Db::name('growth_store_product_catalog')
                    ->where('tenant_id', $tenantId)
                    ->whereIn('style_code', $styleCandidates);
                if ($sid !== null && $sid > 0) {
                    $q->where('store_id', $sid);
                }
                return $q;
            };
            $catalogRows = $buildCatalogQuery($storeId > 0 ? $storeId : null)
                ->field('id,store_id,style_code,product_name,image_url,updated_at')
                ->order('id', 'desc')
                ->select()
                ->toArray();
            if ($catalogRows === [] && $storeId > 0) {
                $catalogRows = $buildCatalogQuery(null)
                    ->field('id,store_id,style_code,product_name,image_url,updated_at')
                    ->order('id', 'desc')
                    ->select()
                    ->toArray();
            }

            try {
                Log::info(
                    'live_style_detail_debug ' . json_encode([
                        'tenant_id' => $tenantId,
                        'store_id_req' => $storeId,
                        'store_id_resolved' => $resolvedStoreId,
                        'style_input' => $styleInput,
                        'style_normalized' => $style,
                        'style_candidates' => $styleCandidates,
                        'product_count' => (int) ($summary['product_count'] ?? 0),
                        'session_count' => (int) ($summary['session_count'] ?? 0),
                        'summary_currency' => $summaryCurrency,
                        'summary_fx_status' => $summaryFxStatus,
                        'trend_count' => count($trend),
                        'catalog_count' => count($catalogRows),
                    ], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
                );
            } catch (\Throwable $e) {
            }

            $summaryImpressions = (float) ($summary['impressions_sum'] ?? 0);
            $summaryClicks = (float) ($summary['clicks_sum'] ?? 0);
            $summaryAddToCart = (float) ($summary['add_to_cart_sum'] ?? 0);

            return $this->jsonOk([
                'style_code' => $style,
                'resolved_store_id' => $resolvedStoreId,
                'currency' => [
                    'gmv_currency' => $summaryCurrency,
                    'gmv_currency_label' => $this->currencyLabel($summaryCurrency),
                    'base_currency' => FxRateService::BASE_CURRENCY,
                    'fx_status' => $summaryFxStatus,
                ],
                'summary' => [
                    'product_count' => (int) ($summary['product_count'] ?? 0),
                    'session_count' => (int) ($summary['session_count'] ?? 0),
                    'gmv_sum' => round((float) ($summary['gmv_sum'] ?? 0), 4),
                    'gmv_cny_sum' => round((float) ($summary['gmv_cny_sum'] ?? 0), 4),
                    'impressions_sum' => (int) $summaryImpressions,
                    'clicks_sum' => (int) $summaryClicks,
                    'add_to_cart_sum' => (int) $summaryAddToCart,
                    'orders_sum' => (int) ($summary['orders_sum'] ?? 0),
                    'ctr' => $summaryImpressions > 0 ? round($summaryClicks / $summaryImpressions, 6) : 0,
                    'add_to_cart_rate' => $summaryClicks > 0 ? round($summaryAddToCart / $summaryClicks, 6) : 0,
                ],
                'trend' => $trend,
                'catalog_items' => $catalogRows,
            ]);
        } catch (\Throwable $e) {
            try {
                Log::error('live_style_detail_failed ' . $e->getMessage());
            } catch (\Throwable $ignore) {
            }
            return $this->jsonErr('detail_failed', 1, ['message' => $e->getMessage()], 'common.operationFailed');
        }
    }

    public function styleImageUpdate(string $style_code = '')
    {
        if (!$this->request->isPost()) {
            return $this->jsonErr('only_post', 1, null, 'common.onlyPost');
        }
        $storeId = (int) $this->request->param('store_id', 0);
        if ($storeId <= 0) {
            return $this->jsonErr('store_required', 1, null, 'common.invalidParams');
        }

        $imageUrl = trim((string) $this->request->param('image_url', ''));
        $file = $this->request->file('image');
        if ($file && $file->isValid()) {
            $imageUrl = $this->saveCatalogImageFile($file);
        }
        if ($imageUrl === '') {
            return $this->jsonErr('image_required', 1, null, 'common.invalidParams');
        }
        $styleInput = trim((string) $this->request->param('style_code', (string) $this->request->param('styleCode', '')));
        if ($styleInput === '') {
            $styleInput = trim((string) $style_code);
        }
        if ($styleInput === '') {
            $styleInput = trim((string) $this->request->route('style_code', ''));
        }
        $style = $this->normalizeCatalogStyleCode($styleInput);
        if ($style === '') {
            return $this->jsonErr('style_code_required', 1, null, 'common.invalidParams');
        }

        try {
            $service = new LiveStyleAnalysisService($this->currentTenantId());
            $updated = $service->updateCatalogImage($storeId, $style, $imageUrl);
            return $this->jsonOk([
                'updated' => $updated,
                'image_url' => $imageUrl,
            ], 'saved');
        } catch (\Throwable $e) {
            return $this->jsonErr('save_failed', 1, ['message' => $e->getMessage()], 'common.saveFailed');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function importCatalogWithJob(int $storeId, string $tmp, string $originalName): array
    {
        $ext = $this->resolveCatalogImportExt($originalName, $tmp);
        $jobId = 0;
        try {
            $jobId = DataImportService::createJob('live_catalog', $ext, $originalName, null, [
                'store_id' => $storeId,
                'file_name' => $originalName,
            ]);
            $service = new LiveStyleAnalysisService($this->currentTenantId());
            $result = $service->importCatalogFile($storeId, $tmp, $originalName, $jobId);
            DataImportService::finishJob(
                $jobId,
                DataImportService::JOB_SUCCESS,
                (int) ($result['total'] ?? 0),
                (int) (($result['inserted'] ?? 0) + ($result['updated'] ?? 0)),
                (int) ($result['skipped'] ?? 0),
                ''
            );

            return [
                'job_id' => $jobId,
                'store_id' => $storeId,
                'result' => $result,
            ];
        } catch (\Throwable $e) {
            if ($jobId > 0) {
                DataImportService::addJobLog($jobId, 'error', 'catalog_import_failed', ['message' => $e->getMessage()]);
                DataImportService::finishJob($jobId, DataImportService::JOB_FAILED, 0, 0, 0, 'catalog_import_failed');
            }
            throw $e;
        }
    }

    private function resolveCatalogImportExt(string $fileName, string $fallbackPath = ''): string
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if ($ext === '' && $fallbackPath !== '') {
            $ext = strtolower(pathinfo($fallbackPath, PATHINFO_EXTENSION));
        }
        return $ext;
    }

    private function normalizeCatalogStyleCode(string $styleCode): string
    {
        $s = mb_strtoupper(trim($styleCode), 'UTF-8');
        if ($s === '') {
            return '';
        }
        // Normalize separators: spaces/underscores and common dash-like unicode chars.
        $s = preg_replace('/[\s_\x{2010}\x{2011}\x{2012}\x{2013}\x{2014}\x{2212}\x{FE58}\x{FE63}\x{FF0D}]+/u', '-', $s) ?? $s;
        $s = preg_replace('/-+/', '-', $s) ?? $s;
        $s = trim($s, '-');
        return mb_substr($s, 0, 64);
    }

    private function compactCatalogStyleCode(string $styleCode): string
    {
        $normalized = $this->normalizeCatalogStyleCode($styleCode);
        if ($normalized === '') {
            return '';
        }
        return str_replace('-', '', $normalized);
    }

    /**
     * @return array<int,string>
     */
    private function buildCatalogStyleCandidates(string $styleCode): array
    {
        $normalized = $this->normalizeCatalogStyleCode($styleCode);
        if ($normalized === '') {
            return [];
        }

        $compact = $this->compactCatalogStyleCode($normalized);
        $candidates = [
            $normalized,
            str_replace('-', '_', $normalized),
            str_replace('-', ' ', $normalized),
        ];
        if ($compact !== '') {
            $candidates[] = $compact;
        }

        // For patterns like A201 / A-201, always include both compact and dashed forms.
        if (preg_match('/^([A-Z]{1,16})[-_ ]?(\d{1,12})$/u', $normalized, $m) === 1) {
            $prefix = (string) ($m[1] ?? '');
            $num = (string) ($m[2] ?? '');
            if ($prefix !== '' && $num !== '') {
                $dashed = $prefix . '-' . $num;
                $plain = $prefix . $num;
                $candidates[] = $dashed;
                $candidates[] = str_replace('-', '_', $dashed);
                $candidates[] = str_replace('-', ' ', $dashed);
                $candidates[] = $plain;
            }
        }

        $uniq = [];
        foreach ($candidates as $item) {
            $v = trim((string) $item);
            if ($v === '') {
                continue;
            }
            $uniq[$v] = true;
        }

        return array_keys($uniq);
    }

    private function normalizeStoreGmvCurrency(string $currency): string
    {
        $raw = strtoupper(trim($currency));
        if (!preg_match('/^[A-Z]{3}$/', $raw)) {
            return 'VND';
        }
        return in_array($raw, FxRateService::supportedCurrencies(), true) ? $raw : 'VND';
    }

    /**
     * @param array<int,int> $storeIds
     * @return array<int,string>
     */
    private function loadStoreGmvCurrencyMap(array $storeIds): array
    {
        $ids = [];
        foreach ($storeIds as $sid) {
            $id = (int) $sid;
            if ($id > 0) {
                $ids[$id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $tenantId = $this->currentTenantId();
        $map = [];
        try {
            $storeFields = Db::name('growth_profit_stores')->getFields();
        } catch (\Throwable $e) {
            $storeFields = [];
        }
        if (is_array($storeFields) && array_key_exists('default_gmv_currency', $storeFields)) {
            $storeQuery = Db::name('growth_profit_stores')
                ->whereIn('id', array_keys($ids))
                ->field('id,default_gmv_currency');
            if (array_key_exists('tenant_id', $storeFields)) {
                $storeQuery->where('tenant_id', $tenantId);
            }
            $storeRows = $storeQuery->select()->toArray();
            foreach ($storeRows as $row) {
                $sid = (int) ($row['id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $map[$sid] = $this->normalizeStoreGmvCurrency((string) ($row['default_gmv_currency'] ?? 'VND'));
            }
        }

        if (count($map) === count($ids)) {
            return $map;
        }

        $missingStoreIds = [];
        foreach (array_keys($ids) as $sid) {
            if (!isset($map[$sid])) {
                $missingStoreIds[] = $sid;
            }
        }
        if ($missingStoreIds === []) {
            return $map;
        }

        try {
            $fields = Db::name('growth_profit_accounts')->getFields();
        } catch (\Throwable $e) {
            return $map;
        }
        if (!is_array($fields)) {
            return $map;
        }

        $currencyCol = '';
        if (array_key_exists('default_gmv_currency', $fields)) {
            $currencyCol = 'default_gmv_currency';
        } elseif (array_key_exists('account_currency', $fields)) {
            $currencyCol = 'account_currency';
        }
        if ($currencyCol === '') {
            return $map;
        }
        if (!array_key_exists('store_id', $fields)) {
            return $map;
        }

        try {
            $query = Db::name('growth_profit_accounts')
                ->whereIn('store_id', $missingStoreIds)
                ->field('id,store_id,' . $currencyCol . ' AS gmv_currency')
                ->order('id', 'desc');
            if (array_key_exists('tenant_id', $fields)) {
                $query->where('tenant_id', $tenantId);
            }
            if (array_key_exists('status', $fields)) {
                $query->where('status', 1);
            }

            $rows = $query->select()->toArray();
            foreach ($rows as $row) {
                $sid = (int) ($row['store_id'] ?? 0);
                if ($sid <= 0 || isset($map[$sid])) {
                    continue;
                }
                $map[$sid] = $this->normalizeStoreGmvCurrency((string) ($row['gmv_currency'] ?? 'VND'));
            }
        } catch (\Throwable $e) {
            // Keep store list available even if account schema differs.
        }

        return $map;
    }

    private function syncStoreAccountDefaultGmvCurrency(int $storeId, string $currency): void
    {
        if ($storeId <= 0) {
            return;
        }

        try {
            $accountFields = Db::name('growth_profit_accounts')->getFields();
        } catch (\Throwable $e) {
            return;
        }
        if (!is_array($accountFields) || !array_key_exists('default_gmv_currency', $accountFields)) {
            return;
        }

        $query = Db::name('growth_profit_accounts')
            ->where('store_id', $storeId);
        if (array_key_exists('tenant_id', $accountFields)) {
            $query->where('tenant_id', $this->currentTenantId());
        }
        $updateData = [
            'default_gmv_currency' => $this->normalizeStoreGmvCurrency($currency !== '' ? $currency : 'VND'),
        ];
        if (array_key_exists('updated_at', $accountFields)) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
        }
        try {
            $query->update($updateData);
        } catch (\Throwable $e) {
            // Keep store save success when account table shape differs.
        }
    }

    /**
     * @param array<string,array<string,mixed>> $cache
     * @return array<string,mixed>
     */
    private function convertAmountToCnyCached(
        float $amount,
        string $currency,
        string $rateDate,
        int $tenantId,
        array &$cache
    ): array {
        $cur = FxRateService::normalizeCurrency($currency);
        $date = FxRateService::normalizeDate($rateDate);
        $cacheKey = $cur . '|' . $date;

        if (!isset($cache[$cacheKey])) {
            $cache[$cacheKey] = FxRateService::convertToCny(1.0, $cur, $date, $tenantId);
        }

        $base = is_array($cache[$cacheKey]) ? $cache[$cacheKey] : [];
        $rate = (float) ($base['rate'] ?? 0);
        $status = (string) ($base['status'] ?? FxRateService::STATUS_MISSING);
        $source = (string) ($base['source'] ?? '');
        $isFallback = (int) ($base['is_fallback'] ?? 0);
        $resolvedDate = (string) ($base['rate_date'] ?? $date);
        $amt = max(0.0, (float) $amount);

        if ($cur === FxRateService::BASE_CURRENCY) {
            $rate = 1.0;
            $status = FxRateService::STATUS_IDENTITY;
            $source = 'identity';
            $isFallback = 0;
            $resolvedDate = $date;
        }
        if ($rate <= 0) {
            return [
                'amount' => round($amt, 2),
                'currency' => $cur,
                'rate_date' => $resolvedDate,
                'rate' => 0.0,
                'amount_cny' => 0.0,
                'source' => $source,
                'status' => FxRateService::STATUS_MISSING,
                'is_fallback' => 1,
            ];
        }

        return [
            'amount' => round($amt, 2),
            'currency' => $cur,
            'rate_date' => $resolvedDate,
            'rate' => $rate,
            'amount_cny' => round($amt * $rate, 2),
            'source' => $source,
            'status' => $status,
            'is_fallback' => $isFallback,
        ];
    }

    /**
     * @param array<int|string,mixed> $currencySet
     */
    private function resolveSummaryCurrency(array $currencySet, string $fallback = ''): string
    {
        $uniq = [];
        foreach ($currencySet as $k => $v) {
            if (is_string($k) && $v === true) {
                $code = strtoupper(trim($k));
            } else {
                $code = strtoupper(trim((string) $v));
            }
            if ($code === '' || $code === '0') {
                continue;
            }
            if ($code === 'MIXED') {
                return 'MIXED';
            }
            $uniq[$code] = true;
        }
        if (count($uniq) > 1) {
            return 'MIXED';
        }
        if ($uniq !== []) {
            $first = array_key_first($uniq);
            return is_string($first) ? FxRateService::normalizeCurrency($first) : FxRateService::BASE_CURRENCY;
        }
        $fb = strtoupper(trim($fallback));
        if ($fb === 'MIXED') {
            return 'MIXED';
        }
        return $fb !== '' ? FxRateService::normalizeCurrency($fb) : FxRateService::BASE_CURRENCY;
    }

    /**
     * @param array<int,string> $statuses
     */
    private function mergeFxStatuses(array $statuses): string
    {
        if ($statuses === []) {
            return FxRateService::STATUS_MISSING;
        }
        $hasFallback = false;
        $hasExact = false;
        $hasIdentity = false;
        foreach ($statuses as $status) {
            $s = trim((string) $status);
            if ($s === '') {
                continue;
            }
            if ($s === FxRateService::STATUS_MISSING) {
                return FxRateService::STATUS_MISSING;
            }
            if ($s === FxRateService::STATUS_FALLBACK_LATEST) {
                $hasFallback = true;
            } elseif ($s === FxRateService::STATUS_EXACT) {
                $hasExact = true;
            } elseif ($s === FxRateService::STATUS_IDENTITY) {
                $hasIdentity = true;
            }
        }
        if ($hasFallback) {
            return FxRateService::STATUS_FALLBACK_LATEST;
        }
        if ($hasExact) {
            return FxRateService::STATUS_EXACT;
        }
        if ($hasIdentity) {
            return FxRateService::STATUS_IDENTITY;
        }
        return FxRateService::STATUS_MISSING;
    }

    private function currencyLabel(string $currency): string
    {
        $cur = strtoupper(trim($currency));
        if ($cur === 'USD') {
            return '美元 (USD)';
        }
        if ($cur === 'VND') {
            return '越南盾 (VND)';
        }
        if ($cur === 'MIXED') {
            return '混合币种';
        }
        return '人民币 (CNY)';
    }

    /**
     * @param array<string,mixed> $arr
     * @return array<string,mixed>
     */
    private function formatCatalogItem(array $arr): array
    {
        return [
            'id' => (int) ($arr['id'] ?? 0),
            'store_id' => (int) ($arr['store_id'] ?? 0),
            'style_code' => (string) ($arr['style_code'] ?? ''),
            'product_name' => (string) ($arr['product_name'] ?? ''),
            'image_url' => (string) ($arr['image_url'] ?? ''),
            'status' => (int) ($arr['status'] ?? 1),
            'notes' => (string) ($arr['notes'] ?? ''),
            'updated_at' => (string) ($arr['updated_at'] ?? ''),
        ];
    }

    private function normalizeSessionDate(string $raw): string
    {
        $v = trim($raw);
        if ($v === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1) {
            return $v;
        }
        $ts = strtotime($v);
        if ($ts === false) {
            return '';
        }
        return date('Y-m-d', $ts);
    }

    /**
     * @param array<string,mixed> $arr
     * @return array<string,mixed>
     */
    private function formatSessionItem(array $arr): array
    {
        return [
            'id' => (int) ($arr['id'] ?? 0),
            'store_id' => (int) ($arr['store_id'] ?? 0),
            'store_name' => (string) ($arr['store_name'] ?? ''),
            'session_date' => (string) ($arr['session_date'] ?? ''),
            'session_name' => (string) ($arr['session_name'] ?? ''),
            'source_file' => (string) ($arr['source_file'] ?? ''),
            'total_rows' => (int) ($arr['total_rows'] ?? 0),
            'matched_rows' => (int) ($arr['matched_rows'] ?? 0),
            'unmatched_rows' => (int) ($arr['unmatched_rows'] ?? 0),
            'updated_at' => (string) ($arr['updated_at'] ?? ''),
        ];
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

    private function storeExists(int $storeId): bool
    {
        if ($storeId <= 0) {
            return false;
        }
        $query = Db::name('growth_profit_stores')->where('id', $storeId);
        $query = $this->scopeTenant($query, 'growth_profit_stores');
        $count = (int) $query->count();
        return $count > 0;
    }

    private function saveCatalogImageFile($file): string
    {
        $ext = strtolower((string) $file->extension());
        if ($ext === '') {
            $ext = strtolower(pathinfo((string) $file->getOriginalName(), PATHINFO_EXTENSION));
        }
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            throw new \InvalidArgumentException('invalid_image_ext');
        }
        $publicRoot = root_path() . 'public';
        $subDir = 'uploads' . DIRECTORY_SEPARATOR . 'live_catalog' . DIRECTORY_SEPARATOR . date('Ymd');
        $absDir = $publicRoot . DIRECTORY_SEPARATOR . $subDir;
        if (!is_dir($absDir)) {
            @mkdir($absDir, 0755, true);
        }
        if (!is_dir($absDir)) {
            throw new \RuntimeException('mkdir_failed');
        }

        $name = date('His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $absPath = $absDir . DIRECTORY_SEPARATOR . $name;
        if (!@copy((string) $file->getPathname(), $absPath)) {
            throw new \RuntimeException('save_file_failed');
        }

        $rel = str_replace('\\', '/', $subDir . DIRECTORY_SEPARATOR . $name);
        return '/' . ltrim($rel, '/');
    }
}

