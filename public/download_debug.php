<?php
/**
 * 下载调试工具
 * 用于诊断七牛云下载问题
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('__ROOT__', dirname(__DIR__));

require __DIR__ . '/../vendor/autoload.php';

// 初始化ThinkPHP
$app = new \think\App();
$app->initialize();

header('Content-Type: text/html; charset=utf-8');

$videoId = $_GET['video_id'] ?? null;
$type = $_GET['type'] ?? 'video';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>下载调试工具</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .section { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f0f0f0; padding: 10px; border-radius: 3px; overflow-x: auto; }
        h2 { margin-top: 0; }
        form { margin: 20px 0; }
        input[type='text'] { padding: 5px; width: 200px; }
        button { padding: 5px 15px; }
    </style>
</head>
<body>
    <h1>下载调试工具</h1>
    
    <form method='get'>
        <label>视频ID: <input type='text' name='video_id' value='" . htmlspecialchars($videoId ?? '') . "'></label>
        <label>类型: 
            <select name='type'>
                <option value='video'" . ($type === 'video' ? ' selected' : '') . ">视频</option>
                <option value='cover'" . ($type === 'cover' ? ' selected' : '') . ">封面</option>
            </select>
        </label>
        <button type='submit'>调试</button>
    </form>";

if ($videoId) {
    echo "<div class='section'>";
    echo "<h2>1. 视频信息</h2>";
    
    try {
        $video = \app\model\Video::find($videoId);
        if (!$video) {
            echo "<p class='error'>视频不存在 (ID: {$videoId})</p>";
            exit;
        }
        
        $fileUrl = $type === 'cover' ? $video->cover_url : $video->video_url;
        echo "<p><strong>视频ID:</strong> {$video->id}</p>";
        echo "<p><strong>标题:</strong> " . htmlspecialchars($video->title) . "</p>";
        echo "<p><strong>文件URL:</strong> " . htmlspecialchars($fileUrl) . "</p>";
        echo "<p><strong>平台:</strong> " . ($video->platform_id ?? '无') . "</p>";
        
        if (empty($fileUrl)) {
            echo "<p class='error'>文件URL为空</p>";
            exit;
        }
        
        // 确保URL是绝对路径
        if (!preg_match('/^https?:\/\//', $fileUrl)) {
            $fileUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                       '://' . $_SERVER['HTTP_HOST'] . 
                       (strpos($fileUrl, '/') === 0 ? '' : '/') . $fileUrl;
        }
        
        echo "<p><strong>完整URL:</strong> " . htmlspecialchars($fileUrl) . "</p>";
        
    } catch (\Exception $e) {
        echo "<p class='error'>获取视频信息失败: " . htmlspecialchars($e->getMessage()) . "</p>";
        exit;
    }
    
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>2. 七牛云配置检查</h2>";
    
    $qiniuConfig = \think\facade\Config::get('qiniu');
    echo "<p><strong>七牛云启用:</strong> " . ($qiniuConfig['enabled'] ? '是' : '否') . "</p>";
    echo "<p><strong>七牛云域名:</strong> " . htmlspecialchars($qiniuConfig['domain'] ?? '未配置') . "</p>";
    
    // 检查是否为CDN URL
    $host = parse_url($fileUrl, PHP_URL_HOST);
    $isCdn = false;
    if ($host) {
        $cdnDomains = [];
        if (!empty($qiniuConfig['domain'])) {
            $cdnDomains[] = parse_url($qiniuConfig['domain'], PHP_URL_HOST) ?: $qiniuConfig['domain'];
        }
        foreach ($cdnDomains as $cdnHost) {
            if ($cdnHost && stripos($host, $cdnHost) !== false) {
                $isCdn = true;
                break;
            }
        }
    }
    
    echo "<p><strong>是否为CDN资源:</strong> " . ($isCdn ? '是' : '否') . "</p>";
    echo "<p><strong>URL主机:</strong> " . htmlspecialchars($host) . "</p>";
    
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>3. 缓存状态检查</h2>";
    
    try {
        $cacheConfig = \think\facade\Config::get('download_cache');
        $cacheRoot = rtrim($cacheConfig['root'] ?? (runtime_path() . 'download_cache'), DIRECTORY_SEPARATOR);
        
        echo "<p><strong>缓存启用:</strong> " . ($cacheConfig['enabled'] ? '是' : '否') . "</p>";
        echo "<p><strong>缓存目录:</strong> " . htmlspecialchars($cacheRoot) . "</p>";
        echo "<p><strong>缓存目录存在:</strong> " . (is_dir($cacheRoot) ? '是' : '否') . "</p>";
        echo "<p><strong>缓存目录可写:</strong> " . (is_writable($cacheRoot) ? '是' : '否') . "</p>";
        
        if ($isCdn) {
            $hash = sha1($fileUrl);
            $subDir = substr($hash, 0, 2) . DIRECTORY_SEPARATOR . substr($hash, 2, 2);
            $cacheDir = $cacheRoot . DIRECTORY_SEPARATOR . $subDir;
            $extension = pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: ($type === 'cover' ? 'jpg' : 'mp4');
            $cachePath = $cacheDir . DIRECTORY_SEPARATOR . $hash . '.' . $extension;
            $tempPath = $cachePath . '.part';
            $lockPath = $cacheDir . DIRECTORY_SEPARATOR . $hash . '.lock';
            $metaPath = $cacheDir . DIRECTORY_SEPARATOR . $hash . '.json';
            
            echo "<p><strong>缓存哈希:</strong> {$hash}</p>";
            echo "<p><strong>缓存路径:</strong> " . htmlspecialchars($cachePath) . "</p>";
            echo "<p><strong>缓存文件存在:</strong> " . (file_exists($cachePath) ? '是' : '否') . "</p>";
            
            if (file_exists($cachePath)) {
                $fileSize = filesize($cachePath);
                $fileTime = filemtime($cachePath);
                echo "<p><strong>缓存文件大小:</strong> " . number_format($fileSize / 1024 / 1024, 2) . " MB</p>";
                echo "<p><strong>缓存时间:</strong> " . date('Y-m-d H:i:s', $fileTime) . "</p>";
                
                // 检查是否过期
                $expire = $cacheConfig['expire_seconds'] ?? 0;
                if ($expire > 0) {
                    $age = time() - $fileTime;
                    $isExpired = $age > $expire;
                    echo "<p><strong>缓存年龄:</strong> " . round($age / 3600, 2) . " 小时</p>";
                    echo "<p><strong>缓存是否过期:</strong> " . ($isExpired ? '是' : '否') . "</p>";
                }
            }
            
            echo "<p><strong>临时文件存在:</strong> " . (file_exists($tempPath) ? '是（可能有未完成的下载）' : '否') . "</p>";
            if (file_exists($tempPath)) {
                echo "<p><strong>临时文件大小:</strong> " . number_format(filesize($tempPath) / 1024, 2) . " KB</p>";
            }
            
            echo "<p><strong>锁文件存在:</strong> " . (file_exists($lockPath) ? '是（可能有正在进行的下载）' : '否') . "</p>";
            if (file_exists($lockPath)) {
                $lockAge = time() - filemtime($lockPath);
                echo "<p><strong>锁文件年龄:</strong> " . round($lockAge / 60, 2) . " 分钟</p>";
                if ($lockAge > 300) {
                    echo "<p class='warning'>警告：锁文件存在超过5分钟，可能是异常遗留</p>";
                }
            }
            
            echo "<p><strong>元数据文件存在:</strong> " . (file_exists($metaPath) ? '是' : '否') . "</p>";
            if (file_exists($metaPath)) {
                $meta = @json_decode(file_get_contents($metaPath), true);
                if ($meta) {
                    echo "<pre>" . htmlspecialchars(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
                }
            }
        }
        
    } catch (\Exception $e) {
        echo "<p class='error'>检查缓存状态失败: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>4. URL可访问性测试</h2>";
    
    try {
        $ch = curl_init($fileUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_REFERER => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/',
            CURLOPT_USERAGENT => 'VideoTool-Debug-Tool/1.0',
            CURLOPT_NOBODY => true, // 只获取头部
        ]);
        
        $startTime = microtime(true);
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = microtime(true) - $startTime;
        $error = curl_error($ch);
        $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        curl_close($ch);
        
        if ($error) {
            echo "<p class='error'><strong>cURL错误:</strong> " . htmlspecialchars($error) . "</p>";
        } else {
            echo "<p><strong>HTTP状态码:</strong> " . $httpCode;
            if ($httpCode >= 400) {
                echo " <span class='error'>(错误)</span>";
            } elseif ($httpCode >= 300) {
                echo " <span class='warning'>(重定向)</span>";
            } else {
                echo " <span class='success'>(正常)</span>";
            }
            echo "</p>";
            echo "<p><strong>响应时间:</strong> " . round($totalTime * 1000, 2) . " ms</p>";
            echo "<p><strong>文件大小:</strong> " . ($contentLength > 0 ? number_format($contentLength / 1024 / 1024, 2) . " MB" : '未知') . "</p>";
        }
        
    } catch (\Exception $e) {
        echo "<p class='error'>测试URL可访问性失败: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
    
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>5. 代理下载URL测试</h2>";
    
    $proxyUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
               '://' . $_SERVER['HTTP_HOST'] . 
               '/api/video/download?video_id=' . $videoId . '&type=' . $type;
    
    echo "<p><strong>代理URL:</strong> <a href='" . htmlspecialchars($proxyUrl) . "' target='_blank'>" . htmlspecialchars($proxyUrl) . "</a></p>";
    echo "<p><a href='" . htmlspecialchars($proxyUrl) . "' target='_blank'><button>测试下载</button></a></p>";
    
    echo "</div>";
    
    echo "<div class='section'>";
    echo "<h2>6. 最近日志（最后50行）</h2>";
    
    $logFile = runtime_path() . 'log' . DIRECTORY_SEPARATOR . date('Ym') . DIRECTORY_SEPARATOR . date('d') . '.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $recentLines = array_slice($lines, -50);
        echo "<pre>" . htmlspecialchars(implode('', $recentLines)) . "</pre>";
    } else {
        echo "<p class='warning'>日志文件不存在: " . htmlspecialchars($logFile) . "</p>";
    }
    
    echo "</div>";
}

echo "</body></html>";

