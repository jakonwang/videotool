<?php
declare(strict_types=1);

namespace app\service;

use app\service\SystemConfigService;

/**
 * 视频封面：无封面时使用后台配置的默认图或内置 SVG
 */
class VideoCoverService
{
    public const BUILTIN_DEFAULT = '/static/default-cover.svg';

    /**
     * 返回用于展示/接口的封面地址（相对站点根路径或完整 URL）
     */
    public static function pick(?string $coverUrl): string
    {
        $c = trim((string) $coverUrl);
        if ($c !== '') {
            return $c;
        }
        $def = SystemConfigService::get('default_cover_url', '');
        if ($def !== null && trim($def) !== '') {
            return trim($def);
        }

        return self::BUILTIN_DEFAULT;
    }

    /**
     * 后台列表等：img 的 src
     */
    public static function displayUrl(?string $coverUrl): string
    {
        return self::pick($coverUrl);
    }
}
