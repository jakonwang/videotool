<?php
use think\facade\Route;

// 后台路由 - 使用完整命名空间避免大小写问题
// 注意：由于入口文件是 admin.php，路由组不需要再加 admin 前缀
// 只在后台入口文件时加载此路由
if (defined('ENTRY_FILE') && ENTRY_FILE === 'admin') {
    Route::get('/', 'app\controller\admin\Index@index');
    
    // 平台管理 - 直接定义完整路径，避免路由组匹配问题
    Route::get('platform/edit/<id>', 'app\controller\admin\Platform@edit');
    Route::post('platform/edit/<id>', 'app\controller\admin\Platform@edit');
    Route::post('platform/delete/<id>', 'app\controller\admin\Platform@delete');
    Route::get('platform/add', 'app\controller\admin\Platform@add');
    Route::post('platform/add', 'app\controller\admin\Platform@add');
    Route::get('platform', 'app\controller\admin\Platform@index');
    
    // 设备管理
    Route::get('device/edit/<id>', 'app\controller\admin\Device@edit');
    Route::post('device/edit/<id>', 'app\controller\admin\Device@edit');
    Route::post('device/delete/<id>', 'app\controller\admin\Device@delete');
    Route::get('device/getByPlatform', 'app\controller\admin\Device@getByPlatform');
    Route::get('device/add', 'app\controller\admin\Device@add');
    Route::post('device/add', 'app\controller\admin\Device@add');
    Route::get('device', 'app\controller\admin\Device@index');
    
    // 视频管理
    Route::get('video/edit/<id>', 'app\controller\admin\Video@edit');
    Route::post('video/edit/<id>', 'app\controller\admin\Video@edit');
    Route::post('video/delete/<id>', 'app\controller\admin\Video@delete');
    Route::post('video/batchDelete', 'app\controller\admin\Video@batchDelete');
    Route::get('video/batchUpload', 'app\controller\admin\Video@batchUpload');
    Route::post('video/batchUpload', 'app\controller\admin\Video@batchUpload');
    Route::post('video/uploadChunk', 'app\controller\admin\Video@uploadChunk');
    Route::get('video/batchEdit', 'app\controller\admin\Video@batchEdit');
    Route::post('video/batchEdit', 'app\controller\admin\Video@batchEdit');
    Route::get('video', 'app\controller\admin\Video@index');
}

