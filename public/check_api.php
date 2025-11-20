<?php
// 检查API和数据库
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/html; charset=utf-8');

define('__ROOT__', dirname(__DIR__));

require __DIR__ . '/../vendor/autoload.php';

// 初始化ThinkPHP App以加载配置
$app = new \think\App();
$app->initialize();

echo "<h2>API和数据库检查</h2>";

// 1. 检查数据库连接
echo "<h3>1. 数据库连接测试</h3>";
try {
    $db = \think\facade\Db::connect();
    echo "✓ 数据库连接成功<br>";
    
    // 检查表是否存在
    $tables = $db->query("SHOW TABLES");
    echo "✓ 数据库表数量: " . count($tables) . "<br>";
    
    // 检查platforms表
    $platforms = \app\model\Platform::select();
    echo "✓ 平台数量: " . count($platforms) . "<br>";
    
    if (count($platforms) > 0) {
        echo "<pre>";
        foreach ($platforms as $p) {
            echo "ID: {$p->id}, 名称: {$p->name}, 代码: {$p->code}\n";
        }
        echo "</pre>";
    } else {
        echo "⚠ 警告：平台表为空，请导入初始数据<br>";
    }
    
} catch (Exception $e) {
    echo "✗ 数据库连接失败: " . $e->getMessage() . "<br>";
    echo "文件: " . $e->getFile() . "<br>";
    echo "行号: " . $e->getLine() . "<br>";
}

// 2. 测试API（使用简化版）
echo "<h3>2. API测试</h3>";
try {
    $ip = '127.0.0.1';
    $platformCode = 'tiktok';
    
    // 获取平台
    $platform = \app\model\Platform::where('code', $platformCode)->find();
    if ($platform) {
        echo "✓ 找到平台: {$platform->name} (代码: {$platform->code})<br>";
        
        // 获取或创建设备
        $device = \app\model\Device::getOrCreate($ip, $platform->id);
        echo "✓ 设备ID: {$device->id}, IP: {$device->ip_address}<br>";
        
        // 获取未下载的视频
        $video = \app\model\Video::getUndownloaded($device->id);
        if ($video) {
            echo "✓ 找到未下载视频: ID {$video->id}, 标题: " . mb_substr($video->title, 0, 30) . "...<br>";
        } else {
            echo "⚠ 该设备暂无未下载的视频<br>";
        }
    } else {
        echo "⚠ 平台不存在，请先导入数据库<br>";
    }
    
} catch (Exception $e) {
    echo "✗ API测试错误: " . $e->getMessage() . "<br>";
    echo "文件: " . $e->getFile() . "<br>";
    echo "行号: " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// 3. 测试路由
echo "<h3>3. 路由测试</h3>";
try {
    $app = new \think\App();
    echo "✓ App初始化成功<br>";
} catch (Exception $e) {
    echo "✗ App初始化失败: " . $e->getMessage() . "<br>";
}

