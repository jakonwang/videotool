<?php
use think\facade\Route;

// API路由 - 使用完整命名空间避免大小写问题
Route::group('api', function() {
    Route::get('platforms', 'app\controller\api\Video@getPlatforms');
    Route::get('qiniu/uploadToken', 'app\controller\api\Video@getQiniuUploadToken'); // 获取七牛云上传Token
    Route::post('video/saveUploaded', 'app\controller\api\Video@saveUploadedFile'); // 保存上传成功的文件信息
    Route::group('video', function() {
        Route::get('getVideo', 'app\controller\api\Video@getVideo');
        Route::post('markDownloaded', 'app\controller\api\Video@markDownloaded');
        Route::get('download', 'app\controller\api\Video@downloadProxy'); // 代理下载接口
    });
});

