<?php
// API测试文件
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'code' => 0,
    'msg' => 'API测试成功',
    'data' => [
        'php_version' => PHP_VERSION,
        'time' => date('Y-m-d H:i:s')
    ]
], JSON_UNESCAPED_UNICODE);

