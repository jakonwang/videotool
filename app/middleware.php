<?php
// 全局中间件定义文件
// 注意：中间件内部会根据 ENTRY_FILE 判断是否对 admin 生效

return [
    \think\middleware\SessionInit::class,
    \app\middleware\TraceIdJsonMiddleware::class,
    \app\middleware\AdminAuthMiddleware::class,
];
