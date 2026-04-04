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
];
