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
];
