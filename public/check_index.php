<?php
/**
 * 检查默认入口文件配置
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>入口文件检查</title>
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
        <h1>🔍 入口文件检查</h1>
        
        <?php
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        
        // 检查文件
        $indexExists = file_exists(__DIR__ . '/index.php');
        $adminExists = file_exists(__DIR__ . '/admin.php');
        $htaccessExists = file_exists(__DIR__ . '/.htaccess');
        
        echo '<div class="item ' . ($indexExists ? 'ok' : 'error') . '">';
        echo '<div class="label">index.php 文件</div>';
        echo '<div class="value">' . ($indexExists ? '✓ 存在' : '✗ 不存在') . '</div>';
        echo '</div>';
        
        echo '<div class="item ' . ($adminExists ? 'ok' : 'error') . '">';
        echo '<div class="label">admin.php 文件</div>';
        echo '<div class="value">' . ($adminExists ? '✓ 存在' : '✗ 不存在') . '</div>';
        echo '</div>';
        
        echo '<div class="item ' . ($htaccessExists ? 'ok' : 'error') . '">';
        echo '<div class="label">.htaccess 文件</div>';
        echo '<div class="value">' . ($htaccessExists ? '✓ 存在' : '✗ 不存在') . '</div>';
        if ($htaccessExists) {
            $htaccessContent = file_get_contents(__DIR__ . '/.htaccess');
            if (strpos($htaccessContent, 'DirectoryIndex index.php') !== false) {
                echo '<div style="color: #28a745; margin-top: 5px;">✓ 已设置 DirectoryIndex index.php</div>';
            } else {
                echo '<div style="color: #dc3545; margin-top: 5px;">✗ 未设置 DirectoryIndex index.php</div>';
            }
        }
        echo '</div>';
        
        // 检测 Web 服务器
        $webServer = 'Unknown';
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $serverSoftware = $_SERVER['SERVER_SOFTWARE'];
            if (stripos($serverSoftware, 'nginx') !== false) {
                $webServer = 'Nginx';
            } elseif (stripos($serverSoftware, 'apache') !== false) {
                $webServer = 'Apache';
            } else {
                $webServer = $serverSoftware;
            }
        }
        
        echo '<div class="item">';
        echo '<div class="label">Web 服务器</div>';
        echo '<div class="value">' . htmlspecialchars($webServer) . '</div>';
        echo '</div>';
        
        // 测试链接
        echo '<div class="item">';
        echo '<div class="label">测试链接</div>';
        echo '<div class="value">';
        echo '<div style="margin: 5px 0;"><a href="' . $baseUrl . '/" target="_blank">访问根目录: ' . $baseUrl . '/</a></div>';
        echo '<div style="margin: 5px 0;"><a href="' . $baseUrl . '/index.php" target="_blank">直接访问 index.php: ' . $baseUrl . '/index.php</a></div>';
        echo '<div style="margin: 5px 0;"><a href="' . $baseUrl . '/index.php?platform=tiktok" target="_blank">前台页面: ' . $baseUrl . '/index.php?platform=tiktok</a></div>';
        echo '<div style="margin: 5px 0;"><a href="' . $baseUrl . '/admin.php" target="_blank">后台管理: ' . $baseUrl . '/admin.php</a></div>';
        echo '</div>';
        echo '</div>';
        ?>
        
        <div style="margin-top: 30px; padding: 15px; background: #e7f3ff; border-radius: 4px;">
            <h3 style="margin-top: 0; color: #0056b3;">修复方法</h3>
            
            <?php if ($webServer === 'Nginx'): ?>
            <h4>Nginx 配置（phpstudy 用户）</h4>
            <ol>
                <li>打开 phpstudy 控制面板</li>
                <li>点击"网站" → 找到你的站点 → "管理" → "配置文件"</li>
                <li>在配置文件中找到 <code>index</code> 指令，确保是：
                    <pre style="background: #f4f4f4; padding: 10px; border-radius: 4px; margin-top: 10px;">index index.php index.html;</pre>
                </li>
                <li>保存并重启 Nginx</li>
            </ol>
            <?php elseif ($webServer === 'Apache'): ?>
            <h4>Apache 配置</h4>
            <p>如果使用 Apache，<code>public/.htaccess</code> 文件已经设置了 <code>DirectoryIndex index.php</code>。</p>
            <p>如果仍然有问题，请检查 Apache 是否启用了 <code>mod_rewrite</code> 模块。</p>
            <?php else: ?>
            <h4>通用方法</h4>
            <p>如果无法修改 Web 服务器配置，可以直接访问：</p>
            <ul>
                <li>前台页面：<code><?php echo $baseUrl; ?>/index.php?platform=tiktok</code></li>
                <li>后台管理：<code><?php echo $baseUrl; ?>/admin.php</code></li>
            </ul>
            <?php endif; ?>
        </div>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="/admin.php">返回后台管理</a>
        </div>
    </div>
</body>
</html>

