<?php
/**
 * 前台测试页面
 * 用于诊断前台访问问题
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>前台测试</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
        .item {
            margin: 15px 0;
            padding: 15px;
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            border-radius: 4px;
        }
        .item.ok {
            border-left-color: #28a745;
            background: #d4edda;
        }
        .item.error {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        .label {
            font-weight: bold;
            color: #555;
        }
        .value {
            color: #333;
            margin-top: 5px;
            word-break: break-all;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 前台访问测试</h1>
        
        <?php
        // 测试信息
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        $currentUrl = $baseUrl . $_SERVER['REQUEST_URI'];
        $platform = $_GET['platform'] ?? 'tiktok';
        
        echo '<div class="item">';
        echo '<div class="label">当前访问URL</div>';
        echo '<div class="value">' . htmlspecialchars($currentUrl) . '</div>';
        echo '</div>';
        
        echo '<div class="item">';
        echo '<div class="label">平台代码</div>';
        echo '<div class="value">' . htmlspecialchars($platform) . '</div>';
        echo '</div>';
        
        // 测试前台页面
        $frontendUrl = $baseUrl . '/?platform=' . $platform;
        echo '<div class="item">';
        echo '<div class="label">前台页面链接</div>';
        echo '<div class="value"><a href="' . htmlspecialchars($frontendUrl) . '" target="_blank">' . htmlspecialchars($frontendUrl) . '</a></div>';
        echo '</div>';
        
        // 测试API
        $apiUrl1 = $baseUrl . '/api_simple.php?platform=' . $platform;
        $apiUrl2 = $baseUrl . '/api/video/getVideo?platform=' . $platform;
        
        echo '<div class="item">';
        echo '<div class="label">API路径1（简化版）</div>';
        echo '<div class="value"><a href="' . htmlspecialchars($apiUrl1) . '" target="_blank">' . htmlspecialchars($apiUrl1) . '</a></div>';
        echo '</div>';
        
        echo '<div class="item">';
        echo '<div class="label">API路径2（路由版）</div>';
        echo '<div class="value"><a href="' . htmlspecialchars($apiUrl2) . '" target="_blank">' . htmlspecialchars($apiUrl2) . '</a></div>';
        echo '</div>';
        
        // 测试API响应
        echo '<div class="item">';
        echo '<div class="label">测试API响应</div>';
        echo '<div class="value">';
        
        // 测试简化版API
        $ch = curl_init($apiUrl1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response1 = curl_exec($ch);
        $httpCode1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode1 === 200) {
            echo '<div style="color: #28a745;">✓ API 1 响应正常 (HTTP ' . $httpCode1 . ')</div>';
            $data1 = json_decode($response1, true);
            if ($data1) {
                echo '<pre style="background: #f4f4f4; padding: 10px; border-radius: 4px; margin-top: 10px; max-height: 200px; overflow-y: auto;">' . htmlspecialchars(json_encode($data1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            } else {
                echo '<div style="color: #dc3545;">✗ API 1 返回的不是有效JSON</div>';
                echo '<pre style="background: #f4f4f4; padding: 10px; border-radius: 4px; margin-top: 10px; max-height: 200px; overflow-y: auto;">' . htmlspecialchars(substr($response1, 0, 500)) . '</pre>';
            }
        } else {
            echo '<div style="color: #dc3545;">✗ API 1 响应失败 (HTTP ' . $httpCode1 . ')</div>';
        }
        
        echo '</div>';
        echo '</div>';
        
        // 检查文件是否存在
        echo '<div class="item">';
        echo '<div class="label">文件检查</div>';
        echo '<div class="value">';
        
        $files = [
            'public/index.php' => '前台入口文件',
            'public/api_simple.php' => '简化版API文件',
            'view/index/index.html' => '前台模板文件',
            'app/controller/index/Index.php' => '前台控制器',
            'app/controller/api/Video.php' => 'API控制器',
        ];
        
        foreach ($files as $file => $desc) {
            $fullPath = __DIR__ . '/../' . $file;
            if (file_exists($fullPath)) {
                echo '<div style="color: #28a745;">✓ ' . $desc . ' 存在</div>';
            } else {
                echo '<div style="color: #dc3545;">✗ ' . $desc . ' 不存在: ' . $fullPath . '</div>';
            }
        }
        
        echo '</div>';
        echo '</div>';
        ?>
        
        <div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border-radius: 4px;">
            <h3 style="margin-top: 0; color: #0056b3;">使用说明</h3>
            <ol>
                <li>点击上面的"前台页面链接"测试前台页面是否能正常访问</li>
                <li>点击"API路径"链接测试API是否能正常返回数据</li>
                <li>如果API返回错误，请检查：
                    <ul>
                        <li>数据库是否已导入</li>
                        <li>平台数据是否存在（平台代码：<?php echo htmlspecialchars($platform); ?>）</li>
                        <li>是否有未下载的视频</li>
                    </ul>
                </li>
                <li>如果前台页面显示后台管理界面，请检查Web服务器配置，确保根目录指向 <code>public/index.php</code></li>
            </ol>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="/admin.php">返回后台管理</a> | 
            <a href="/?platform=<?php echo htmlspecialchars($platform); ?>">访问前台页面</a>
        </div>
    </div>
</body>
</html>

