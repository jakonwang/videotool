<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class TenantScopeService
{
    /**
     * @var array<string, bool>
     */
    private static array $tenantColumnCache = [];

    public static function tenantId(?int $tenantId = null): int
    {
        $id = $tenantId !== null ? (int) $tenantId : AdminAuthService::tenantId();
        return $id > 0 ? $id : 1;
    }

    public static function tableHasTenantId(string $table): bool
    {
        $name = strtolower(trim($table));
        if ($name === '') {
            return false;
        }
        if (array_key_exists($name, self::$tenantColumnCache)) {
            return self::$tenantColumnCache[$name];
        }
        try {
            $fields = Db::name($name)->getFields();
            $has = is_array($fields) && array_key_exists('tenant_id', $fields);
        } catch (\Throwable $e) {
            $has = false;
        }
        self::$tenantColumnCache[$name] = $has;
        return $has;
    }

    /**
     * @param mixed $query
     * @return mixed
     */
    public static function apply($query, string $table, ?int $tenantId = null)
    {
        if (self::tableHasTenantId($table)) {
            $query->where('tenant_id', self::tenantId($tenantId));
        }
        return $query;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function withPayload(string $table, array $payload, ?int $tenantId = null): array
    {
        if (self::tableHasTenantId($table) && !array_key_exists('tenant_id', $payload)) {
            $payload['tenant_id'] = self::tenantId($tenantId);
        }
        return $payload;
    }
}

