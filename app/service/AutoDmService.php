<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class AutoDmService
{
    public const POLICY_KEY_DEFAULT = 'auto_dm_default';

    public const CAMPAIGN_STATUS_PAUSED = 0;
    public const CAMPAIGN_STATUS_RUNNING = 1;
    public const CAMPAIGN_STATUS_COMPLETED = 2;

    public const TASK_STATUS_PENDING = 0;
    public const TASK_STATUS_ASSIGNED = 1;
    public const TASK_STATUS_SENDING = 2;
    public const TASK_STATUS_SENT = 3;
    public const TASK_STATUS_FAILED = 4;
    public const TASK_STATUS_BLOCKED = 5;
    public const TASK_STATUS_COOLING = 6;

    public const TASK_TYPE_ZALO_AUTO_DM = 'zalo_auto_dm';
    public const TASK_TYPE_WA_AUTO_DM = 'wa_auto_dm';
    public const EXECUTE_CLIENT_BOTH = 'both';
    public const EXECUTE_CLIENT_MOBILE = 'mobile';
    public const EXECUTE_CLIENT_DESKTOP = 'desktop';

    public const EVENT_CREATED = 'created';
    public const EVENT_ASSIGNED = 'assigned';
    public const EVENT_SENDING = 'sending';
    public const EVENT_SENT = 'auto_dm_sent';
    public const EVENT_FAILED = 'auto_dm_failed';
    public const EVENT_BLOCKED = 'auto_dm_blocked';
    public const EVENT_COOLING = 'auto_dm_cooling';
    public const EVENT_REPLY_STOP = 'auto_dm_reply_stop';
    public const EVENT_REPLY_DETECTED = 'auto_dm_reply_detected';
    public const EVENT_REPLY_CONFIRMED = 'auto_dm_reply_confirmed';

    public const REPLY_STATE_NONE = 0;
    public const REPLY_STATE_DETECTED = 1;
    public const REPLY_STATE_REVIEWED = 2;

    /**
     * @return array<string, mixed>
     */
    public static function defaultPolicy(): array
    {
        return [
            'enabled' => true,
            'daily_limit' => 80,
            'time_window_start' => '09:00',
            'time_window_end' => '21:00',
            'min_interval_sec' => 90,
            'cooldown_hours' => 24,
            'fail_fuse_threshold' => 3,
            'retry_backoff_sec' => 300,
            'unsubscribe_keywords' => [
                'stop', 'unsubscribe', 'do not contact', 'dont contact',
                'khong lien he', 'dung gui', 'huy dang ky',
                'khong nhan tin', 'khong can', 'huy',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadPolicy(int $tenantId): array
    {
        $tenantId = $tenantId > 0 ? $tenantId : 1;
        $policy = self::defaultPolicy();
        try {
            $query = Db::name('contact_policies')
                ->where('tenant_id', $tenantId)
                ->where('policy_key', self::POLICY_KEY_DEFAULT)
                ->order('id', 'desc');
            if (TenantScopeService::tableHasTenantId('contact_policies')) {
                $query->where('tenant_id', $tenantId);
            }
            $row = $query->find();
            if (!$row) {
                return $policy;
            }
            $policy['enabled'] = (int) ($row['is_enabled'] ?? 1) === 1;
            $decoded = self::decodeJsonObject((string) ($row['config_json'] ?? ''));
            if ($decoded !== []) {
                $policy = array_merge($policy, $decoded);
            }
        } catch (\Throwable $e) {
            return $policy;
        }

        return self::normalizePolicy($policy);
    }

    /**
     * @param array<string, mixed> $policy
     * @return array<string, mixed>
     */
    public static function normalizePolicy(array $policy): array
    {
        $normalized = self::defaultPolicy();
        foreach ($policy as $k => $v) {
            $normalized[$k] = $v;
        }
        $normalized['enabled'] = (bool) ($normalized['enabled'] ?? true);
        $normalized['daily_limit'] = max(1, min(100000, (int) ($normalized['daily_limit'] ?? 80)));
        $normalized['min_interval_sec'] = max(10, min(86400, (int) ($normalized['min_interval_sec'] ?? 90)));
        $normalized['cooldown_hours'] = max(1, min(720, (int) ($normalized['cooldown_hours'] ?? 24)));
        $normalized['fail_fuse_threshold'] = max(1, min(100, (int) ($normalized['fail_fuse_threshold'] ?? 3)));
        $normalized['retry_backoff_sec'] = max(30, min(86400, (int) ($normalized['retry_backoff_sec'] ?? 300)));
        $normalized['time_window_start'] = self::normalizeTimeHHMM((string) ($normalized['time_window_start'] ?? '09:00'), '09:00');
        $normalized['time_window_end'] = self::normalizeTimeHHMM((string) ($normalized['time_window_end'] ?? '21:00'), '21:00');
        $normalized['unsubscribe_keywords'] = self::normalizeStringList($normalized['unsubscribe_keywords'] ?? []);

        return $normalized;
    }

    /**
     * @param array<string, string> $channels
     */
    public static function routeChannel(array $channels, string $preferred = 'auto'): string
    {
        $hasZalo = trim((string) ($channels['zalo'] ?? '')) !== '';
        $hasWa = trim((string) ($channels['whatsapp'] ?? '')) !== '';
        $pref = strtolower(trim($preferred));
        if (in_array($pref, ['zalo', 'zalo_auto_dm'], true)) {
            if ($hasZalo) {
                return 'zalo';
            }
            if ($hasWa) {
                return 'wa';
            }
            return '';
        }
        if (in_array($pref, ['wa', 'whatsapp', 'wa_auto_dm'], true)) {
            if ($hasWa) {
                return 'wa';
            }
            if ($hasZalo) {
                return 'zalo';
            }
            return '';
        }
        if ($hasZalo) {
            return 'zalo';
        }
        if ($hasWa) {
            return 'wa';
        }

        return '';
    }

    public static function taskTypeByChannel(string $channel): string
    {
        $c = strtolower(trim($channel));
        if ($c === 'wa' || $c === 'whatsapp') {
            return self::TASK_TYPE_WA_AUTO_DM;
        }

        return self::TASK_TYPE_ZALO_AUTO_DM;
    }

    public static function normalizeExecuteClient(string $raw): string
    {
        $value = strtolower(trim($raw));
        if (in_array($value, [self::EXECUTE_CLIENT_MOBILE, 'phone', 'mobile_agent'], true)) {
            return self::EXECUTE_CLIENT_MOBILE;
        }
        if (in_array($value, [self::EXECUTE_CLIENT_DESKTOP, 'pc', 'computer', 'desktop_agent'], true)) {
            return self::EXECUTE_CLIENT_DESKTOP;
        }

        return self::EXECUTE_CLIENT_BOTH;
    }

    public static function buildIdempotencyKey(int $tenantId, int $influencerId, int $campaignId, string $day, int $stepNo = 0): string
    {
        $seed = implode('|', [
            'auto_dm',
            max(1, $tenantId),
            max(1, $influencerId),
            max(1, $campaignId),
            max(0, $stepNo),
            trim($day) !== '' ? $day : date('Y-m-d'),
        ]);

        return substr(hash('sha256', $seed), 0, 64);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSequenceConfig(): array
    {
        return [
            'steps' => [
                ['step_no' => 0, 'delay_hours' => 0],
                ['step_no' => 1, 'delay_hours' => 24],
                ['step_no' => 2, 'delay_hours' => 72],
            ],
        ];
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public static function normalizeSequenceConfig($raw): array
    {
        $source = [];
        if (is_array($raw)) {
            $source = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $source = self::decodeJsonObject($raw);
        }

        $inputSteps = is_array($source['steps'] ?? null) ? $source['steps'] : [];
        $steps = [];
        foreach ($inputSteps as $item) {
            if (!is_array($item)) {
                continue;
            }
            $stepNo = max(0, min(9, (int) ($item['step_no'] ?? 0)));
            $delayHours = max(0, min(24 * 30, (int) ($item['delay_hours'] ?? 0)));
            $steps[$stepNo] = [
                'step_no' => $stepNo,
                'delay_hours' => $delayHours,
            ];
        }
        if (!isset($steps[0])) {
            $steps[0] = ['step_no' => 0, 'delay_hours' => 0];
        }
        ksort($steps);
        $list = array_values($steps);
        if (count($list) > 6) {
            $list = array_slice($list, 0, 6);
        }

        return ['steps' => $list];
    }

    public static function stepDelayHours(array $sequenceConfig, int $stepNo): int
    {
        $steps = is_array($sequenceConfig['steps'] ?? null) ? $sequenceConfig['steps'] : [];
        foreach ($steps as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((int) ($item['step_no'] ?? -1) === $stepNo) {
                return max(0, (int) ($item['delay_hours'] ?? 0));
            }
        }

        return $stepNo === 0 ? 0 : 24;
    }

    public static function maxStepNo(array $sequenceConfig): int
    {
        $max = 0;
        $steps = is_array($sequenceConfig['steps'] ?? null) ? $sequenceConfig['steps'] : [];
        foreach ($steps as $item) {
            if (!is_array($item)) {
                continue;
            }
            $max = max($max, (int) ($item['step_no'] ?? 0));
        }

        return max(0, $max);
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public static function normalizeAbConfig($raw, int $baseTemplateId): array
    {
        $source = [];
        if (is_array($raw)) {
            $source = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $source = self::decodeJsonObject($raw);
        }

        $variantsRaw = [];
        if (isset($source['variants']) && is_array($source['variants'])) {
            $variantsRaw = $source['variants'];
        } elseif (is_array($source)) {
            $variantsRaw = $source;
        }

        $variants = [];
        foreach ($variantsRaw as $index => $item) {
            if (is_numeric($item)) {
                $tplId = (int) $item;
                if ($tplId > 0) {
                    $variants[] = [
                        'code' => chr(65 + (count($variants) % 26)),
                        'template_id' => $tplId,
                        'weight' => 100,
                    ];
                }
                continue;
            }
            if (!is_array($item)) {
                continue;
            }
            $tplId = (int) ($item['template_id'] ?? 0);
            if ($tplId <= 0) {
                continue;
            }
            $weight = max(1, min(10000, (int) ($item['weight'] ?? 100)));
            $code = trim((string) ($item['code'] ?? ''));
            if ($code === '') {
                $code = chr(65 + ($index % 26));
            }
            $variants[] = [
                'code' => mb_substr($code, 0, 12),
                'template_id' => $tplId,
                'weight' => $weight,
            ];
        }
        if ($variants === [] && $baseTemplateId > 0) {
            $variants[] = [
                'code' => 'A',
                'template_id' => $baseTemplateId,
                'weight' => 100,
            ];
        }
        if (count($variants) > 8) {
            $variants = array_slice($variants, 0, 8);
        }

        return [
            'enabled' => count($variants) > 1,
            'stable_bucket' => true,
            'variants' => $variants,
        ];
    }

    public static function pickVariantTemplateId(array $abConfig, int $influencerId, int $fallbackTemplateId): int
    {
        $variants = is_array($abConfig['variants'] ?? null) ? $abConfig['variants'] : [];
        if ($variants === []) {
            return $fallbackTemplateId;
        }
        if (count($variants) === 1) {
            return (int) ($variants[0]['template_id'] ?? $fallbackTemplateId);
        }

        $total = 0;
        foreach ($variants as $item) {
            $total += max(1, (int) ($item['weight'] ?? 1));
        }
        if ($total <= 0) {
            return $fallbackTemplateId;
        }

        $seed = max(1, $influencerId);
        $bucket = intval(hexdec(substr(hash('sha1', 'ab|' . $seed), 0, 8))) % $total;
        $cursor = 0;
        foreach ($variants as $item) {
            $cursor += max(1, (int) ($item['weight'] ?? 1));
            if ($bucket < $cursor) {
                $id = (int) ($item['template_id'] ?? 0);
                if ($id > 0) {
                    return $id;
                }
                break;
            }
        }

        return $fallbackTemplateId;
    }

    /**
     * @return array<string, mixed>
     */
    public static function classifyReplyByRules(string $text): array
    {
        $raw = mb_strtolower(trim($text), 'UTF-8');
        if ($raw === '') {
            return ['category' => 'other', 'matched' => '', 'score' => 0];
        }

        $rules = [
            'unsubscribe' => [
                'stop', 'unsubscribe', 'do not contact', 'dont contact', 'remove me',
                'khong lien he', 'đừng liên hệ', 'dung gui', 'huy dang ky',
            ],
            'intent' => [
                'interested', 'cooperate', 'collab', 'let us work', 'sample ok',
                'quan tâm', 'hop tac', 'hợp tác', 'ok gui', 'ok gửi',
            ],
            'inquiry' => [
                'price', 'how much', 'quotation', 'detail', 'catalog', 'link',
                'giá', 'bao nhieu', 'bao nhiêu', 'chi tiet', 'chi tiết',
            ],
            'reject' => [
                'no thanks', 'not interested', 'busy now', 'already have',
                'khong can', 'không cần', 'tu choi', 'từ chối',
            ],
        ];

        foreach ($rules as $category => $keywords) {
            foreach ($keywords as $word) {
                $needle = mb_strtolower(trim($word), 'UTF-8');
                if ($needle === '') {
                    continue;
                }
                if (mb_strpos($raw, $needle, 0, 'UTF-8') !== false) {
                    return [
                        'category' => $category,
                        'matched' => $word,
                        'score' => 1,
                    ];
                }
            }
        }

        return ['category' => 'other', 'matched' => '', 'score' => 0];
    }

    public static function replyCategoryDefaultStatus(string $category): int
    {
        $key = strtolower(trim($category));
        if ($key === 'intent') {
            return 2;
        }
        if ($key === 'inquiry') {
            return 3;
        }
        if ($key === 'reject') {
            return 1;
        }
        if ($key === 'unsubscribe') {
            return 6;
        }

        return 2;
    }

    public static function isWithinTimeWindow(string $windowStart, string $windowEnd, ?int $nowTs = null): bool
    {
        $nowTs = $nowTs ?? time();
        $start = self::normalizeTimeHHMM($windowStart, '09:00');
        $end = self::normalizeTimeHHMM($windowEnd, '21:00');
        [$startHour, $startMin] = self::parseHHMM($start);
        [$endHour, $endMin] = self::parseHHMM($end);

        $minutes = ((int) date('G', $nowTs)) * 60 + (int) date('i', $nowTs);
        $startMinutes = $startHour * 60 + $startMin;
        $endMinutes = $endHour * 60 + $endMin;

        if ($startMinutes === $endMinutes) {
            return true;
        }
        if ($startMinutes < $endMinutes) {
            return $minutes >= $startMinutes && $minutes <= $endMinutes;
        }

        return $minutes >= $startMinutes || $minutes <= $endMinutes;
    }

    public static function isCooldownActive(?string $cooldownUntil, ?int $nowTs = null): bool
    {
        $raw = trim((string) $cooldownUntil);
        if ($raw === '') {
            return false;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return false;
        }

        return $ts > ($nowTs ?? time());
    }

    public static function isMinIntervalBlocked(?string $lastAutoDmAt, int $minIntervalSec, ?int $nowTs = null): bool
    {
        $raw = trim((string) $lastAutoDmAt);
        if ($raw === '') {
            return false;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return false;
        }
        $minIntervalSec = max(10, $minIntervalSec);

        return ($ts + $minIntervalSec) > ($nowTs ?? time());
    }

    public static function inferPriority(array $influencer): int
    {
        return MobileOutreachService::inferPriority(
            (int) round((float) ($influencer['quality_score'] ?? 0)),
            (string) ($influencer['quality_grade'] ?? '')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function renderCampaignText(int $tenantId, int $influencerId, int $templateId, int $productId = 0): array
    {
        $tenantId = $tenantId > 0 ? $tenantId : 1;
        if ($templateId <= 0 || $influencerId <= 0) {
            return [
                'text' => '',
                'template_id' => 0,
                'template_name' => '',
                'template_lang' => 'en',
            ];
        }

        $templateQuery = Db::name('message_templates')
            ->where('id', $templateId)
            ->where('status', 1);
        $templateQuery = self::scopeTenant($templateQuery, 'message_templates', $tenantId);
        $baseTemplate = $templateQuery->find();
        if (!$baseTemplate) {
            return [
                'text' => '',
                'template_id' => 0,
                'template_name' => '',
                'template_lang' => 'en',
            ];
        }

        $infQuery = Db::name('influencers')
            ->where('id', $influencerId)
            ->field('region');
        $infQuery = self::scopeTenant($infQuery, 'influencers', $tenantId);
        $inf = $infQuery->find();
        $region = (string) ($inf['region'] ?? '');

        $template = MessageOutreachService::pickTemplateVariantByRegion((array) $baseTemplate, $region, $tenantId);
        $vars = MessageOutreachService::buildRenderVars(
            $influencerId,
            $productId > 0 ? $productId : null,
            MessageOutreachService::adminBaseUrl(),
            $tenantId
        );
        $body = (string) ($template['body'] ?? '');
        $text = MessageOutreachService::renderBody($body, $vars);

        return [
            'text' => $text,
            'template_id' => (int) ($template['id'] ?? 0),
            'template_name' => (string) ($template['name'] ?? ''),
            'template_lang' => (string) ($template['lang'] ?? 'en'),
        ];
    }

    /**
     * @param list<string> $keywords
     */
    public static function hitUnsubscribeKeywords(string $text, array $keywords): bool
    {
        $raw = mb_strtolower(trim($text), 'UTF-8');
        if ($raw === '' || $keywords === []) {
            return false;
        }
        foreach ($keywords as $keyword) {
            $needle = mb_strtolower(trim((string) $keyword), 'UTF-8');
            if ($needle === '') {
                continue;
            }
            if (mb_strpos($raw, $needle, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $raw
     * @return array<string, mixed>
     */
    public static function decodeJsonObject($raw): array
    {
        if (!is_string($raw)) {
            return [];
        }
        $txt = trim($raw);
        if ($txt === '') {
            return [];
        }
        $decoded = json_decode($txt, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function encodeJson(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return '{}';
        }

        return $json;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function normalizeStringList($raw): array
    {
        $list = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                $v = trim((string) $item);
                if ($v !== '') {
                    $list[] = $v;
                }
            }
        } elseif (is_string($raw) && trim($raw) !== '') {
            $parts = preg_split('/[,;\r\n]+/', $raw) ?: [];
            foreach ($parts as $part) {
                $v = trim((string) $part);
                if ($v !== '') {
                    $list[] = $v;
                }
            }
        }

        $list = array_values(array_unique($list));
        if (count($list) > 200) {
            $list = array_slice($list, 0, 200);
        }

        return $list;
    }

    private static function normalizeTimeHHMM(string $time, string $fallback): string
    {
        $txt = trim($time);
        if (!preg_match('/^\d{1,2}:\d{1,2}$/', $txt)) {
            return $fallback;
        }
        [$h, $m] = self::parseHHMM($txt);
        if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
            return $fallback;
        }

        return sprintf('%02d:%02d', $h, $m);
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function parseHHMM(string $time): array
    {
        $parts = explode(':', $time, 2);
        $hour = isset($parts[0]) ? (int) $parts[0] : 0;
        $minute = isset($parts[1]) ? (int) $parts[1] : 0;

        return [$hour, $minute];
    }

    private static function scopeTenant($query, string $table, int $tenantId)
    {
        return TenantScopeService::apply($query, $table, $tenantId);
    }
}
