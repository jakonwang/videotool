<?php
declare(strict_types=1);

namespace app\service;

use app\service\influencer_source\InfluencerSourceAdapterFactory;
use think\facade\Db;

class InfluencerSourceImportService
{
    public const SOURCE_DAMI = 'dami';

    /**
     * @return list<string>
     */
    public static function fieldKeys(): array
    {
        return [
            'tiktok_id',
            'source_influencer_id',
            'nickname',
            'avatar_url',
            'follower_count',
            'contact_whatsapp',
            'contact_zalo',
            'contact_note',
            'region',
            'category_name',
            'profile_url',
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function aliasMap(): array
    {
        return [
            'tiktok_id' => ['tiktok_id', 'tiktok', '@', 'handle', '用户名', '达人账号', '账号'],
            'source_influencer_id' => ['source_influencer_id', 'influencer_id', 'id', 'dami_id', '达人id', '主键id'],
            'nickname' => ['nickname', 'name', '昵称', '达人昵称'],
            'avatar_url' => ['avatar_url', 'avatar', '头像', '头像链接'],
            'follower_count' => ['follower_count', 'followers', 'fans', '粉丝', '粉丝数'],
            'contact_whatsapp' => ['contact_whatsapp', 'whatsapp', 'wa', 'whatsapp号码', 'wa号码'],
            'contact_zalo' => ['contact_zalo', 'zalo', 'zalo账号', 'zaloid'],
            'contact_note' => ['contact_note', 'contact_info', 'contact', '备注', '联系方式', '说明'],
            'region' => ['region', 'country', '地区', '国家'],
            'category_name' => ['category_name', 'category', '分类', '标签', '类目'],
            'profile_url' => ['profile_url', 'homepage', 'url', 'link', '主页链接', '主页url'],
        ];
    }

    /**
     * @param list<string> $headers
     * @param array<string, mixed> $mapping
     * @return array<string, int>
     */
    public static function resolveMapping(array $headers, array $mapping = []): array
    {
        $resolved = [];
        $normalizedHeaders = [];
        foreach ($headers as $idx => $header) {
            $normalizedHeaders[(int) $idx] = self::normHeader($header);
        }

        foreach (self::fieldKeys() as $field) {
            if (!array_key_exists($field, $mapping)) {
                continue;
            }
            $raw = $mapping[$field];
            if (is_int($raw) || (is_string($raw) && preg_match('/^\d+$/', trim($raw)))) {
                $idx = (int) $raw;
                if (isset($headers[$idx])) {
                    $resolved[$field] = $idx;
                }
                continue;
            }
            $label = trim((string) $raw);
            if ($label === '') {
                continue;
            }
            $norm = self::normHeader($label);
            foreach ($normalizedHeaders as $idx => $hdr) {
                if ($hdr === $norm) {
                    $resolved[$field] = $idx;
                    break;
                }
            }
        }

        $aliasMap = self::aliasMap();
        foreach (self::fieldKeys() as $field) {
            if (isset($resolved[$field])) {
                continue;
            }
            $aliases = $aliasMap[$field] ?? [];
            foreach ($normalizedHeaders as $idx => $hdr) {
                if ($hdr === '') {
                    continue;
                }
                foreach ($aliases as $alias) {
                    if ($hdr === self::normHeader($alias)) {
                        $resolved[$field] = $idx;
                        break 2;
                    }
                }
            }
        }

        return $resolved;
    }

    /**
     * @param array<string, int> $mapping
     * @param list<string> $row
     * @return array<string, string>
     */
    private static function rowByMapping(array $mapping, array $row): array
    {
        $out = [];
        foreach (self::fieldKeys() as $field) {
            $idx = $mapping[$field] ?? null;
            $out[$field] = ($idx !== null && isset($row[$idx])) ? trim((string) $row[$idx]) : '';
        }

        return $out;
    }

    /**
     * @param array<string, string> $raw
     * @return array{ok:bool,data?:array<string,mixed>,reason?:string}
     */
    private static function normalizeRawRow(array $raw): array
    {
        $tiktokRaw = (string) ($raw['tiktok_id'] ?? '');
        $sourceId = trim((string) ($raw['source_influencer_id'] ?? ''));
        $handle = InfluencerService::normalizeTiktokId($tiktokRaw);
        if ($handle === null && $sourceId === '') {
            return ['ok' => false, 'reason' => 'missing_tiktok_id_and_source_id'];
        }

        if ($handle === null) {
            $fallback = strtolower(preg_replace('/[^a-z0-9._]+/i', '_', $sourceId) ?? '');
            $fallback = trim($fallback, '_');
            if ($fallback === '') {
                $fallback = substr(md5($sourceId), 0, 20);
            }
            $handle = '@dami_' . mb_substr($fallback, 0, 110);
        }

        $nickname = InfluencerService::normalizeInputText((string) ($raw['nickname'] ?? ''), 120);
        $avatar = InfluencerService::normalizeInputText((string) ($raw['avatar_url'] ?? ''), 1024);
        $followers = InfluencerService::parseFollowerCount((string) ($raw['follower_count'] ?? ''));
        $region = InfluencerService::normalizeInputText((string) ($raw['region'] ?? ''), 64);
        $category = InfluencerService::normalizeInputText((string) ($raw['category_name'] ?? ''), 64);
        $profileUrl = InfluencerService::normalizeInputText((string) ($raw['profile_url'] ?? ''), 1024);
        $wa = InfluencerService::normalizeWhatsappNumber((string) ($raw['contact_whatsapp'] ?? ''));
        $zalo = InfluencerService::normalizeZaloToken((string) ($raw['contact_zalo'] ?? ''));
        $note = InfluencerService::normalizeInputText((string) ($raw['contact_note'] ?? ''), 255);
        $parts = [];
        if ($wa !== '') {
            $parts['whatsapp'] = $wa;
        }
        if ($zalo !== '') {
            $parts['zalo'] = $zalo;
        }
        if ($note !== '') {
            $parts['text'] = $note;
        }
        $sourceHash = md5(json_encode([
            'tiktok_id' => $handle,
            'source_influencer_id' => $sourceId,
            'nickname' => $nickname,
            'avatar_url' => $avatar,
            'follower_count' => $followers,
            'region' => $region,
            'category_name' => $category,
            'profile_url' => $profileUrl,
            'contact' => $parts,
        ], JSON_UNESCAPED_UNICODE));

        return [
            'ok' => true,
            'data' => [
                'tiktok_id' => $handle,
                'source_influencer_id' => $sourceId,
                'nickname' => $nickname,
                'avatar_url' => $avatar,
                'follower_count' => $followers,
                'contact_parts' => $parts,
                'region' => $region,
                'category_name' => $category,
                'profile_url' => $profileUrl,
                'source_hash' => $sourceHash,
            ],
        ];
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     * @param array<string, int> $mapping
     * @return array{total:int,inserted:int,updated:int,failed:int,preview:list<array<string,mixed>>,failed_rows:list<array<string,mixed>>}
     */
    public static function previewRows(array $headers, array $rows, array $mapping, int $tenantId, int $limit = 100): array
    {
        $total = 0;
        $inserted = 0;
        $updated = 0;
        $failed = 0;
        $preview = [];
        $failedRows = [];
        $handles = [];
        $sourceIds = [];
        $normalizedRows = [];

        foreach ($rows as $idx => $row) {
            $total++;
            $raw = self::rowByMapping($mapping, $row);
            $norm = self::normalizeRawRow($raw);
            if (!($norm['ok'] ?? false)) {
                $failed++;
                if (count($failedRows) < 100) {
                    $failedRows[] = [
                        'row_no' => $idx + 2,
                        'reason' => (string) ($norm['reason'] ?? 'invalid_row'),
                    ];
                }
                continue;
            }
            $data = (array) ($norm['data'] ?? []);
            $normalizedRows[] = ['row_no' => $idx + 2, 'data' => $data];
            $handles[] = (string) ($data['tiktok_id'] ?? '');
            $sid = trim((string) ($data['source_influencer_id'] ?? ''));
            if ($sid !== '') {
                $sourceIds[] = $sid;
            }
        }

        $existsByHandle = [];
        if ($handles !== []) {
            $query = Db::name('influencers')->whereIn('tiktok_id', array_values(array_unique($handles)));
            if (TenantScopeService::tableHasTenantId('influencers')) {
                $query->where('tenant_id', $tenantId);
            }
            $rowsDb = $query->field('id,tiktok_id')->select()->toArray();
            foreach ($rowsDb as $rowDb) {
                $existsByHandle[(string) ($rowDb['tiktok_id'] ?? '')] = (int) ($rowDb['id'] ?? 0);
            }
        }
        $existsBySource = [];
        if ($sourceIds !== [] && self::hasColumn('influencers', 'source_influencer_id')) {
            $query = Db::name('influencers')
                ->where('source_system', self::SOURCE_DAMI)
                ->whereIn('source_influencer_id', array_values(array_unique($sourceIds)));
            if (TenantScopeService::tableHasTenantId('influencers')) {
                $query->where('tenant_id', $tenantId);
            }
            $rowsDb = $query->field('id,source_influencer_id')->select()->toArray();
            foreach ($rowsDb as $rowDb) {
                $existsBySource[(string) ($rowDb['source_influencer_id'] ?? '')] = (int) ($rowDb['id'] ?? 0);
            }
        }

        foreach ($normalizedRows as $item) {
            $data = (array) ($item['data'] ?? []);
            $sid = trim((string) ($data['source_influencer_id'] ?? ''));
            $handle = (string) ($data['tiktok_id'] ?? '');
            $id = 0;
            $action = 'insert';
            if ($handle !== '' && isset($existsByHandle[$handle])) {
                $id = (int) $existsByHandle[$handle];
                $action = 'update';
            } elseif ($sid !== '' && isset($existsBySource[$sid])) {
                $id = (int) $existsBySource[$sid];
                $action = 'update';
            }

            if ($action === 'update') {
                $updated++;
            } else {
                $inserted++;
            }
            if (count($preview) < $limit) {
                $preview[] = [
                    'row_no' => (int) ($item['row_no'] ?? 0),
                    'action' => $action,
                    'match_id' => $id,
                    'tiktok_id' => $handle,
                    'source_influencer_id' => $sid,
                    'nickname' => (string) ($data['nickname'] ?? ''),
                    'follower_count' => (int) ($data['follower_count'] ?? 0),
                    'region' => (string) ($data['region'] ?? ''),
                ];
            }
        }

        return [
            'total' => $total,
            'inserted' => $inserted,
            'updated' => $updated,
            'failed' => $failed,
            'preview' => $preview,
            'failed_rows' => $failedRows,
        ];
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     * @param array<string, int> $mapping
     * @return array<string, mixed>
     */
    public static function commitRows(
        array $headers,
        array $rows,
        array $mapping,
        int $tenantId,
        int $adminId,
        string $fileName,
        string $sourceSystem = self::SOURCE_DAMI
    ): array {
        $now = date('Y-m-d H:i:s');
        $batchNo = 'DAMI-' . date('YmdHis') . '-' . substr(md5(uniqid('', true)), 0, 6);

        $batchId = (int) Db::name('influencer_source_import_batches')->insertGetId([
            'tenant_id' => $tenantId,
            'batch_no' => $batchNo,
            'source_system' => $sourceSystem,
            'file_name' => mb_substr($fileName, 0, 255),
            'mapping_json' => json_encode($mapping, JSON_UNESCAPED_UNICODE),
            'total_rows' => 0,
            'inserted_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'error_rows_json' => '[]',
            'created_by' => $adminId > 0 ? $adminId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $total = 0;
        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $failedRows = [];

        Db::transaction(function () use (
            $rows, $mapping, $tenantId, $sourceSystem, $batchId, $now,
            &$total, &$inserted, &$updated, &$skipped, &$failed, &$failedRows
        ): void {
            foreach ($rows as $idx => $row) {
                $total++;
                $raw = self::rowByMapping($mapping, $row);
                $norm = self::normalizeRawRow($raw);
                if (!($norm['ok'] ?? false)) {
                    $failed++;
                    if (count($failedRows) < 200) {
                        $failedRows[] = ['row_no' => $idx + 2, 'reason' => (string) ($norm['reason'] ?? 'invalid_row')];
                    }
                    continue;
                }
                $data = (array) ($norm['data'] ?? []);
                $handle = (string) ($data['tiktok_id'] ?? '');
                $sid = trim((string) ($data['source_influencer_id'] ?? ''));

                $existsQuery = Db::name('influencers')->where('tiktok_id', $handle);
                if (TenantScopeService::tableHasTenantId('influencers')) {
                    $existsQuery->where('tenant_id', $tenantId);
                }
                $exists = $existsQuery->find();
                if (!$exists && $sid !== '' && self::hasColumn('influencers', 'source_influencer_id')) {
                    $q2 = Db::name('influencers')->where('source_system', $sourceSystem)->where('source_influencer_id', $sid);
                    if (TenantScopeService::tableHasTenantId('influencers')) {
                        $q2->where('tenant_id', $tenantId);
                    }
                    $exists = $q2->find();
                }

                $contactInfo = null;
                $parts = (array) ($data['contact_parts'] ?? []);
                if ($parts !== []) {
                    $contactInfo = InfluencerService::mergeContactPartsInto(
                        $exists ? (string) ($exists['contact_info'] ?? '') : null,
                        $parts
                    );
                }

                $payload = [
                    'nickname' => (string) ($data['nickname'] ?? ''),
                    'avatar_url' => (string) ($data['avatar_url'] ?? ''),
                    'follower_count' => (int) ($data['follower_count'] ?? 0),
                    'region' => (string) ($data['region'] ?? ''),
                    'category_name' => (string) ($data['category_name'] ?? ''),
                    'profile_url' => (string) ($data['profile_url'] ?? ''),
                    'data_source' => $sourceSystem,
                    'source_system' => $sourceSystem,
                    'source_influencer_id' => $sid !== '' ? $sid : null,
                    'source_sync_at' => $now,
                    'source_hash' => (string) ($data['source_hash'] ?? ''),
                    'last_crawled_at' => $now,
                    'source_batch_id' => $batchId,
                    'updated_at' => $now,
                ];
                if ($contactInfo !== null) {
                    $payload['contact_info'] = $contactInfo;
                }

                if ($exists) {
                    $update = [];
                    foreach ($payload as $k => $v) {
                        if ($k === 'nickname' && $v === '') {
                            continue;
                        }
                        if ($k === 'avatar_url' && $v === '') {
                            continue;
                        }
                        if ($k === 'follower_count' && (int) $v <= 0) {
                            continue;
                        }
                        if ($k === 'region' && $v === '') {
                            continue;
                        }
                        if ($k === 'category_name' && $v === '') {
                            continue;
                        }
                        if ($k === 'profile_url' && $v === '') {
                            continue;
                        }
                        if ($k === 'source_influencer_id' && ($v === null || $v === '')) {
                            continue;
                        }
                        $update[$k] = $v;
                    }
                    Db::name('influencers')->where('id', (int) $exists['id'])->update($update);
                    $updated++;
                    continue;
                }

                $insert = [
                    'tiktok_id' => $handle,
                    'nickname' => (string) ($payload['nickname'] ?? ''),
                    'avatar_url' => ((string) ($payload['avatar_url'] ?? '') !== '') ? (string) $payload['avatar_url'] : null,
                    'follower_count' => max(0, (int) ($payload['follower_count'] ?? 0)),
                    'contact_info' => $contactInfo,
                    'region' => ((string) ($payload['region'] ?? '') !== '') ? (string) $payload['region'] : null,
                    'status' => 0,
                    'category_name' => ((string) ($payload['category_name'] ?? '') !== '') ? (string) $payload['category_name'] : null,
                    'profile_url' => ((string) ($payload['profile_url'] ?? '') !== '') ? (string) $payload['profile_url'] : null,
                    'data_source' => $sourceSystem,
                    'source_system' => $sourceSystem,
                    'source_influencer_id' => $sid !== '' ? $sid : null,
                    'source_sync_at' => $now,
                    'source_hash' => (string) ($payload['source_hash'] ?? ''),
                    'last_crawled_at' => $now,
                    'source_batch_id' => $batchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $insert = TenantScopeService::withPayload('influencers', $insert, $tenantId);
                Db::name('influencers')->insert($insert);
                $inserted++;
            }
        });

        Db::name('influencer_source_import_batches')->where('id', $batchId)->update([
            'total_rows' => $total,
            'inserted_rows' => $inserted,
            'updated_rows' => $updated,
            'skipped_rows' => $skipped,
            'failed_rows' => $failed,
            'error_rows_json' => json_encode($failedRows, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return [
            'batch_id' => $batchId,
            'batch_no' => $batchNo,
            'total' => $total,
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'failed_rows' => $failedRows,
        ];
    }

    /**
     * @param array<string, mixed> $mapping
     * @return array{headers:list<string>,rows:list<list<string>>,mapping:array<string,int>}
     */
    public static function readFile(string $absPath, string $ext, string $sourceSystem, array $mapping = [], int $maxRows = 0): array
    {
        $adapter = InfluencerSourceAdapterFactory::make($sourceSystem);
        $parsed = $adapter->parseRows($absPath, ['ext' => $ext, 'max_rows' => $maxRows]);
        $headers = array_values(array_map(static fn($v) => trim((string) $v), (array) ($parsed['headers'] ?? [])));
        $rows = (array) ($parsed['rows'] ?? []);
        $resolved = self::resolveMapping($headers, $mapping);

        return [
            'headers' => $headers,
            'rows' => $rows,
            'mapping' => $resolved,
        ];
    }

    private static function normHeader(string $raw): string
    {
        $s = trim(mb_strtolower($raw, 'UTF-8'));
        $s = (string) preg_replace('/\s+/u', '', $s);
        return $s;
    }

    private static function hasColumn(string $table, string $column): bool
    {
        try {
            Db::name($table)->where($column, '')->limit(1)->select();
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

