<?php
// 简化版API，直接返回JSON
error_reporting(E_ALL);
ini_set('display_errors', '0'); // 不显示错误，只返回JSON

define('__ROOT__', dirname(__DIR__));

require __DIR__ . '/../vendor/autoload.php';

// 初始化ThinkPHP App以加载配置
$app = new \think\App();
$app->initialize();

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $platformCode = $_GET['platform'] ?? 'tiktok';
    
    // 获取平台
    $platform = \app\model\Platform::where('code', $platformCode)->find();
    if (!$platform) {
        echo json_encode([
            'code' => 1,
            'msg' => '平台不存在，请先在后台添加平台'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 获取或创建设备
    $device = \app\model\Device::getOrCreate($ip, $platform->id);
    
    // 获取未下载的视频
    $video = \app\model\Video::getUndownloaded($device->id);
    
    if (!$video) {
        echo json_encode([
            'code' => 1,
            'msg' => '暂无可用视频，请先在后台上传视频'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // 确保URL是绝对路径
    $coverUrl = $video->cover_url;
    $videoUrl = $video->video_url;
    
    // 如果没有封面URL，使用视频URL作为默认封面（视频第一帧）
    if (empty($coverUrl)) {
        $coverUrl = $videoUrl;
    }
    
    // 如果是相对路径，转换为绝对路径
    if ($coverUrl && !preg_match('/^https?:\/\//', $coverUrl)) {
        $coverUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                   '://' . $_SERVER['HTTP_HOST'] . 
                   (strpos($coverUrl, '/') === 0 ? '' : '/') . $coverUrl;
    }
    
    if ($videoUrl && !preg_match('/^https?:\/\//', $videoUrl)) {
        $videoUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                   '://' . $_SERVER['HTTP_HOST'] . 
                   (strpos($videoUrl, '/') === 0 ? '' : '/') . $videoUrl;
    }
    
    echo json_encode([
        'code' => 0,
        'data' => [
            'id' => $video->id,
            'title' => $video->title,
            'cover_url' => $coverUrl,
            'video_url' => $videoUrl,
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'code' => 1,
        'msg' => '服务器错误：' . $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}

