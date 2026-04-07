<?php
declare(strict_types=1);

namespace app\service;

use app\model\Influencer as InfluencerModel;
use app\model\InfluencerStatusLog as InfluencerStatusLogModel;
use think\facade\Db;

class InfluencerStatusFlowService
{
    /**
     * @var array<int, string>
     */
    private const STATUS_MAP = [
        0 => 'pending_contact',
        1 => 'dm_sent',
        2 => 'replied',
        3 => 'waiting_sample',
        4 => 'sample_shipped',
        5 => 'cooperating',
        6 => 'blacklist',
    ];

    public static function isValidStatus(int $status): bool
    {
        return array_key_exists($status, self::STATUS_MAP);
    }

    public static function canTransition(int $fromStatus, int $toStatus, bool $allowSkip = false): bool
    {
        if (!self::isValidStatus($fromStatus) || !self::isValidStatus($toStatus)) {
            return false;
        }
        if ($fromStatus === $toStatus) {
            return true;
        }
        if ($fromStatus === 6) {
            return false;
        }
        if ($toStatus === 6) {
            return true;
        }
        if ($allowSkip && $toStatus > $fromStatus && $toStatus < 6) {
            return true;
        }

        return $toStatus === $fromStatus + 1;
    }

    /**
     * @param array<string, mixed> $context
     * @return array{ok:bool,message?:string,from_status?:int,to_status?:int,no_change?:bool}
     */
    public static function transition(int $influencerId, int $toStatus, string $source, string $note = '', array $context = [], bool $allowSkip = false): array
    {
        if ($influencerId <= 0 || !self::isValidStatus($toStatus)) {
            return ['ok' => false, 'message' => 'invalid_params'];
        }

        $row = InfluencerModel::find($influencerId);
        if (!$row) {
            return ['ok' => false, 'message' => 'influencer_not_found'];
        }
        $fromStatus = (int) ($row->status ?? 0);
        if (!self::canTransition($fromStatus, $toStatus, $allowSkip)) {
            return ['ok' => false, 'message' => 'invalid_transition'];
        }
        if ($fromStatus === $toStatus) {
            return ['ok' => true, 'from_status' => $fromStatus, 'to_status' => $toStatus, 'no_change' => true];
        }

        try {
            Db::transaction(function () use ($row, $fromStatus, $toStatus, $source, $note, $context, $allowSkip): void {
                $path = [$toStatus];
                if ($allowSkip && $toStatus > $fromStatus && $toStatus < 6) {
                    $path = [];
                    for ($step = $fromStatus + 1; $step <= $toStatus; $step++) {
                        $path[] = $step;
                    }
                }

                $current = $fromStatus;
                foreach ($path as $next) {
                    $row->status = $next;
                    $row->save();
                    InfluencerStatusLogModel::create([
                        'influencer_id' => (int) $row->id,
                        'from_status' => $current,
                        'to_status' => $next,
                        'source' => mb_substr(trim($source) !== '' ? trim($source) : 'manual', 0, 32),
                        'note' => $note !== '' ? mb_substr($note, 0, 255) : null,
                        'context_json' => $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
                    ]);
                    $current = $next;
                }
            });
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'transition_failed'];
        }

        return ['ok' => true, 'from_status' => $fromStatus, 'to_status' => $toStatus];
    }
}

