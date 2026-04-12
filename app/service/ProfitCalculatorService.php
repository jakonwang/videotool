<?php
declare(strict_types=1);

namespace app\service;

class ProfitCalculatorService
{
    public const CHANNEL_LIVE = 'live';
    public const CHANNEL_VIDEO = 'video';
    public const CHANNEL_INFLUENCER = 'influencer';

    /**
     * @return string[]
     */
    public static function channelOptions(): array
    {
        return [self::CHANNEL_LIVE, self::CHANNEL_VIDEO, self::CHANNEL_INFLUENCER];
    }

    public static function normalizeChannelType(string $channel): string
    {
        $raw = mb_strtolower(trim($channel), 'UTF-8');
        if (in_array($raw, ['live', '直播', 'zhibo'], true)) {
            return self::CHANNEL_LIVE;
        }
        if (in_array($raw, ['video', '视频', 'shipin'], true)) {
            return self::CHANNEL_VIDEO;
        }
        if (in_array($raw, ['influencer', 'creator', '达人', 'daren'], true)) {
            return self::CHANNEL_INFLUENCER;
        }

        return '';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok:bool,message:string,data?:array<string,mixed>}
     */
    public static function calculate(array $input): array
    {
        $channel = self::normalizeChannelType((string) ($input['channel_type'] ?? ''));
        if ($channel === '') {
            return ['ok' => false, 'message' => 'invalid_channel_type'];
        }

        $sale = self::asFloat($input['sale_price_cny'] ?? 0);
        $cost = self::asFloat($input['product_cost_cny'] ?? 0);
        $cancelRate = self::clampRate(self::asFloat($input['cancel_rate'] ?? 0));
        $platformFeeRate = self::clampRate(self::asFloat($input['platform_fee_rate'] ?? 0));
        $commissionRate = self::clampRate(self::asFloat($input['influencer_commission_rate'] ?? 0));
        $orderCount = max(0, (int) ($input['order_count'] ?? 0));
        $liveHours = max(0.0, self::asFloat($input['live_hours'] ?? 0));
        $wageHourly = max(0.0, self::asFloat($input['wage_hourly_cny'] ?? 0));
        $adSpendCny = max(0.0, self::asFloat($input['ad_spend_cny'] ?? 0));
        $gmvCny = max(0.0, self::asFloat($input['gmv_cny'] ?? 0));

        if ($sale < 0 || $cost < 0) {
            return ['ok' => false, 'message' => 'invalid_cost_or_sale'];
        }

        if (in_array($channel, [self::CHANNEL_LIVE, self::CHANNEL_VIDEO], true)) {
            if ($adSpendCny <= 0 || $gmvCny <= 0 || $orderCount <= 0) {
                return ['ok' => false, 'message' => 'invalid_live_video_required_fields'];
            }
        }

        if ($channel === self::CHANNEL_INFLUENCER && $orderCount <= 0) {
            return ['ok' => false, 'message' => 'invalid_influencer_order_count'];
        }

        $roi = $adSpendCny > 0 ? ($gmvCny / $adSpendCny) : 0.0;
        $wageCost = $wageHourly * $liveHours;

        $baseNumerator = $sale * (1 - $cancelRate);
        $baseDenominator = $sale * (1 - $cancelRate) * (1 - $platformFeeRate) - $cost * (1 - $cancelRate);
        $breakEvenRoi = self::safeDiv($baseNumerator, $baseDenominator);
        $netProfit = 0.0;
        $perOrder = 0.0;

        if ($channel === self::CHANNEL_LIVE) {
            if ($roi <= 0) {
                return ['ok' => false, 'message' => 'invalid_roi'];
            }
            $singleProfit = $sale * (1 - $cancelRate) * (1 - $platformFeeRate)
                - $cost * (1 - $cancelRate)
                - $sale / $roi;
            $netProfit = $orderCount * $singleProfit - $wageCost;
            $perOrder = $singleProfit - ($orderCount > 0 ? ($wageCost / $orderCount) : 0.0);
        } elseif ($channel === self::CHANNEL_VIDEO) {
            if ($roi <= 0) {
                return ['ok' => false, 'message' => 'invalid_roi'];
            }
            $singleProfit = $sale * (1 - $cancelRate) * (1 - $platformFeeRate)
                - $cost * (1 - $cancelRate)
                - $sale / $roi;
            $netProfit = $orderCount * $singleProfit;
            $perOrder = $singleProfit;
        } else {
            $influencerDenominator = $sale * (1 - $cancelRate) * (1 - $platformFeeRate - $commissionRate) - $cost * (1 - $cancelRate);
            $breakEvenRoi = self::safeDiv($baseNumerator, $influencerDenominator);
            $singleProfit = $sale * (1 - $cancelRate) * (1 - $platformFeeRate - $commissionRate) - $cost * (1 - $cancelRate);
            $netProfit = $orderCount * $singleProfit;
            $perOrder = $singleProfit;
        }

        return [
            'ok' => true,
            'message' => 'ok',
            'data' => [
                'channel_type' => $channel,
                'sale_price_cny' => round($sale, 2),
                'product_cost_cny' => round($cost, 2),
                'cancel_rate' => $cancelRate,
                'platform_fee_rate' => $platformFeeRate,
                'influencer_commission_rate' => $commissionRate,
                'order_count' => $orderCount,
                'live_hours' => round($liveHours, 2),
                'wage_hourly_cny' => round($wageHourly, 2),
                'wage_cost_cny' => round($wageCost, 2),
                'ad_spend_cny' => round($adSpendCny, 2),
                'gmv_cny' => round($gmvCny, 2),
                'roi' => round($roi, 6),
                'net_profit_cny' => round($netProfit, 2),
                'break_even_roi' => $breakEvenRoi === null ? null : round($breakEvenRoi, 6),
                'per_order_profit_cny' => round($perOrder, 2),
            ],
        ];
    }

    private static function asFloat($value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return (float) str_replace(',', '', (string) $value);
    }

    private static function clampRate(float $value): float
    {
        if ($value < 0) {
            return 0.0;
        }
        if ($value > 1) {
            return 1.0;
        }

        return $value;
    }

    private static function safeDiv(float $num, float $den): ?float
    {
        if (abs($den) < 0.0000001) {
            return null;
        }

        return $num / $den;
    }
}
