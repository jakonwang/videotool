<?php
// API调试文件
error_reporting(E_ALL);
ini_set('display_errors', '1');

define('__ROOT__', dirname(__DIR__));

require __DIR__ . '/../vendor/autoload.php';

try {
    $app = new \think\App();
    $http = $app->http;
    
    // 测试路由
    echo "路由测试：<br>";
    $routes = $http->route->getRuleList();
    echo "<pre>";
    print_r($routes);
    echo "</pre>";
    
    // 测试API
    echo "<br>测试API访问：<br>";
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/video/getVideo?platform=tiktok';
    $_GET['platform'] = 'tiktok';
    
} catch (Exception $e) {
    echo "错误：" . $e->getMessage() . "<br>";
    echo "文件：" . $e->getFile() . "<br>";
    echo "行号：" . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

