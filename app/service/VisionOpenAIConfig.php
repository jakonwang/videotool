<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;

/**
 * 合并 config/openai.php 与后台 system_settings
 */
class VisionOpenAIConfig
{
    /**
     * @return array{
     *   enabled:bool,
     *   api_key:string,
     *   base_url:string,
     *   model:string,
     *   describe_on_import:bool,
     *   max_catalog_items:int,
     *   timeout_seconds:int,
     *   describe_max_tokens:int,
     *   match_max_tokens:int
     * }
     */
    public static function get(): array
    {
        $base = (array) (Config::get('openai') ?: []);
        $key = trim((string) (SystemConfigService::get('openai_api_key', '') ?: ($base['api_key'] ?? '')));
        $url = trim((string) (SystemConfigService::get('openai_base_url', '') ?: ($base['base_url'] ?? 'https://api.openai.com/v1')));
        $url = rtrim($url, '/');
        $model = trim((string) (SystemConfigService::get('openai_model', '') ?: ($base['model'] ?? 'gpt-4o-mini')));
        if ($model === '') {
            $model = 'gpt-4o-mini';
        }
        $doImport = SystemConfigService::get('openai_describe_on_import', '');
        if ($doImport === '' || $doImport === null) {
            $describeOnImport = (bool) ($base['describe_on_import'] ?? true);
        } else {
            $describeOnImport = $doImport === '1';
        }
        $maxCatStr = trim((string) (SystemConfigService::get('openai_max_catalog', '') ?? ''));
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

        return [
            'enabled' => $key !== '',
            'api_key' => $key,
            'base_url' => $url,
            'model' => $model,
            'describe_on_import' => $describeOnImport,
            'max_catalog_items' => $maxCatalog,
            'timeout_seconds' => $timeout,
            'describe_max_tokens' => (int) ($base['describe_max_tokens'] ?? 220),
            'match_max_tokens' => (int) ($base['match_max_tokens'] ?? 700),
        ];
    }
}
