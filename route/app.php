<?php
// 应用路由文件（前台入口 index.php 使用）
use think\facade\Route;

// 前台路由 - 使用完整命名空间避免大小写问题
// 只在前台入口文件时加载此路由（或者未定义 ENTRY_FILE 时，默认前台）
if (!defined('ENTRY_FILE') || ENTRY_FILE === 'index') {
    Route::get('/', 'app\controller\index\Index@index');
}

// 只加载 API 路由，不加载后台路由
// 注意：admin.php 路由是后台入口 admin.php 专用的，不应该在这里加载
if (file_exists(__DIR__ . '/api.php')) {
    require __DIR__ . '/api.php';
}

