<?php
use think\facade\Route;

// 后台路由 - 使用完整命名空间避免大小写问题
// 注意：由于入口文件是 admin.php，路由组不需要再加 admin 前缀
// 只在后台入口文件时加载此路由
if (defined('ENTRY_FILE') && ENTRY_FILE === 'admin') {
    Route::get('/', 'app\controller\admin\Index@index');

    // 后台认证
    Route::get('auth/login', 'app\controller\admin\Auth@login');
    Route::post('auth/login', 'app\controller\admin\Auth@login');
    Route::get('auth/logout', 'app\controller\admin\Auth@logoutPage');
    Route::post('auth/logout', 'app\controller\admin\Auth@logout');

    // 仪表盘统计（只读 JSON）
    Route::get('stats/overview', 'app\controller\admin\Stats@overview');
    Route::get('stats/trends', 'app\controller\admin\Stats@trends');
    Route::get('stats/platformDistribution', 'app\controller\admin\Stats@platformDistribution');
    Route::get('stats/downloadErrorTrends', 'app\controller\admin\Stats@downloadErrorTrends');
    Route::get('stats/downloadErrorTop', 'app\controller\admin\Stats@downloadErrorTop');
    Route::get('stats/productDistribution', 'app\controller\admin\Stats@productDistribution');
    Route::get('stats/storageUsage', 'app\controller\admin\Stats@storageUsage');

    Route::get('settings', 'app\controller\admin\Settings@index');
    Route::post('settings', 'app\controller\admin\Settings@index');

    // 用户（管理员账号）
    Route::get('user/list', 'app\controller\admin\User@listJson');
    Route::get('user/listJson', 'app\controller\admin\User@listJson');
    Route::post('user/create', 'app\controller\admin\User@create');
    Route::post('user/update', 'app\controller\admin\User@update');
    Route::post('user/toggle', 'app\controller\admin\User@toggle');
    Route::post('user/resetPassword', 'app\controller\admin\User@resetPassword');
    Route::post('user/delete', 'app\controller\admin\User@delete');
    Route::get('user', 'app\controller\admin\User@index');
    
    // 平台管理 - 直接定义完整路径，避免路由组匹配问题
    Route::get('platform/list', 'app\controller\admin\Platform@listJson');
    Route::get('platform/listJson', 'app\controller\admin\Platform@listJson');
    Route::get('platform/edit/<id>', 'app\controller\admin\Platform@edit');
    Route::post('platform/edit/<id>', 'app\controller\admin\Platform@edit');
    Route::post('platform/delete/<id>', 'app\controller\admin\Platform@delete');
    Route::get('platform/add', 'app\controller\admin\Platform@add');
    Route::post('platform/add', 'app\controller\admin\Platform@add');
    Route::get('platform', 'app\controller\admin\Platform@index');
    
    // 设备管理
    Route::get('device/list', 'app\controller\admin\Device@listJson');
    Route::get('device/listJson', 'app\controller\admin\Device@listJson');
    Route::get('device/edit/<id>', 'app\controller\admin\Device@edit');
    Route::post('device/edit/<id>', 'app\controller\admin\Device@edit');
    Route::post('device/delete/<id>', 'app\controller\admin\Device@delete');
    Route::get('device/getByPlatform', 'app\controller\admin\Device@getByPlatform');
    Route::get('device/add', 'app\controller\admin\Device@add');
    Route::post('device/add', 'app\controller\admin\Device@add');
    Route::get('device', 'app\controller\admin\Device@index');
    
    // 商品
    Route::get('product/list', 'app\controller\admin\Product@listJson');
    Route::get('product/listJson', 'app\controller\admin\Product@listJson');
    Route::get('product/edit/<id>', 'app\controller\admin\Product@edit');
    Route::post('product/edit/<id>', 'app\controller\admin\Product@edit');
    Route::post('product/delete/<id>', 'app\controller\admin\Product@delete');
    Route::get('product/add', 'app\controller\admin\Product@add');
    Route::post('product/add', 'app\controller\admin\Product@add');
    Route::post('product/uploadThumb', 'app\controller\admin\Product@uploadThumb');
    Route::get('product', 'app\controller\admin\Product@index');

    // 分发链接
    Route::get('distribute/list', 'app\controller\admin\Distribute@listJson');
    Route::get('distribute/listJson', 'app\controller\admin\Distribute@listJson');
    Route::get('distribute/add', 'app\controller\admin\Distribute@add');
    Route::post('distribute/add', 'app\controller\admin\Distribute@add');
    Route::post('distribute/delete/<id>', 'app\controller\admin\Distribute@delete');
    Route::post('distribute/toggle/<id>', 'app\controller\admin\Distribute@toggle');
    Route::get('distribute', 'app\controller\admin\Distribute@index');

    // 视频管理
    Route::get('video/list', 'app\\controller\\admin\\Video@listJson');
    Route::get('video/listJson', 'app\\controller\\admin\\Video@listJson');
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

    // 缓存管理
    Route::get('cache/list', 'app\controller\admin\Cache@listJson');
    Route::get('cache/listJson', 'app\controller\admin\Cache@listJson');
    Route::get('cache', 'app\controller\admin\Cache@index');
    Route::post('cache/delete/<hash>', 'app\controller\admin\Cache@delete');
    Route::post('cache/clear', 'app\controller\admin\Cache@clear');
    Route::get('cache/download/<hash>', 'app\controller\admin\Cache@download');
    
    // 下载错误监控
    Route::get('downloadLog/list', 'app\controller\admin\DownloadLog@listJson');
    Route::get('downloadLog/listJson', 'app\controller\admin\DownloadLog@listJson');
    Route::get('downloadLog', 'app\controller\admin\DownloadLog@index');
    Route::post('downloadLog/clear', 'app\controller\admin\DownloadLog@clear');

    // 桌面端：发卡 / 版本
    Route::get('client_license/list', 'app\controller\admin\ClientLicense@listJson');
    Route::get('client_license/listJson', 'app\controller\admin\ClientLicense@listJson');
    Route::post('client_license/add', 'app\controller\admin\ClientLicense@add');
    Route::post('client_license/update/<id>', 'app\controller\admin\ClientLicense@update');
    Route::post('client_license/toggle/<id>', 'app\controller\admin\ClientLicense@toggle');
    Route::post('client_license/unbind/<id>', 'app\controller\admin\ClientLicense@unbind');
    Route::post('client_license/delete/<id>', 'app\controller\admin\ClientLicense@delete');
    Route::get('client_license', 'app\controller\admin\ClientLicense@index');

    Route::get('client_version/list', 'app\controller\admin\ClientVersion@listJson');
    Route::get('client_version/listJson', 'app\controller\admin\ClientVersion@listJson');
    Route::post('client_version/add', 'app\controller\admin\ClientVersion@add');
    Route::post('client_version/uploadPackage', 'app\controller\admin\ClientVersion@uploadPackage');
    Route::post('client_version/update/<id>', 'app\controller\admin\ClientVersion@update');
    Route::post('client_version/toggle/<id>', 'app\controller\admin\ClientVersion@toggle');
    Route::post('client_version/delete/<id>', 'app\controller\admin\ClientVersion@delete');
    Route::get('client_version', 'app\controller\admin\ClientVersion@index');

    // 图片搜款式
    Route::get('product_search/list', 'app\controller\admin\ProductSearch@listJson');
    Route::get('product_search/listJson', 'app\controller\admin\ProductSearch@listJson');
    Route::post('product_search/importCsv', 'app\controller\admin\ProductSearch@importCsv');
    Route::get('product_search/importTaskStatus', 'app\controller\admin\ProductSearch@importTaskStatus');
    Route::post('product_search/importTaskTick', 'app\controller\admin\ProductSearch@importTaskTick');
    Route::post('product_search/syncAliyunQueue', 'app\controller\admin\ProductSearch@syncAliyunQueue');
    Route::post('product_search/batchDelete', 'app\controller\admin\ProductSearch@deleteBatch');
    Route::post('product_search/update/<id>', 'app\controller\admin\ProductSearch@updateItem');
    Route::post('product_search/delete/<id>', 'app\controller\admin\ProductSearch@delete');
    Route::get('product_search/sampleCsv', 'app\controller\admin\ProductSearch@sampleCsv');
    Route::get('product_search/exportCsv', 'app\controller\admin\ProductSearch@exportCsv');
    Route::get('product_search', 'app\controller\admin\ProductSearch@index');

    // 达人名录（TikTok @handle）
    Route::get('influencer/list', 'app\controller\admin\Influencer@listJson');
    Route::get('influencer/listJson', 'app\controller\admin\Influencer@listJson');
    Route::get('influencer/search', 'app\controller\admin\Influencer@searchJson');
    Route::post('influencer/importCsv', 'app\controller\admin\Influencer@importCsv');
    Route::get('influencer/importTaskStatus', 'app\controller\admin\Influencer@importTaskStatus');
    Route::post('influencer/importTaskTick', 'app\controller\admin\Influencer@importTaskTick');
    Route::get('influencer/sampleCsv', 'app\controller\admin\Influencer@sampleCsv');
    Route::get('influencer/exportCsv', 'app\controller\admin\Influencer@exportCsv');
    Route::post('influencer/update', 'app\controller\admin\Influencer@update');
    Route::post('influencer/updateStatus', 'app\controller\admin\Influencer@updateStatus');
    Route::get('influencer/outreachHistory', 'app\controller\admin\Influencer@outreachHistory');
    Route::post('influencer/delete', 'app\controller\admin\Influencer@delete');
    Route::get('influencer', 'app\controller\admin\Influencer@index');

    // 分类管理（商品/达人）
    Route::get('category/list', 'app\controller\admin\Category@listJson');
    Route::get('category/listJson', 'app\controller\admin\Category@listJson');
    Route::get('category/options', 'app\controller\admin\Category@options');
    Route::post('category/save', 'app\controller\admin\Category@save');
    Route::post('category/delete', 'app\controller\admin\Category@delete');
    Route::get('category', 'app\controller\admin\Category@index');

    // 模块管理
    Route::get('extension/list', 'app\controller\admin\Extension@listJson');
    Route::get('extension/listJson', 'app\controller\admin\Extension@listJson');
    Route::get('extension/logs', 'app\controller\admin\Extension@logsJson');
    Route::get('extension/permissionMatrix', 'app\controller\admin\Extension@permissionMatrix');
    Route::post('extension/install', 'app\controller\admin\Extension@install');
    Route::post('extension/uninstall', 'app\controller\admin\Extension@uninstall');
    Route::post('extension/toggle', 'app\controller\admin\Extension@toggle');
    Route::post('extension/savePermission', 'app\controller\admin\Extension@savePermission');
    Route::get('extension', 'app\controller\admin\Extension@index');

    // 达人联系话术模板
    Route::get('message_template/list', 'app\controller\admin\MessageTemplate@listJson');
    Route::get('message_template/listJson', 'app\controller\admin\MessageTemplate@listJson');
    Route::post('message_template/save', 'app\controller\admin\MessageTemplate@save');
    Route::post('message_template/delete', 'app\controller\admin\MessageTemplate@delete');
    Route::post('message_template/render', 'app\controller\admin\MessageTemplate@render');
    Route::get('message_template', 'app\controller\admin\MessageTemplate@index');
}

