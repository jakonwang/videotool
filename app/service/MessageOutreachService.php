<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

class MessageOutreachService
{
    private const LANG_ZH = 'zh';
    private const LANG_EN = 'en';
    private const LANG_VI = 'vi';

    /**
     * 10个亲和力 Emoji
     *
     * @var list<string>
     */
    private static array $emojiPool = ['🙂', '😊', '😄', '🤝', '✨', '🌟', '💖', '🙌', '🎉', '🔥'];

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
     * 优先匹配达人+商品的启用分销链；否则回退到该商品任意启用链
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
            'tiktok_id' => (string) ($inf['tiktok_id'] ?? ''),
            'nickname' => (string) ($inf['nickname'] ?? ''),
            'region' => (string) ($inf['region'] ?? ''),
            'whatsapp' => (string) ($channels['whatsapp'] ?? ''),
            'zalo' => (string) ($channels['zalo'] ?? ''),
            'product_name' => '',
            'goods_url' => '',
            'tiktok_shop_url' => '',
            'distribute_link' => '',
            'current_time_period' => self::currentTimeGreeting(),
            'random_emoji' => self::randomEmoji(),
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
     * 智能变量解析：支持 {{ var }} / {{var}}
     *
     * @param array<string, string> $vars
     */
    public static function renderBody(string $body, array $vars): string
    {
        $rendered = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', static function (array $matches) use ($vars): string {
            $key = isset($matches[1]) ? mb_strtolower((string) $matches[1], 'UTF-8') : '';
            if ($key === '') {
                return (string) ($matches[0] ?? '');
            }

            if (array_key_exists($key, $vars)) {
                return (string) $vars[$key];
            }

            if ($key === 'current_time_period') {
                return self::currentTimeGreeting();
            }
            if ($key === 'random_emoji') {
                return self::randomEmoji();
            }

            return (string) ($matches[0] ?? '');
        }, $body);

        return $rendered !== null ? $rendered : $body;
    }

    public static function waMeWithText(string $digits, string $text): string
    {
        $normalized = InfluencerService::normalizeWhatsappNumber($digits);
        if ($normalized === '') {
            return '';
        }

        return 'https://wa.me/' . $normalized . '?text=' . rawurlencode($text);
    }

    public static function buildZaloUrl(string $zaloToken): string
    {
        $normalized = InfluencerService::normalizeZaloToken($zaloToken);
        if ($normalized === '') {
            return '';
        }

        return 'https://zalo.me/' . $normalized;
    }

    /**
     * 根据达人 region 推断模板语言；未知默认 en
     */
    public static function inferTemplateLangByRegion(string $region): string
    {
        $r = mb_strtolower(trim($region), 'UTF-8');
        if ($r === '') {
            return self::LANG_EN;
        }
        if (str_contains($r, 'vn') || str_contains($r, 'vietnam')) {
            return self::LANG_VI;
        }
        if (str_contains($r, 'zh') || str_contains($r, 'cn') || str_contains($r, 'china')) {
            return self::LANG_ZH;
        }
        if (str_contains($r, 'en') || str_contains($r, 'us') || str_contains($r, 'uk') || str_contains($r, 'english')) {
            return self::LANG_EN;
        }

        return self::LANG_EN;
    }

    /**
     * 基于 region 选择最优模板语言，未命中时回退 en
     *
     * @param array<string, mixed> $baseTemplate
     * @return array<string, mixed>
     */
    public static function pickTemplateVariantByRegion(array $baseTemplate, string $region): array
    {
        $baseLang = self::normalizeTemplateLang((string) ($baseTemplate['lang'] ?? self::LANG_EN));
        $langs = self::templateLangPriority($region, $baseLang);
        $templateKey = trim((string) ($baseTemplate['template_key'] ?? ''));

        if ($templateKey !== '') {
            foreach ($langs as $lang) {
                $row = Db::name('message_templates')
                    ->where('template_key', $templateKey)
                    ->where('lang', $lang)
                    ->where('status', 1)
                    ->order('id', 'desc')
                    ->find();
                if (is_array($row)) {
                    return $row;
                }
            }
        }

        $name = trim((string) ($baseTemplate['name'] ?? ''));
        if ($name !== '') {
            foreach ($langs as $lang) {
                $row = Db::name('message_templates')
                    ->where('name', $name)
                    ->where('lang', $lang)
                    ->where('status', 1)
                    ->order('id', 'desc')
                    ->find();
                if (is_array($row)) {
                    return $row;
                }
            }
        }

        return $baseTemplate;
    }

    /**
     * 05-11: Chào buổi sáng
     * 12-18: Chào buổi chiều
     * 19-23: Chào buổi tối
     * 00-04: Chào buổi tối
     */
    public static function currentTimeGreeting(string $region = ''): string
    {
        $hour = (int) date('G');
        if ($hour >= 5 && $hour <= 11) {
            return 'Chào buổi sáng';
        }
        if ($hour >= 12 && $hour <= 18) {
            return 'Chào buổi chiều';
        }

        return 'Chào buổi tối';
    }

    public static function randomEmoji(): string
    {
        $pool = self::$emojiPool;
        $idx = random_int(0, count($pool) - 1);

        return $pool[$idx];
    }

    private static function normalizeTemplateLang(string $lang): string
    {
        $lang = mb_strtolower(trim($lang), 'UTF-8');
        if (in_array($lang, [self::LANG_ZH, self::LANG_EN, self::LANG_VI], true)) {
            return $lang;
        }

        return self::LANG_EN;
    }

    /**
     * @return list<string>
     */
    private static function templateLangPriority(string $region, string $baseLang): array
    {
        $wanted = self::inferTemplateLangByRegion($region);
        $priority = [$wanted, self::LANG_EN, $baseLang];
        $unique = [];
        foreach ($priority as $lang) {
            $normalized = self::normalizeTemplateLang($lang);
            if (!in_array($normalized, $unique, true)) {
                $unique[] = $normalized;
            }
        }

        return $unique;
    }
}
