<?php
/**
 * OpenAI Vision（寻款）：可用环境变量或后台 system_settings 覆盖
 */
return [
    'api_key' => env('OPENAI_API_KEY', ''),
    /** 兼容代理 / Azure OpenAI 时改 base，默认官方 */
    'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'model' => env('OPENAI_VISION_MODEL', 'gpt-4o-mini'),
    /** 导入时每行是否调用 Vision 生成 ai_description（会按行计费） */
    'describe_on_import' => env('OPENAI_DESCRIBE_ON_IMPORT', '1') !== '0',
    /** 拍照寻款单次请求带入的最大库内条数（防超长 prompt） */
    'max_catalog_items' => (int) env('OPENAI_MAX_CATALOG_ITEMS', 250),
    'timeout_seconds' => (int) env('OPENAI_TIMEOUT', 120),
    'describe_max_tokens' => (int) env('OPENAI_DESCRIBE_MAX_TOKENS', 220),
    'match_max_tokens' => (int) env('OPENAI_MATCH_MAX_TOKENS', 700),
];
