<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;

/**
 * 火山引擎方舟（豆包视觉）：合并 config/services.php 与 system_settings。
 * 鉴权：`Authorization: Bearer <API Key>`。请求体 `model`：优先 VOLC_ARK_MODEL / 后台「模型 ID」，否则 endpoint_id（ep-）。见官方快速入门文档。
 */
class VolcArkVisionConfig
{
    /**
 * @return array{
 *   enabled:bool,
 *   access_key:string,
 *   secret_key:string,
 *   model:string,
 *   endpoint_id:string,
 *   base_url:string,
     *   max_catalog_items:int,
     *   timeout_seconds:int,
     *   match_max_tokens:int,
     *   describe_max_tokens:int,
     *   retry_times:int,
     *   verify_ssl:bool
     * }
     */
    public static function get(): array
    {
        $base = (array) (Config::get('services.volc_ark') ?: []);

        $enabledFlag = trim((string) SystemConfigService::get('volc_ark_enabled', ''));
        $ak = trim((string) SystemConfigService::get('volc_ark_access_key', '') ?: ($base['access_key'] ?? ''));
        if ($ak === '') {
            $ak = trim((string) (getenv('VOLC_ACCESS_KEY') ?: ''));
        }
        $sk = trim((string) SystemConfigService::get('volc_ark_secret_key', '') ?: ($base['secret_key'] ?? ''));
        if ($sk === '') {
            $sk = trim((string) (getenv('VOLC_SECRET_KEY') ?: ''));
        }
        /** 方舟 HTTP Bearer：优先 Access Key 框；空则用 Secret 框（常见误把 API Key 只填在「Secret」） */
        $bearer = $ak !== '' ? $ak : $sk;
        $ep = trim((string) SystemConfigService::get('volc_ark_endpoint_id', '') ?: ($base['endpoint_id'] ?? ''));
        if ($ep === '') {
            $ep = trim((string) (getenv('VOLC_ENDPOINT_ID') ?: ''));
        }
        /** model：VOLC_ARK_MODEL → 后台模型 ID → 接入点 ep-（仅 ep 时优先于配置默认，避免被默认 model 覆盖）→ config 默认（Doubao-1.5-vision-pro-32k） */
        $modelFromDb = trim((string) SystemConfigService::get('volc_ark_model_id', '') ?: '');
        $modelFromBase = trim((string) ($base['model_id'] ?? ''));
        $modelFromEnv = trim((string) (getenv('VOLC_ARK_MODEL') ?: ''));
        $model = $modelFromEnv !== '' ? $modelFromEnv : ($modelFromDb !== '' ? $modelFromDb : ($ep !== '' ? $ep : $modelFromBase));
        $url = trim((string) SystemConfigService::get('volc_ark_base_url', '') ?: ($base['base_url'] ?? ''));
        if ($url === '') {
            $url = trim((string) (getenv('VOLC_ARK_BASE_URL') ?: ''));
        }
        if ($url === '') {
            $url = 'https://ark.cn-beijing.volces.com/api/v3';
        }
        $url = rtrim($url, '/');

        $maxCatStr = trim((string) SystemConfigService::get('volc_ark_max_catalog', '') ?? '');
        $maxCatalog = $maxCatStr !== '' ? (int) $maxCatStr : (int) ($base['max_catalog_items'] ?? 250);
        if ($maxCatalog < 10) {
            $maxCatalog = 10;
        }
        if ($maxCatalog > 500) {
            $maxCatalog = 500;
        }

        $timeout = (int) ($base['timeout_seconds'] ?? 120);
        if ($timeout < 30) {
            $timeout = 30;
        }
        if ($timeout > 300) {
            $timeout = 300;
        }

        $matchTok = (int) ($base['match_max_tokens'] ?? 1024);
        if ($matchTok < 256) {
            $matchTok = 256;
        }
        if ($matchTok > 8192) {
            $matchTok = 8192;
        }

        $descTok = (int) ($base['describe_max_tokens'] ?? 220);
        if ($descTok < 64) {
            $descTok = 64;
        }
        if ($descTok > 2000) {
            $descTok = 2000;
        }

        $retry = (int) ($base['retry_times'] ?? 2);
        if ($retry < 1) {
            $retry = 1;
        }
        if ($retry > 5) {
            $retry = 5;
        }

        $verifySsl = (bool) ($base['verify_ssl'] ?? true);
        $ve = getenv('VOLC_ARK_VERIFY_SSL');
        if ($ve !== false && \trim((string) $ve) !== '') {
            $parsed = \filter_var(\trim((string) $ve), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($parsed !== null) {
                $verifySsl = $parsed;
            }
        }

        $enabled = $enabledFlag === '1' && $bearer !== '' && $model !== '';

        return [
            'enabled' => $enabled,
            'access_key' => $bearer,
            'secret_key' => $sk,
            'model' => $model,
            'endpoint_id' => $ep,
            'base_url' => $url,
            'max_catalog_items' => $maxCatalog,
            'timeout_seconds' => $timeout,
            'match_max_tokens' => $matchTok,
            'describe_max_tokens' => $descTok,
            'retry_times' => $retry,
            'verify_ssl' => $verifySsl,
        ];
    }
}
