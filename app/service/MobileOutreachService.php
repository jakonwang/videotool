<?php
declare(strict_types=1);

namespace app\service;

class MobileOutreachService
{
    public const TASK_TYPE_COMMENT_WARMUP = 'comment_warmup';
    public const TASK_TYPE_TIKTOK_DM = 'tiktok_dm';
    public const TASK_TYPE_ZALO_IM = 'zalo_im';
    public const TASK_TYPE_WA_IM = 'wa_im';

    public const STATUS_PENDING = 0;
    public const STATUS_ASSIGNED = 1;
    public const STATUS_PREPARED = 2;
    public const STATUS_DONE = 3;
    public const STATUS_FAILED = 4;
    public const STATUS_SKIPPED = 5;
    public const STATUS_CANCELED = 6;

    public const EVENT_COMMENT_PREPARED = 'comment_prepared';
    public const EVENT_COMMENT_SENT = 'comment_sent';
    public const EVENT_DM_PREPARED = 'dm_prepared';
    public const EVENT_IM_PREPARED = 'im_prepared';

    /**
     * @return list<string>
     */
    public static function supportedTaskTypes(): array
    {
        return [
            self::TASK_TYPE_COMMENT_WARMUP,
            self::TASK_TYPE_TIKTOK_DM,
            self::TASK_TYPE_ZALO_IM,
            self::TASK_TYPE_WA_IM,
        ];
    }

    public static function normalizeTaskType(string $raw): string
    {
        $key = strtolower(trim($raw));
        if ($key === '' || $key === 'auto' || $key === 'dm') {
            return self::TASK_TYPE_TIKTOK_DM;
        }
        if (in_array($key, ['comment', 'warmup', 'warmup_comment', 'comment_warmup'], true)) {
            return self::TASK_TYPE_COMMENT_WARMUP;
        }
        if (in_array($key, ['tiktok_dm', 'dm_tiktok', 'mobile_dm'], true)) {
            return self::TASK_TYPE_TIKTOK_DM;
        }
        if (in_array($key, ['zalo', 'zalo_im', 'im_zalo'], true)) {
            return self::TASK_TYPE_ZALO_IM;
        }
        if (in_array($key, ['wa', 'whatsapp', 'wa_im', 'im_wa'], true)) {
            return self::TASK_TYPE_WA_IM;
        }

        return self::TASK_TYPE_TIKTOK_DM;
    }

    public static function isCommentTask(string $taskType): bool
    {
        return self::normalizeTaskType($taskType) === self::TASK_TYPE_COMMENT_WARMUP;
    }

    public static function inferPriority(int $qualityScore, string $qualityGrade): int
    {
        $grade = strtoupper(trim($qualityGrade));
        $base = 100;
        if ($grade === 'A') {
            $base = 300;
        } elseif ($grade === 'B') {
            $base = 220;
        } elseif ($grade === 'C') {
            $base = 140;
        }

        if ($qualityScore < 0) {
            $qualityScore = 0;
        }
        if ($qualityScore > 100) {
            $qualityScore = 100;
        }

        return $base + (int) round($qualityScore);
    }

    public static function isCommentLocked(?string $lastCommentedAt, int $hours = 24): bool
    {
        $raw = trim((string) $lastCommentedAt);
        if ($raw === '') {
            return false;
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return false;
        }

        return ($ts + ($hours * 3600)) > time();
    }

    /**
     * @param array<string, mixed> $channels
     */
    public static function resolveChannel(string $taskType, array $channels, string $preferred = 'auto'): string
    {
        $type = self::normalizeTaskType($taskType);
        if ($type === self::TASK_TYPE_COMMENT_WARMUP) {
            return 'tiktok_comment';
        }

        $hasWa = trim((string) ($channels['whatsapp'] ?? '')) !== '';
        $hasZalo = trim((string) ($channels['zalo'] ?? '')) !== '';
        $pref = strtolower(trim($preferred));

        if ($type === self::TASK_TYPE_ZALO_IM) {
            return $hasZalo ? 'zalo' : ($hasWa ? 'wa' : 'tiktok_dm');
        }
        if ($type === self::TASK_TYPE_WA_IM) {
            return $hasWa ? 'wa' : ($hasZalo ? 'zalo' : 'tiktok_dm');
        }
        if ($pref === 'zalo') {
            return $hasZalo ? 'zalo' : ($hasWa ? 'wa' : 'tiktok_dm');
        }
        if ($pref === 'wa' || $pref === 'whatsapp') {
            return $hasWa ? 'wa' : ($hasZalo ? 'zalo' : 'tiktok_dm');
        }

        return $hasZalo ? 'zalo' : ($hasWa ? 'wa' : 'tiktok_dm');
    }

    public static function randomEmoji(): string
    {
        $pool = ['😊', '😄', '🤝', '✨', '🔥', '💬', '👏', '🌟', '🎯', '💖'];
        return $pool[array_rand($pool)];
    }

