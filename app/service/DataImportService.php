<?php
declare(strict_types=1);

namespace app\service;

use app\model\GrowthAdCreative as GrowthAdCreativeModel;
use app\model\GrowthAdMetric as GrowthAdMetricModel;
use app\model\GrowthCompetitor as GrowthCompetitorModel;
use app\model\GrowthCompetitorMetric as GrowthCompetitorMetricModel;
use app\model\GrowthIndustryMetric as GrowthIndustryMetricModel;
use app\model\ImportJob as ImportJobModel;
use app\model\ImportJobLog as ImportJobLogModel;
use think\facade\Db;

class DataImportService
{
    public const JOB_QUEUED = 0;
    public const JOB_RUNNING = 1;
    public const JOB_SUCCESS = 2;
    public const JOB_FAILED = 3;
    public const JOB_PARTIAL = 4;

    /**
     * @var array<string, bool>
     */
    private static array $tenantColumnCache = [];

    private static function currentTenantId(): int
    {
        $tid = AdminAuthService::tenantId();
        return $tid > 0 ? $tid : 1;
    }

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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private static function withTenantPayload(string $table, array $payload): array
    {
        if (self::tableHasTenantId($table) && !array_key_exists('tenant_id', $payload)) {
            $payload['tenant_id'] = self::currentTenantId();
        }

        return $payload;
    }

