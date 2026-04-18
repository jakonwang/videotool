<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class StoreCurrencyService
{
    /**
     * @var array<string, bool>
     */
    private static array $columnCache = [];

    public static function normalize(string $currency): string
    {
        $raw = strtoupper(trim($currency));
        if (!preg_match('/^[A-Z]{3}$/', $raw)) {
            return 'VND';
        }
        return in_array($raw, FxRateService::supportedCurrencies(), true) ? $raw : 'VND';
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $tbl = strtolower(trim($table));
        $col = strtolower(trim($column));
        if ($tbl === '' || $col === '') {
            return false;
        }
        $key = $tbl . '.' . $col;
        if (array_key_exists($key, self::$columnCache)) {
            return self::$columnCache[$key];
        }
        try {
            $fields = Db::name($tbl)->getFields();
            $has = is_array($fields) && array_key_exists($col, $fields);
        } catch (\Throwable $e) {
            $has = false;
        }
        self::$columnCache[$key] = $has;
        return $has;
    }

    /**
     * @param array<int,int> $storeIds
     * @return array<int,string>
     */
    public static function loadStoreGmvCurrencyMap(array $storeIds, int $tenantId): array
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

        $tenant = TenantScopeService::tenantId($tenantId);
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
                $storeQuery->where('tenant_id', $tenant);
            }
            $storeRows = $storeQuery->select()->toArray();
            foreach ($storeRows as $row) {
                $sid = (int) ($row['id'] ?? 0);
                if ($sid <= 0) {
                    continue;
                }
                $map[$sid] = self::normalize((string) ($row['default_gmv_currency'] ?? 'VND'));
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
        if (!is_array($fields) || !array_key_exists('store_id', $fields)) {
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

        try {
            $query = Db::name('growth_profit_accounts')
                ->whereIn('store_id', $missingStoreIds)
                ->field('id,store_id,' . $currencyCol . ' AS gmv_currency')
                ->order('id', 'desc');
            if (array_key_exists('tenant_id', $fields)) {
                $query->where('tenant_id', $tenant);
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
                $map[$sid] = self::normalize((string) ($row['gmv_currency'] ?? 'VND'));
            }
        } catch (\Throwable $e) {
            // Keep store list available even if account schema differs.
        }

        return $map;
    }

    public static function syncStoreAccountDefaultGmvCurrency(int $storeId, string $currency, int $tenantId): void
    {
        if ($storeId <= 0 || !self::hasColumn('growth_profit_accounts', 'default_gmv_currency')) {
            return;
        }

        $updateData = ['default_gmv_currency' => self::normalize($currency !== '' ? $currency : 'VND')];
        if (self::hasColumn('growth_profit_accounts', 'updated_at')) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
        }

        try {
            $query = Db::name('growth_profit_accounts')->where('store_id', $storeId);
            if (self::hasColumn('growth_profit_accounts', 'tenant_id')) {
                $query->where('tenant_id', TenantScopeService::tenantId($tenantId));
            }
            $query->update($updateData);
        } catch (\Throwable $e) {
            // Keep store save success when account table shape differs.
        }
    }
}

