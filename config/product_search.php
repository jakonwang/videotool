<?php
// 图片搜款式：Python 解释器。未设置环境变量时：Windows 留空（由代码使用 py -3），Linux/macOS 默认 python3。Web 进程 PATH 常与 shell 不同，生产环境建议 PRODUCT_SEARCH_PYTHON=/usr/bin/python3 等绝对路径。
return [
    'python_bin' => env('PRODUCT_SEARCH_PYTHON', PHP_OS_FAMILY === 'Windows' ? '' : 'python3'),
    /** @var int 搜索返回条数 */
    'top_k' => 3,
    /** @var int 导入时单张图下载超时（秒） */
    'fetch_image_timeout' => 30,
    /** @var int 异步导入每行 AI 调用后的休眠（微秒）；0 最快。遇方舟限流时可改为 100000～200000 */
    'import_ai_usleep_microseconds' => (int) (env('PRODUCT_STYLE_IMPORT_AI_USLEEP', 0)),
];
