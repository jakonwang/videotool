<?php
// 简化版入口文件用于测试
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 定义应用目录
define('__ROOT__', dirname(__DIR__));

// 加载框架引导文件
require __DIR__ . '/../vendor/autoload.php';

// 执行应用
$app = new think\App();
$http = $app->http;

// 响应输出
$response = $http->run();
$response->send();

$http->end($response);

