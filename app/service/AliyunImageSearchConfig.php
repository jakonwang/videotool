<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;

/**
 * 合并 config/services.php、环境变量与后台 system_settings 中的阿里云图像搜索配置
 */
class AliyunImageSearchConfig
{
    /**
     * @return array{
     *   enabled:bool,
     *   access_key_id:string,
     *   access_key_secret:string,
     *   endpoint:string,
     *   instance_name:string,
     *   region_id:string,
     *   category_id:int,
     *   search_num:int,
     *   connect_timeout_ms:int,
     *   read_timeout_ms:int,
     *   qps_delay_ms:int,
     *   crop:bool,
     *   distinct_product_id:bool
     * }
     */
    public static function get(): array
    {
        $base = (array) (Config::get('services.aliyun_is') ?: []);
        $ak = trim((string) (SystemConfigService::get('aliyun_is_access_key_id', '') ?: ($base['access_key_id'] ?? '')));
        $sk = trim((string) (SystemConfigService::get('aliyun_is_access_key_secret', '') ?: ($base['access_key_secret'] ?? '')));
        $endpoint = trim((string) (SystemConfigService::get('aliyun_is_endpoint', '') ?: ($base['endpoint'] ?? '')));
        $instance = trim((string) (SystemConfigService::get('aliyun_is_instance_name', '') ?: ($base['instance_name'] ?? '')));
        $regionOverride = trim((string) (SystemConfigService::get('aliyun_is_region_id', '') ?: ($base['region_id'] ?? '')));
        $enabledFlag = trim((string) (SystemConfigService::get('aliyun_is_enabled', '')));

        $catDb = trim((string) (SystemConfigService::get('aliyun_is_category_id', '') ?? ''));
        $cat = $catDb !== '' ? (int) $catDb : (int) ($base['category_id'] ?? 5);
        if ($cat < 0) {
            $cat = 5;
        }
        $numDb = trim((string) (SystemConfigService::get('aliyun_is_search_num', '') ?? ''));
        $num = $numDb !== '' ? (int) $numDb : (int) ($base['search_num'] ?? 5);
        if ($num < 1) {
            $num = 5;
        }
        if ($num > 100) {
            $num = 100;
        }

        $regionId = $regionOverride !== '' ? $regionOverride : self::regionIdFromEndpoint($endpoint);

        $enabled = $enabledFlag === '1'
            && $ak !== '' && $sk !== '' && $endpoint !== '' && $instance !== '' && $regionId !== '';

        return [
            'enabled' => $enabled,
            'access_key_id' => $ak,
            'access_key_secret' => $sk,
            'endpoint' => $endpoint,
            'instance_name' => $instance,
            'region_id' => $regionId,
            'category_id' => $cat,
            'search_num' => $num,
            'connect_timeout_ms' => (int) ($base['connect_timeout_ms'] ?? 8000),
            'read_timeout_ms' => (int) ($base['read_timeout_ms'] ?? 25000),
            'qps_delay_ms' => (int) ($base['qps_delay_ms'] ?? 300),
            'crop' => (bool) ($base['crop'] ?? true),
            'distinct_product_id' => (bool) ($base['distinct_product_id'] ?? true),
        ];
    }

    public static function regionIdFromEndpoint(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') {
            return '';
        }
        if (preg_match('/imagesearch(?:-vpc)?\.([a-z0-9-]+)\.aliyuncs\.com/i', $endpoint, $m)) {
            return $m[1];
        }

        return '';
    }
}
