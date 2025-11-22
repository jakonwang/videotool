<?php
return [
    // 是否启用下载缓存
    'enabled' => env('download_cache.enabled', true),

    // 缓存根目录，默认 runtime/download_cache
    'root' => env('download_cache.root', rtrim(runtime_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'download_cache'),

    // 缓存有效期（秒）
    'expire_seconds' => env('download_cache.expire', 3 * 24 * 60 * 60), // 3天

    // 最小缓存文件大小（字节），太小的文件没必要缓存
    'min_file_size' => env('download_cache.min_size', 200 * 1024), // 200KB

    // 是否只缓存远程/CDN文件
    'remote_only' => env('download_cache.remote_only', true),
];

