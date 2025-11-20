<?php
// 七牛云存储配置

return [
    // 是否启用七牛云（设置为false则使用本地存储）
    'enabled' => env('qiniu.enabled', true),
    
    // Access Key
    'access_key' => env('qiniu.access_key', 'Gr61Z33pLjdunaMnwQDCuTJaHOeJa-cwibVgIPbF'),
    
    // Secret Key
    'secret_key' => env('qiniu.secret_key', 'QuAbFxTsqS6AGKfPAA8aufp_QTEE0rcCC7Q7l6R9'),
    
    // 存储空间名称（Bucket）
    'bucket' => env('qiniu.bucket', 'videowarehouse'),
    
    // 访问域名（CDN域名，必须以http://或https://开头，不要带结尾斜杠）
    'domain' => env('qiniu.domain', 'https://storage.banono-us.com'),
    
    // 上传策略配置
    'policy' => [
        // 文件大小限制（字节），0表示不限制
        'fsizeLimit' => 0,
        
        // 允许的文件类型，空数组表示不限制
        'mimeLimit' => [],
        
        // 文件保存目录前缀
        'saveKey' => '',
    ],
];

