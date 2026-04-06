<?php
// Session配置
// expire：秒。ThinkPHP 文件驱动下，过小会导致后台频繁掉线（原 1440 仅约 24 分钟）。
// 可在项目根 .env 设置 SESSION_EXPIRE=86400（示例：24 小时）
return [
    // session name
    'name'           => 'PHPSESSID',
    // SESSION_ID的提交变量,解决flash上传跨域
    'var_session_id' => '',
    // 驱动方式 支持file cache
    'type'           => 'file',
    // 存储连接标识 当type使用cache的时候有效
    'store'          => null,
    // 过期时间（秒）
    'expire'         => (int) env('SESSION_EXPIRE', 28800),
    // 前缀
    'prefix'         => '',
];