    public static function buildWarmupComment(string $nickname = '', string $topic = ''): string
    {
        $name = trim($nickname);
        if ($name === '') {
            $name = 'ban';
        }
        $topicText = trim($topic);
        $emoji = self::randomEmoji();
        $templates = [
            "Video moi cua {$name} cuon qua {$emoji} Minh vua gui tin nhan xin gui mau, mong ban phan hoi nhe!",
            "{$name} oi, noi dung hom nay rat hay {$emoji} Minh vua inbox de de xuat gui mau phu hop cho ban.",
            ($topicText !== '' ? "Chu de {$topicText} ban lam rat tot {$emoji} " : '') . "Minh da nhan rieng de gui mau thu, ban check giup minh nhe!",
            "Gu cua {$name} dang trend luon {$emoji} Minh vua de lai tin nhan hop tac + gui mau.",
            "{$name} phoi san pham rat dep {$emoji} Minh da DM de gui mau phu hop, mong ban phan hoi.",
        ];

        return $templates[array_rand($templates)];
    }

    /**
     * @param array<string, mixed> $influencer
     * @param array<string, mixed> $channels
     * @param array<string, mixed> $opts
     * @return array<string, mixed>
     */
    public static function buildTaskPayload(array $influencer, string $taskType, array $channels, array $opts = []): array
    {
        $type = self::normalizeTaskType($taskType);
        $topic = trim((string) ($opts['last_video_topic'] ?? ''));
        $payload = [
            'influencer_id' => (int) ($influencer['id'] ?? 0),
            'tiktok_id' => (string) ($influencer['tiktok_id'] ?? ''),
            'nickname' => (string) ($influencer['nickname'] ?? ''),
            'region' => (string) ($influencer['region'] ?? ''),
            'task_type' => $type,
            'target_channel' => self::resolveChannel(
                $type,
                $channels,
                (string) ($opts['preferred_channel'] ?? 'auto')
            ),
            'channels' => [
                'whatsapp' => (string) ($channels['whatsapp'] ?? ''),
                'zalo' => (string) ($channels['zalo'] ?? ''),
                'wa_me' => (string) ($channels['wa_me'] ?? ''),
                'zalo_open' => (string) ($channels['zalo_open'] ?? ''),
            ],
            'template_id' => (int) ($opts['template_id'] ?? 0),
            'product_id' => (int) ($opts['product_id'] ?? 0),
            'remark' => (string) ($opts['remark'] ?? ''),
        ];

        if ($type === self::TASK_TYPE_COMMENT_WARMUP) {
            $payload['comment_text'] = trim((string) ($opts['comment_text'] ?? ''));
            if ($payload['comment_text'] === '') {
                $payload['comment_text'] = self::buildWarmupComment(
                    (string) ($influencer['nickname'] ?? ''),
                    $topic
                );
            }
        }

        return $payload;
    }

    public static function mapReportEventToStatus(string $event): int
    {
        $key = strtolower(trim($event));
        if (in_array($key, ['prepared', self::EVENT_COMMENT_PREPARED, self::EVENT_DM_PREPARED, self::EVENT_IM_PREPARED], true)) {
            return self::STATUS_PREPARED;
        }
        if (in_array($key, ['sent', 'done', 'success', self::EVENT_COMMENT_SENT], true)) {
            return self::STATUS_DONE;
        }
        if (in_array($key, ['skip', 'skipped'], true)) {
            return self::STATUS_SKIPPED;
        }
        if (in_array($key, ['cancel', 'canceled', 'cancelled'], true)) {
            return self::STATUS_CANCELED;
        }

        return self::STATUS_FAILED;
    }

    public static function normalizeActionEvent(string $event, string $taskType): string
    {
        $key = strtolower(trim($event));
        if ($key === '') {
            return 'report';
        }
        if ($key === self::EVENT_COMMENT_PREPARED
            || $key === self::EVENT_COMMENT_SENT
            || $key === self::EVENT_DM_PREPARED
            || $key === self::EVENT_IM_PREPARED
        ) {
            return $key;
        }
        if ($key === 'prepared') {
            if (self::isCommentTask($taskType)) {
                return self::EVENT_COMMENT_PREPARED;
            }
            $type = self::normalizeTaskType($taskType);
            if ($type === self::TASK_TYPE_TIKTOK_DM) {
                return self::EVENT_DM_PREPARED;
            }
            return self::EVENT_IM_PREPARED;
        }
        if (in_array($key, ['sent', 'done', 'success'], true) && self::isCommentTask($taskType)) {
            return self::EVENT_COMMENT_SENT;
        }

        return $key;
    }

    public static function shouldTouchLastCommentedAt(string $event, string $taskType): bool
    {
        if (!self::isCommentTask($taskType)) {
            return false;
        }
        $evt = self::normalizeActionEvent($event, $taskType);
        return in_array($evt, [self::EVENT_COMMENT_PREPARED, self::EVENT_COMMENT_SENT], true);
    }

    public static function shouldTouchLastContactedAt(string $event, string $taskType): bool
    {
        $evt = self::normalizeActionEvent($event, $taskType);
        if (in_array($evt, [self::EVENT_DM_PREPARED, self::EVENT_IM_PREPARED], true)) {
            return true;
        }
        $status = self::mapReportEventToStatus($event);
        if (self::isCommentTask($taskType)) {
            return false;
        }

        return in_array($status, [self::STATUS_PREPARED, self::STATUS_DONE], true);
    }
}
