<?php
use think\facade\Route;

// API路由 - 使用完整命名空间避免大小写问题
Route::group('api', function() {
    Route::group('video', function() {
        Route::get('getVideo', 'app\controller\api\Video@getVideo');
        Route::post('markDownloaded', 'app\controller\api\Video@markDownloaded');
    });
    Route::get('platforms', 'app\controller\api\Video@getPlatforms');
});

