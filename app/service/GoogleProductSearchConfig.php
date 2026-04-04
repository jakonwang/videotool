<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;

/**
 * Google Cloud Vision Product Search：合并 config/services.php、环境变量与 system_settings。
 */
class GoogleProductSearchConfig
{
    /**
     * @return array{
     *   enabled:bool,
     *   project_id:string,
     *   location:string,
     *   product_set_id:string,
     *   key_file:string,
     *   gcs_bucket:string,
     *   gcs_prefix:string,
     *   product_category:string,
     *   match_score_min:float,
     *   search_top_k:int
     * }
     */
    public static function get(): array
    {
        $base = (array) (Config::get('services.google_product_search') ?: []);

        $enabledFlag = trim((string) SystemConfigService::get('google_ps_enabled', ''));
        $project = trim((string) SystemConfigService::get('google_ps_project_id', '') ?: ($base['project_id'] ?? ''));
        $location = trim((string) SystemConfigService::get('google_ps_location', '') ?: ($base['location'] ?? ''));
        $setId = trim((string) SystemConfigService::get('google_ps_product_set_id', '') ?: ($base['product_set_id'] ?? ''));
        $keyFile = trim((string) SystemConfigService::get('google_ps_key_file', '') ?: ($base['key_file'] ?? ''));
        if ($keyFile === '') {
            $keyFile = trim((string) (getenv('GOOGLE_APPLICATION_CREDENTIALS') ?: ''));
        }
        $bucket = trim((string) SystemConfigService::get('google_ps_gcs_bucket', '') ?: ($base['gcs_bucket'] ?? ''));
        $prefix = trim((string) SystemConfigService::get('google_ps_gcs_prefix', '') ?: ($base['gcs_prefix'] ?? 'vision_product_search_refs'));
        $prefix = trim($prefix, '/');

        $cat = trim((string) SystemConfigService::get('google_ps_product_category', '') ?: ($base['product_category'] ?? 'homegoods-v2'));
        if ($cat === '') {
            $cat = 'homegoods-v2';
        }

        $minDb = trim((string) SystemConfigService::get('google_ps_match_score_min', '') ?? '');
        $matchMin = $minDb !== '' ? (float) $minDb : (float) ($base['match_score_min'] ?? 0.5);
        if ($matchMin < 0 || $matchMin > 1) {
            $matchMin = 0.5;
        }

        $topDb = trim((string) SystemConfigService::get('google_ps_search_top_k', '') ?? '');
        $topK = $topDb !== '' ? (int) $topDb : (int) ($base['search_top_k'] ?? 5);
        if ($topK < 1) {
            $topK = 1;
        }
        if ($topK > 20) {
            $topK = 20;
        }

        $keyOk = self::isKeyFileAllowed($keyFile);

        $enabled = $enabledFlag === '1'
            && $project !== '' && $location !== '' && $setId !== ''
            && $keyOk && $bucket !== '';

        return [
            'enabled' => $enabled,
            'project_id' => $project,
            'location' => $location,
            'product_set_id' => $setId,
            'key_file' => $keyFile,
            'gcs_bucket' => $bucket,
            'gcs_prefix' => $prefix,
            'product_category' => $cat,
            'match_score_min' => $matchMin,
            'search_top_k' => $topK,
        ];
    }

    /**
     * 密钥 JSON 须存在且可读，且解析后的真实路径不得位于 public 目录下（避免被 Web 直接访问）。
     */
    public static function isKeyFileAllowed(string $keyFile): bool
    {
        $keyFile = trim($keyFile);
        if ($keyFile === '' || !is_file($keyFile) || !is_readable($keyFile)) {
            return false;
        }
        $keyReal = realpath($keyFile);
        if ($keyReal === false) {
            return false;
        }
        $public = realpath(root_path() . 'public');
        if ($public === false) {
            return true;
        }
        $pub = rtrim(str_replace('\\', '/', $public), '/') . '/';
        $kr = str_replace('\\', '/', $keyReal);
        if (str_starts_with($kr . '/', $pub) || $kr === rtrim($pub, '/')) {
            return false;
        }

        return true;
    }
}
