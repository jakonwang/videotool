<?php
// 图片搜款式：Python 解释器与脚本路径（Windows 可设为 C:\Python311\python.exe）
return [
    'python_bin' => env('PRODUCT_SEARCH_PYTHON', 'python'),
    /** @var int 搜索返回条数 */
    'top_k' => 3,
    /** @var int 导入时单张图下载超时（秒） */
    'fetch_image_timeout' => 30,
];
