<?php
declare(strict_types=1);

namespace app\service;

use app\model\DataSource as DataSourceModel;
use app\model\ImportJob as ImportJobModel;
use app\service\data_import\SourceAdapterRegistry;
use think\facade\Db;

class DataImportDispatchService
{
    /**
     * @var array<string, bool>
     */
    private static array $tenantColumnCache = [];

    private static function tableHasTenantId(string $table): bool
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

    private static function applyTenantFilter($query, string $table)
    {
        if (self::tableHasTenantId($table)) {
            $query->where('tenant_id', AdminAuthService::tenantId());
        }

        return $query;
    }

    /**
     * @return list<array<string, string>>
     */
    public static function adapterOptions(): array
    {
        return SourceAdapterRegistry::options();
    }

    /**
     * @return array{job_id:int,domain:string,status:int,total_rows:int,success_rows:int,failed_rows:int,error_message:string,adapter_key:string}
     */
    public static function runSource(int $sourceId, string $domainOverride = ''): array
    {
        $sourceQuery = DataSourceModel::where('id', $sourceId);
        $sourceQuery = self::applyTenantFilter($sourceQuery, 'data_sources');
        $source = $sourceQuery->find();
        if (!$source) {
            throw new \RuntimeException('source_not_found');
        }
        if ((int) ($source->status ?? 0) !== 1) {
            throw new \RuntimeException('source_disabled');
        }

        $config = self::parseConfig((string) ($source->config_json ?? ''));
        $adapterKey = trim((string) ($source->adapter_key ?? ''));
        if ($adapterKey === '') {
            $adapterKey = trim((string) ($config['adapter_key'] ?? ''));
        }
        if ($adapterKey === '') {
            throw new \RuntimeException('adapter_key_required');
        }

        $domain = self::normalizeDomain($domainOverride !== '' ? $domainOverride : (string) ($config['domain'] ?? ''));
        if ($domain === '') {
            throw new \RuntimeException('domain_required');
        }

        $jobId = DataImportService::createJob(
            $domain,
            'adapter',
            (string) ($source->code ?? ''),
            (int) $source->id,
            [
                'source_id' => (int) $source->id,
                'source_code' => (string) ($source->code ?? ''),
                'adapter_key' => $adapterKey,
                'config' => self::maskedConfig($config),
            ]
        );

        try {
            $adapter = SourceAdapterRegistry::build($adapterKey);
            $rows = $adapter->fetchRows($config, $domain);
            DataImportService::addJobLog($jobId, 'info', 'adapter_rows_fetched', ['count' => count($rows)]);

            $jobUpdateQuery = ImportJobModel::where('id', $jobId);
            $jobUpdateQuery = self::applyTenantFilter($jobUpdateQuery, 'import_jobs');
            $jobUpdateQuery->update([
                'payload_json' => json_encode([
                    'source_id' => (int) $source->id,
                    'source_code' => (string) ($source->code ?? ''),
                    'adapter_key' => $adapterKey,
                    'config' => self::maskedConfig($config),
                    'rows' => $rows,
                ], JSON_UNESCAPED_UNICODE),
            ]);
            $result = DataImportService::runDomainImport($domain, $rows, $jobId);

            return [
                'job_id' => $jobId,
                'domain' => $domain,
                'status' => (int) $result['status'],
                'total_rows' => (int) $result['total_rows'],
                'success_rows' => (int) $result['success_rows'],
                'failed_rows' => (int) $result['failed_rows'],
                'error_message' => (string) ($result['error_message'] ?? ''),
                'adapter_key' => $adapterKey,
            ];
        } catch (\Throwable $e) {
            DataImportService::addJobLog($jobId, 'error', 'adapter_dispatch_failed', ['message' => $e->getMessage()]);
            DataImportService::finishJob($jobId, DataImportService::JOB_FAILED, 0, 0, 0, 'adapter_dispatch_failed');
            throw new \RuntimeException('adapter_dispatch_failed');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function parseConfig(string $configJson): array
    {
        $raw = trim($configJson);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }
        return $decoded;
    }

    private static function normalizeDomain(string $domain): string
    {
        $normalized = mb_strtolower(trim($domain), 'UTF-8');
        if (in_array($normalized, ['industry', 'competitor', 'ads'], true)) {
            return $normalized;
        }
        return '';
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function maskedConfig(array $config): array
    {
        $copy = $config;
        $secretKeys = ['token', 'api_key', 'secret', 'authorization', 'password'];
        foreach ($copy as $k => $v) {
            $key = mb_strtolower(trim((string) $k), 'UTF-8');
            if (in_array($key, $secretKeys, true)) {
                $copy[$k] = '***';
            }
        }
        return $copy;
    }
}
