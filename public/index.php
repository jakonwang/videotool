<?php
// [ 应用入口文件 ]
namespace think;

// 定义应用目录
define('__ROOT__', dirname(__DIR__));

// 定义入口文件类型（用于路由判断）
define('ENTRY_FILE', 'index');

// 加载框架引导文件
require __DIR__ . '/../vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new App())->http;

$response = $http->run();

$response->send();

$http->end($response);

