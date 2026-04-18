<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\service\ChunkUploadService;
use app\service\DataImportService;
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

        $query = Db::name('growth_profit_stores')->order('id', 'desc');
        $query = $this->scopeTenant($query, 'growth_profit_stores');

        $hasStatus = is_array($fields) && array_key_exists('status', $fields);
        if ($status !== '' && $hasStatus) {
            $query->where('status', (int) $status === 0 ? 0 : 1);
        }
        $rows = $query->select()->toArray();
        $items = [];
        foreach ($rows as $row) {
            $storeName = (string) ($row['store_name'] ?? '');
            if ($storeName === '') {
                $storeName = (string) ($row['name'] ?? '');
            }
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'store_code' => (string) ($row['store_code'] ?? ''),
                'store_name' => $storeName,
                'status' => $hasStatus ? (int) ($row['status'] ?? 1) : 1,
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }
        return $this->jsonOk(['items' => $items]);
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
        $items = [];
        foreach ($rows as $row) {
            $storeName = (string) ($row['store_name'] ?? '');
            if ($storeName === '') {
                $storeName = (string) ($row['name'] ?? '');
            }
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'store_code' => (string) ($row['store_code'] ?? ''),
                'store_name' => $storeName,
                'status' => $hasStatus ? (int) ($row['status'] ?? 1) : 1,
                'notes' => (string) ($row['notes'] ?? ''),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
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
        $nameCol = $hasStoreName ? 'store_name' : ($hasLegacyName ? 'name' : '');
        if ($nameCol === '') {
            return $this->jsonErr('store_name_missing', 1, null, 'common.operationFailed');
        }

        $tenantId = $this->currentTenantId();
        $storeCode = trim((string) ($payload['store_code'] ?? ''));
        $status = (int) ($payload['status'] ?? 1) === 0 ? 0 : 1;
        $notes = trim((string) ($payload['notes'] ?? ''));

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
            } else {
                if (is_array($fields) && array_key_exists('created_at', $fields)) {
                    $saveData['created_at'] = date('Y-m-d H:i:s');
                }
                $id = (int) Db::name('growth_profit_stores')->insertGetId($saveData);
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
            return $this->jsonOk([
                'item' => [
                    'id' => (int) ($row['id'] ?? 0),
                    'store_code' => (string) ($row['store_code'] ?? ''),
                    'store_name' => $storeName,
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
        $styleInput = trim((string) $style_code);
        if ($styleInput === '') {
            $styleInput = trim((string) $this->request->route('style_code', ''));
        }
        if ($styleInput === '') {
            $styleInput = trim((string) $this->request->param('style_code', (string) $this->request->param('styleCode', '')));
        }
        $style = $this->normalizeCatalogStyleCode($styleInput);
        if ($style === '') {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }
        $styleCompact = $this->compactCatalogStyleCode($style);
        if ($styleCompact === '') {
            return $this->jsonErr('invalid_params', 1, null, 'common.invalidParams');
        }

        $query = Db::name('growth_live_product_metrics')
            ->where('tenant_id', $tenantId)
            ->where('is_matched', 1)
            ->where(function ($q) use ($style, $styleCompact): void {
                $q->where('catalog_style_code', $style)
                    ->whereOrRaw(
                        'REPLACE(REPLACE(REPLACE(UPPER(TRIM(catalog_style_code)), "-", ""), "_", ""), " ", "") = :style_compact',
                        ['style_compact' => $styleCompact]
                    );
            });
        if ($storeId > 0) {
            $query->where('store_id', $storeId);
        }
        if ($dateFrom !== '') {
            $query->where('session_date', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->where('session_date', '<=', $dateTo);
        }

        $totalRow = $query->fieldRaw('
            COUNT(*) as product_count,
            COUNT(DISTINCT session_id) as session_count,
            COALESCE(SUM(gmv),0) as gmv_sum,
            COALESCE(SUM(impressions),0) as impressions_sum,
            COALESCE(SUM(clicks),0) as clicks_sum,
            COALESCE(SUM(add_to_cart_count),0) as add_to_cart_sum,
            COALESCE(SUM(orders_count),0) as orders_sum
        ')->find();
        $impressions = (float) ($totalRow['impressions_sum'] ?? 0);
        $clicks = (float) ($totalRow['clicks_sum'] ?? 0);
        $atc = (float) ($totalRow['add_to_cart_sum'] ?? 0);

        $trendRows = $query->fieldRaw('
            session_date,
            COALESCE(SUM(gmv),0) as gmv_sum,
            COALESCE(SUM(impressions),0) as impressions_sum,
            COALESCE(SUM(clicks),0) as clicks_sum,
            COALESCE(SUM(add_to_cart_count),0) as add_to_cart_sum,
            COALESCE(SUM(orders_count),0) as orders_sum
        ')
            ->group('session_date')
            ->order('session_date', 'asc')
            ->select()
            ->toArray();

        $trend = [];
        foreach ($trendRows as $row) {
            $exp = (float) ($row['impressions_sum'] ?? 0);
            $clk = (float) ($row['clicks_sum'] ?? 0);
            $add = (float) ($row['add_to_cart_sum'] ?? 0);
            $trend[] = [
                'session_date' => (string) ($row['session_date'] ?? ''),
                'gmv_sum' => round((float) ($row['gmv_sum'] ?? 0), 4),
                'impressions_sum' => (int) $exp,
                'clicks_sum' => (int) $clk,
                'add_to_cart_sum' => (int) $add,
                'orders_sum' => (int) ($row['orders_sum'] ?? 0),
                'ctr' => $exp > 0 ? round($clk / $exp, 6) : 0,
                'add_to_cart_rate' => $clk > 0 ? round($add / $clk, 6) : 0,
            ];
        }

        $catalogRows = Db::name('growth_store_product_catalog')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($style, $styleCompact): void {
                $q->where('style_code', $style)
                    ->whereOrRaw(
                        'REPLACE(REPLACE(REPLACE(UPPER(TRIM(style_code)), "-", ""), "_", ""), " ", "") = :style_compact',
                        ['style_compact' => $styleCompact]
                    );
            })
            ->when($storeId > 0, static function ($q) use ($storeId): void {
                $q->where('store_id', $storeId);
            })
            ->field('id,store_id,style_code,product_name,image_url,updated_at')
            ->order('id', 'desc')
            ->select()
            ->toArray();

        return $this->jsonOk([
            'style_code' => $style,
            'summary' => [
                'product_count' => (int) ($totalRow['product_count'] ?? 0),
                'session_count' => (int) ($totalRow['session_count'] ?? 0),
                'gmv_sum' => round((float) ($totalRow['gmv_sum'] ?? 0), 4),
                'impressions_sum' => (int) $impressions,
                'clicks_sum' => (int) $clicks,
                'add_to_cart_sum' => (int) $atc,
                'orders_sum' => (int) ($totalRow['orders_sum'] ?? 0),
                'ctr' => $impressions > 0 ? round($clicks / $impressions, 6) : 0,
                'add_to_cart_rate' => $clicks > 0 ? round($atc / $clicks, 6) : 0,
            ],
            'trend' => $trend,
            'catalog_items' => $catalogRows,
        ]);
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
        $styleInput = trim((string) $style_code);
        if ($styleInput === '') {
            $styleInput = trim((string) $this->request->route('style_code', ''));
        }
        if ($styleInput === '') {
            $styleInput = trim((string) $this->request->param('style_code', (string) $this->request->param('styleCode', '')));
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
        $s = str_replace(['—', '–', '_', ' '], '-', $s);
        $s = preg_replace('/-+/', '-', $s) ?? $s;
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
