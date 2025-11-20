<?php
// [ 后台入口文件 ]
namespace think;

// 定义应用目录
define('__ROOT__', dirname(__DIR__));

// 定义入口文件类型（用于路由判断）
define('ENTRY_FILE', 'admin');

// 加载框架引导文件
require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用并响应
$app = new App();
$http = $app->http;

// 如果没有路径或路径为空，默认跳转到后台首页
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if (empty($pathInfo) || $pathInfo === '/') {
    $_SERVER['PATH_INFO'] = '/';
    $_SERVER['REQUEST_URI'] = '/';
}

$response = $http->run();

$response->send();

$http->end($response);

