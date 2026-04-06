<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 话术变量、达人链链接、WhatsApp 深链
 */
class MessageOutreachService
{
    public static function adminBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (preg_match('#^(.*?)/admin\.php#', $scriptName, $matches)) {
            $baseUrl .= $matches[1];
        }

        return $baseUrl;
    }

    /**
     * 优先匹配该达人+商品的启用达人链；否则退回该商品任意启用链
     */
    public static function resolveDistributeLink(int $influencerId, int $productId, string $baseUrl): string
    {
        $row = Db::name('product_links')
            ->where('product_id', $productId)
            ->where('influencer_id', $influencerId)
            ->where('status', 1)
            ->order('id', 'desc')
            ->find();
        if (!$row || empty($row['token'])) {
            $row = Db::name('product_links')
                ->where('product_id', $productId)
                ->where('status', 1)
                ->order('id', 'desc')
                ->find();
        }
        if (!$row || empty($row['token'])) {
            return '';
        }

        return rtrim($baseUrl, '/') . '/index.php/d/' . rawurlencode((string) $row['token']);
    }

    /**
     * @return array<string, string>
     */
    public static function buildRenderVars(int $influencerId, ?int $productId, string $baseUrl): array
    {
        $inf = Db::name('influencers')->where('id', $influencerId)->find();
        if (!$inf) {
            return [];
        }
        $channels = InfluencerService::contactChannelsFromStored(isset($inf['contact_info']) ? (string) $inf['contact_info'] : null);
        $vars = [
            'tiktok_id'       => (string) ($inf['tiktok_id'] ?? ''),
            'nickname'        => (string) ($inf['nickname'] ?? ''),
            'region'          => (string) ($inf['region'] ?? ''),
            'whatsapp'        => $channels['whatsapp'],
            'zalo'            => $channels['zalo'],
            'product_name'    => '',
            'goods_url'       => '',
            'tiktok_shop_url' => '',
            'distribute_link' => '',
        ];
        if ($productId !== null && $productId > 0) {
            $p = Db::name('products')->where('id', $productId)->find();
            if ($p) {
                $vars['product_name'] = (string) ($p['name'] ?? '');
                $vars['goods_url'] = (string) ($p['goods_url'] ?? '');
                $vars['tiktok_shop_url'] = (string) ($p['tiktok_shop_url'] ?? '');
            }
            $vars['distribute_link'] = self::resolveDistributeLink($influencerId, $productId, $baseUrl);
        }

        return $vars;
    }

    /**
     * @param array<string, string> $vars
     */
    public static function renderBody(string $body, array $vars): string
    {
        $out = $body;
        foreach ($vars as $k => $v) {
            $out = str_replace('{{' . $k . '}}', $v, $out);
        }

        return $out;
    }

    public static function waMeWithText(string $digits, string $text): string
    {
        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
    }
}
