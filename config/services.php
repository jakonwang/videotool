<?php
/**
 * 第三方服务默认配置（可被后台 system_settings 与环境变量覆盖）
 */
return [
    'aliyun_is' => [
        'access_key_id' => env('ALIYUN_IS_ACCESS_KEY_ID', ''),
        'access_key_secret' => env('ALIYUN_IS_ACCESS_KEY_SECRET', ''),
        /** 例：imagesearch.cn-shanghai.aliyuncs.com */
        'endpoint' => env('ALIYUN_IS_ENDPOINT', ''),
        /** 控制台实例名称（非实例 ID） */
        'instance_name' => env('ALIYUN_IS_INSTANCE_NAME', ''),
        /** 区域 ID，留空则从 endpoint 解析 */
        'region_id' => env('ALIYUN_IS_REGION_ID', ''),
        /**
         * 商品图搜类目：官方类目参考见文档「图像搜索图片类目与ID列表」。
         * 5=配饰(Accessories)，耳环等饰品通常用此类目以提高精度。
         */
        'category_id' => (int) env('ALIYUN_IS_CATEGORY_ID', 5),
        /** SearchImageByPic 返回条数（要求 5） */
        'search_num' => (int) env('ALIYUN_IS_SEARCH_NUM', 5),
        'connect_timeout_ms' => (int) env('ALIYUN_IS_CONNECT_TIMEOUT_MS', 8000),
        'read_timeout_ms' => (int) env('ALIYUN_IS_READ_TIMEOUT_MS', 25000),
        /** 导入同步队列两次 AddImage 间隔（毫秒），缓解 QPS 限流 */
        'qps_delay_ms' => (int) env('ALIYUN_IS_QPS_DELAY_MS', 300),
        /** 是否启用主体识别（类目内商品图搜建议 true） */
        'crop' => env('ALIYUN_IS_CROP', '1') !== '0',
        /** SearchImageByPic 是否按 ProductId 去重 */
        'distinct_product_id' => true,
    ],
    /**
     * Google Cloud Vision — Product Search（拍照寻款，可选）
     * 后台「设置」或环境变量可覆盖；参考图须先上传到 GCS（见 GoogleProductSearchService）。
     */
    'google_product_search' => [
        'project_id' => env('GOOGLE_PS_PROJECT_ID', ''),
        /** 与控制台 Product Search 区域一致，如 us-east1、asia-east1 */
        'location' => env('GOOGLE_PS_LOCATION', ''),
        'product_set_id' => env('GOOGLE_PS_PRODUCT_SET_ID', ''),
        /** 服务账号 JSON 绝对路径，须不在 public 下 */
        'key_file' => env('GOOGLE_PS_KEY_FILE', env('GOOGLE_APPLICATION_CREDENTIALS', '')),
        /** 存放参考图的 Bucket（须与项目同账号，且 Vision 可读取） */
        'gcs_bucket' => env('GOOGLE_PS_GCS_BUCKET', ''),
        'gcs_prefix' => env('GOOGLE_PS_GCS_PREFIX', 'vision_product_search_refs'),
        'product_category' => env('GOOGLE_PS_PRODUCT_CATEGORY', 'homegoods-v2'),
        /** 低于此分时提示「未找到完全匹配款式」 */
        'match_score_min' => (float) env('GOOGLE_PS_MATCH_SCORE_MIN', 0.5),
        'search_top_k' => (int) env('GOOGLE_PS_SEARCH_TOP_K', 5),
    ],
    /**
     * 火山引擎方舟（豆包视觉）：OpenAI 兼容 Chat Completions。
     * 鉴权：Bearer = 控制台 API Key。请求体 model：见 VOLC_ARK_MODEL / model_id，或与官方一致填推理接入点 ep-。
     */
    'volc_ark' => [
        'access_key' => env('VOLC_ACCESS_KEY', ''),
        'secret_key' => env('VOLC_SECRET_KEY', ''),
        /** 请求体 model：默认 Doubao-1.5-vision-pro-32k（方舟 Model ID 多为 doubao-1-5-vision-pro-32k，以控制台「模型列表」为准，可能带日期后缀） */
        'model_id' => env('VOLC_ARK_MODEL', 'doubao-1-5-vision-pro-32k'),
        'endpoint_id' => env('VOLC_ENDPOINT_ID', ''),
        'base_url' => rtrim((string) env('VOLC_ARK_BASE_URL', 'https://ark.cn-beijing.volces.com/api/v3'), '/'),
        'max_catalog_items' => (int) env('VOLC_ARK_MAX_CATALOG', 250),
        /** 全自动寻款：注入豆包 system 的候选条数（默认 50，与「最可能」预筛一致：按索引 id 倒序） */
        'auto_match_catalog_limit' => (int) env('VOLC_ARK_AUTO_CATALOG', 50),
        'timeout_seconds' => (int) env('VOLC_ARK_TIMEOUT', 120),
        'match_max_tokens' => (int) env('VOLC_ARK_MATCH_MAX_TOKENS', 1024),
        /** 全自动寻款仅输出编号或 NULL，宜小以省 token */
        'auto_match_max_tokens' => (int) env('VOLC_ARK_AUTO_MATCH_MAX_TOKENS', 64),
        'describe_max_tokens' => (int) env('VOLC_ARK_DESCRIBE_MAX_TOKENS', 220),
        'retry_times' => (int) env('VOLC_ARK_RETRY', 2),
        /** HTTPS 证书校验；可被环境变量 VOLC_ARK_VERIFY_SSL 覆盖（见 VolcArkVisionConfig） */
        'verify_ssl' => true,
    ],
];