    private static function applyTenantFilter($query, string $table)
    {
        if (self::tableHasTenantId($table)) {
            $query->where('tenant_id', self::currentTenantId());
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function createJob(string $domain, string $jobType, string $fileName = '', ?int $sourceId = null, array $payload = []): int
    {
        $jobPayload = [
            'source_id' => $sourceId,
            'domain' => mb_substr(trim($domain) !== '' ? trim($domain) : 'generic', 0, 32),
            'job_type' => mb_substr(trim($jobType) !== '' ? trim($jobType) : 'csv', 0, 32),
            'file_name' => $fileName !== '' ? mb_substr($fileName, 0, 255) : null,
            'status' => self::JOB_RUNNING,
            'started_at' => date('Y-m-d H:i:s'),
            'payload_json' => $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        ];
        $jobPayload = self::withTenantPayload('import_jobs', $jobPayload);
        $row = ImportJobModel::create($jobPayload);

        return (int) $row->id;
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function addJobLog(int $jobId, string $level, string $message, array $context = []): void
    {
        if ($jobId <= 0) {
            return;
        }
        $logPayload = [
            'job_id' => $jobId,
            'level' => mb_substr(trim($level) !== '' ? trim($level) : 'info', 0, 16),
            'message' => mb_substr($message, 0, 255),
            'context_json' => $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
        ];
        $logPayload = self::withTenantPayload('import_job_logs', $logPayload);
        ImportJobLogModel::create($logPayload);
    }

    public static function finishJob(int $jobId, int $status, int $total, int $success, int $failed, string $errorMessage = ''): void
    {
        if ($jobId <= 0) {
            return;
        }
        $query = ImportJobModel::where('id', $jobId);
        $query = self::applyTenantFilter($query, 'import_jobs');
        $query->update([
            'status' => $status,
            'total_rows' => max(0, $total),
            'success_rows' => max(0, $success),
            'failed_rows' => max(0, $failed),
            'error_message' => $errorMessage !== '' ? mb_substr($errorMessage, 0, 255) : null,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{status:int,total_rows:int,success_rows:int,failed_rows:int,error_message:string}
     */
    public static function runDomainImport(string $domain, array $rows, int $jobId = 0): array
    {
        $domain = mb_strtolower(trim($domain), 'UTF-8');
        $total = count($rows);
        $ok = 0;
        $fail = 0;
        $error = '';

        try {
            if ($domain === 'industry') {
                [$ok, $fail] = self::importIndustryRows($rows, $jobId);
            } elseif ($domain === 'competitor') {
                [$ok, $fail] = self::importCompetitorRows($rows, $jobId);
            } elseif ($domain === 'ads') {
                [$ok, $fail] = self::importAdRows($rows, $jobId);
            } else {
                throw new \InvalidArgumentException('unsupported_domain');
            }
        } catch (\Throwable $e) {
            $error = 'import_exception';
            if ($jobId > 0) {
                self::addJobLog($jobId, 'error', 'import_exception', ['message' => $e->getMessage()]);
                self::finishJob($jobId, self::JOB_FAILED, $total, 0, $total, $error);
            }
            return [
                'status' => self::JOB_FAILED,
                'total_rows' => $total,
                'success_rows' => 0,
                'failed_rows' => $total,
                'error_message' => $error,
            ];
        }

        $status = $fail > 0 ? ($ok > 0 ? self::JOB_PARTIAL : self::JOB_FAILED) : self::JOB_SUCCESS;
        if ($fail > 0 && $ok === 0 && $error === '') {
            $error = 'all_rows_failed';
        }
        if ($jobId > 0) {
            self::finishJob($jobId, $status, $total, $ok, $fail, $error);
        }

        return [
            'status' => $status,
            'total_rows' => $total,
            'success_rows' => $ok,
            'failed_rows' => $fail,
            'error_message' => $error,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public static function extractRowsFromPayload(?string $payloadJson): array
    {
        if (!is_string($payloadJson) || trim($payloadJson) === '') {
            return [];
        }
        $decoded = json_decode($payloadJson, true);
        if (!is_array($decoded)) {
            return [];
        }

        $rows = [];
        if (isset($decoded['rows']) && is_array($decoded['rows'])) {
            $rows = $decoded['rows'];
        } elseif (array_is_list($decoded)) {
            $rows = $decoded;
        }
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = [];
            foreach ($row as $k => $v) {
                $normalized[(string) $k] = trim((string) $v);
            }
            if ($normalized !== []) {
                $out[] = $normalized;
            }
        }

        return $out;
    }

    /**
     * Parse uploaded CSV to normalized rows.
     *
     * @return array{headers: list<string>, rows: list<array<string, string>>}
     */
    public static function parseCsvFile(string $filePath): array
    {
        $headers = [];
        $rows = [];
        $f = new \SplFileObject($filePath, 'r');
        $f->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);
        $f->setCsvControl(',');
        $lineNo = 0;
        while (!$f->eof()) {
            $line = $f->fgetcsv();
            if (!is_array($line)) {
                continue;
            }
            $line = self::normalizeCsvLine($line);
            $lineNo++;
            if ($lineNo === 1) {
                $headers = self::normalizeHeaders($line);
                continue;
            }
            if ($headers === []) {
                continue;
            }
            $row = [];
            $hasValue = false;
            foreach ($headers as $idx => $key) {
                $v = isset($line[$idx]) ? trim((string) $line[$idx]) : '';
                $row[$key] = $v;
                if ($v !== '') {
                    $hasValue = true;
                }
            }
            if ($hasValue) {
                $rows[] = $row;
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param array<int, mixed> $line
     * @return list<string>
     */
    private static function normalizeCsvLine(array $line): array
    {
        $out = [];
        foreach ($line as $cell) {
            $out[] = self::normalizeCsvCell((string) $cell);
        }

        return $out;
    }

    private static function normalizeCsvCell(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $value = (string) preg_replace('/^\xEF\xBB\xBF/', '', $value);
        if (!mb_check_encoding($value, 'UTF-8')) {
            $detected = mb_detect_encoding($value, ['GB18030', 'GBK', 'BIG5', 'UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
            if (is_string($detected) && strtoupper($detected) !== 'UTF-8') {
                $converted = @mb_convert_encoding($value, 'UTF-8', $detected);
                if (is_string($converted) && $converted !== '') {
                    $value = $converted;
                }
            } else {
                $converted = @iconv('GB18030', 'UTF-8//IGNORE', $value);
                if (is_string($converted) && $converted !== '') {
                    $value = $converted;
                }
            }
        }

        return trim($value);
    }

    /**
     * @param array<int, mixed> $headers
     * @return list<string>
     */
    private static function normalizeHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $h) {
            $k = mb_strtolower(trim((string) $h), 'UTF-8');
            $k = preg_replace('/^\xEF\xBB\xBF/u', '', $k) ?? $k;
            $k = preg_replace('/[^a-z0-9_]+/i', '_', $k) ?? $k;
            $k = trim((string) $k, '_');
            $out[] = $k !== '' ? $k : ('col_' . (count($out) + 1));
        }
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0:int,1:int}
     */
    private static function importIndustryRows(array $rows, int $jobId = 0): array
    {
        $ok = 0;
        $fail = 0;
        $warnMax = 50;
        foreach ($rows as $idx => $r) {
            $date = self::sanitizeDate((string) ($r['metric_date'] ?? ($r['date'] ?? '')));
            $country = strtoupper(trim((string) ($r['country_code'] ?? ($r['country'] ?? ''))));
            $category = trim((string) ($r['category_name'] ?? ($r['category'] ?? '')));
            if ($date === '' || $country === '' || $category === '') {
                $fail++;
                if ($jobId > 0 && $fail <= $warnMax) {
                    self::addJobLog($jobId, 'warn', 'missing_required_fields', ['row' => $idx + 2]);
                }
                continue;
            }
            $payload = [
                'metric_date' => $date,
                'country_code' => mb_substr($country, 0, 12),
                'category_name' => mb_substr($category, 0, 64),
                'heat_score' => (float) ($r['heat_score'] ?? 0),
                'content_count' => max(0, (int) ($r['content_count'] ?? 0)),
                'engagement_rate' => (float) ($r['engagement_rate'] ?? 0),
                'cpc' => (float) ($r['cpc'] ?? 0),
                'cpm' => (float) ($r['cpm'] ?? 0),
            ];
            $payload = self::withTenantPayload('growth_industry_metrics', $payload);
            $exists = GrowthIndustryMetricModel::where('metric_date', $payload['metric_date'])
                ->where('country_code', $payload['country_code'])
                ->where('category_name', $payload['category_name']);
            $exists = self::applyTenantFilter($exists, 'growth_industry_metrics')->find();
            if ($exists) {
                $exists->save($payload);
            } else {
                GrowthIndustryMetricModel::create($payload);
            }
            $ok++;
        }

        return [$ok, $fail];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0:int,1:int}
     */
    private static function importCompetitorRows(array $rows, int $jobId = 0): array
    {
        $ok = 0;
        $fail = 0;
        $warnMax = 50;
        foreach ($rows as $idx => $r) {
            $name = trim((string) ($r['competitor_name'] ?? ($r['name'] ?? '')));
            $platform = trim((string) ($r['platform'] ?? 'tiktok'));
            $metricDate = self::sanitizeDate((string) ($r['metric_date'] ?? ($r['date'] ?? '')));
            if ($name === '' || $metricDate === '') {
                $fail++;
                if ($jobId > 0 && $fail <= $warnMax) {
                    self::addJobLog($jobId, 'warn', 'missing_required_fields', ['row' => $idx + 2]);
                }
                continue;
            }
            $competitor = GrowthCompetitorModel::where('name', $name)->where('platform', $platform);
            $competitor = self::applyTenantFilter($competitor, 'growth_competitors')->find();
            if (!$competitor) {
                $competitorPayload = [
                    'name' => mb_substr($name, 0, 128),
                    'platform' => mb_substr($platform !== '' ? $platform : 'tiktok', 0, 32),
                    'region' => trim((string) ($r['region'] ?? '')) ?: null,
                    'category_name' => trim((string) ($r['category_name'] ?? ($r['category'] ?? ''))) ?: null,
                    'status' => 1,
                ];
                $competitorPayload = self::withTenantPayload('growth_competitors', $competitorPayload);
                $competitor = GrowthCompetitorModel::create($competitorPayload);
            }
            $payload = [
                'competitor_id' => (int) $competitor->id,
                'metric_date' => $metricDate,
                'followers' => max(0, (int) ($r['followers'] ?? 0)),
                'engagement_rate' => (float) ($r['engagement_rate'] ?? 0),
                'content_count' => max(0, (int) ($r['content_count'] ?? 0)),
                'conversion_proxy' => (float) ($r['conversion_proxy'] ?? 0),
            ];
            $payload = self::withTenantPayload('growth_competitor_metrics', $payload);
            $exists = GrowthCompetitorMetricModel::where('competitor_id', $payload['competitor_id'])
                ->where('metric_date', $payload['metric_date']);
            $exists = self::applyTenantFilter($exists, 'growth_competitor_metrics')->find();
            if ($exists) {
                $exists->save($payload);
            } else {
                GrowthCompetitorMetricModel::create($payload);
            }
            $ok++;
        }

        return [$ok, $fail];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{0:int,1:int}
     */
    private static function importAdRows(array $rows, int $jobId = 0): array
    {
        $ok = 0;
        $fail = 0;
        $warnMax = 50;
        foreach ($rows as $idx => $r) {
            $creativeCode = trim((string) ($r['creative_code'] ?? ($r['creative_id'] ?? '')));
            $metricDate = self::sanitizeDate((string) ($r['metric_date'] ?? ($r['date'] ?? '')));
            if ($creativeCode === '' || $metricDate === '') {
                $fail++;
                if ($jobId > 0 && $fail <= $warnMax) {
                    self::addJobLog($jobId, 'warn', 'missing_required_fields', ['row' => $idx + 2]);
                }
                continue;
            }
            $creative = GrowthAdCreativeModel::where('creative_code', $creativeCode);
            $creative = self::applyTenantFilter($creative, 'growth_ad_creatives')->find();
            if (!$creative) {
                $creativePayload = [
                    'creative_code' => mb_substr($creativeCode, 0, 64),
                    'title' => mb_substr((string) ($r['title'] ?? ''), 0, 255),
                    'platform' => mb_substr(trim((string) ($r['platform'] ?? 'tiktok')), 0, 32),
                    'region' => trim((string) ($r['region'] ?? '')) ?: null,
                    'category_name' => trim((string) ($r['category_name'] ?? ($r['category'] ?? ''))) ?: null,
                    'landing_url' => trim((string) ($r['landing_url'] ?? '')) ?: null,
                    'first_seen_at' => self::sanitizeDate((string) ($r['first_seen_at'] ?? '')) ?: null,
                    'last_seen_at' => self::sanitizeDate((string) ($r['last_seen_at'] ?? '')) ?: null,
                    'status' => 1,
                ];
                $creativePayload = self::withTenantPayload('growth_ad_creatives', $creativePayload);
                $creative = GrowthAdCreativeModel::create($creativePayload);
            } else {
                $creative->title = trim((string) ($r['title'] ?? '')) !== '' ? mb_substr((string) ($r['title'] ?? ''), 0, 255) : (string) $creative->title;
                $creative->platform = trim((string) ($r['platform'] ?? '')) !== '' ? mb_substr((string) ($r['platform'] ?? ''), 0, 32) : (string) $creative->platform;
                $creative->region = trim((string) ($r['region'] ?? '')) !== '' ? mb_substr((string) ($r['region'] ?? ''), 0, 16) : $creative->region;
                $creative->category_name = trim((string) ($r['category_name'] ?? ($r['category'] ?? ''))) !== '' ? mb_substr((string) ($r['category_name'] ?? ($r['category'] ?? '')), 0, 64) : $creative->category_name;
                $creative->landing_url = trim((string) ($r['landing_url'] ?? '')) !== '' ? mb_substr((string) ($r['landing_url'] ?? ''), 0, 512) : $creative->landing_url;
                $creative->first_seen_at = self::sanitizeDate((string) ($r['first_seen_at'] ?? '')) ?: $creative->first_seen_at;
                $creative->last_seen_at = self::sanitizeDate((string) ($r['last_seen_at'] ?? '')) ?: $creative->last_seen_at;
                $creative->save();
            }
            $payload = [
                'creative_id' => (int) $creative->id,
                'metric_date' => $metricDate,
                'impressions' => max(0, (int) ($r['impressions'] ?? 0)),
                'clicks' => max(0, (int) ($r['clicks'] ?? 0)),
                'ctr' => (float) ($r['ctr'] ?? 0),
                'cpc' => (float) ($r['cpc'] ?? 0),
                'cpm' => (float) ($r['cpm'] ?? 0),
                'est_spend' => (float) ($r['est_spend'] ?? ($r['spend'] ?? 0)),
                'est_gmv' => (float) ($r['est_gmv'] ?? ($r['gmv'] ?? 0)),
                'active_days' => max(0, (int) ($r['active_days'] ?? 0)),
            ];
            $payload = self::withTenantPayload('growth_ad_metrics', $payload);
            $exists = GrowthAdMetricModel::where('creative_id', $payload['creative_id'])
                ->where('metric_date', $payload['metric_date']);
            $exists = self::applyTenantFilter($exists, 'growth_ad_metrics')->find();
            if ($exists) {
                $exists->save($payload);
            } else {
                GrowthAdMetricModel::create($payload);
            }
            $ok++;
        }

        return [$ok, $fail];
    }

    private static function sanitizeDate(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return '';
        }
        $dt = date_create($raw);
        if ($dt === false) {
            return '';
        }

        return $dt->format('Y-m-d');
    }
}
