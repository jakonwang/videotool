<?php
/**
 * 检查上传配置
 * 访问: http://your-domain/admin.php/../check_upload.php
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>上传配置检查</title>
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
        .item.warning {
            border-left-color: #ffc107;
            background: #fff3cd;
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
            font-size: 18px;
            margin-top: 5px;
        }
        .help {
            margin-top: 20px;
            padding: 15px;
            background: #e7f3ff;
            border-radius: 4px;
        }
        .help h3 {
            margin-top: 0;
            color: #0056b3;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📋 上传配置检查</h1>
        
        <?php
        // 显示 Web 服务器信息
        echo '<div class="item">';
        echo '<div class="label">Web 服务器</div>';
        echo '<div class="value">' . htmlspecialchars($web_server) . '</div>';
        if ($web_server === 'Nginx') {
            echo '<div style="color: #856404; margin-top: 5px;">⚠️ 如果出现 413 错误，请检查 Nginx 的 <code>client_max_body_size</code> 配置</div>';
        } elseif ($web_server === 'Apache') {
            echo '<div style="color: #856404; margin-top: 5px;">⚠️ 如果出现 413 错误，请检查 Apache 的 <code>LimitRequestBody</code> 配置</div>';
        }
        echo '</div>';
        
        if ($content_length > 0) {
            echo '<div class="item warning">';
            echo '<div class="label">当前请求大小</div>';
            echo '<div class="value">' . number_format($content_length / 1024 / 1024, 2) . ' MB</div>';
            echo '</div>';
        }
        
        echo '<hr style="margin: 30px 0;">';
        // 检测 Web 服务器类型
        $web_server = 'Unknown';
        if (isset($_SERVER['SERVER_SOFTWARE'])) {
            $server_software = $_SERVER['SERVER_SOFTWARE'];
            if (stripos($server_software, 'nginx') !== false) {
                $web_server = 'Nginx';
            } elseif (stripos($server_software, 'apache') !== false) {
                $web_server = 'Apache';
            } else {
                $web_server = $server_software;
            }
        }
        
        // 获取配置值
        $upload_max_filesize = ini_get('upload_max_filesize');
        $post_max_size = ini_get('post_max_size');
        $max_execution_time = ini_get('max_execution_time');
        $memory_limit = ini_get('memory_limit');
        
        // 检查 HTTP 响应头
        $content_length = isset($_SERVER['CONTENT_LENGTH']) ? $_SERVER['CONTENT_LENGTH'] : 0;
        
        // 转换为字节
        function convertToBytes($val) {
            $val = trim($val);
            $last = strtolower($val[strlen($val)-1]);
            $val = (int)$val;
            switch($last) {
                case 'g': $val *= 1024;
                case 'm': $val *= 1024;
                case 'k': $val *= 1024;
            }
            return $val;
        }
        
        $upload_max_bytes = convertToBytes($upload_max_filesize);
        $post_max_bytes = convertToBytes($post_max_size);
        
        // 检查配置
        $upload_ok = $upload_max_bytes >= 100 * 1024 * 1024; // 100MB
        $post_ok = $post_max_bytes >= 100 * 1024 * 1024; // 100MB
        $post_larger = $post_max_bytes >= $upload_max_bytes;
        
        // 显示配置
        echo '<div class="item ' . ($upload_ok ? 'ok' : 'warning') . '">';
        echo '<div class="label">upload_max_filesize (单个文件最大上传大小)</div>';
        echo '<div class="value">' . $upload_max_filesize . ' (' . number_format($upload_max_bytes / 1024 / 1024, 2) . ' MB)</div>';
        if (!$upload_ok) {
            echo '<div style="color: #856404; margin-top: 5px;">⚠️ 建议设置为 100M 或更大</div>';
        }
        echo '</div>';
        
        echo '<div class="item ' . ($post_ok ? 'ok' : 'warning') . '">';
        echo '<div class="label">post_max_size (POST请求最大大小)</div>';
        echo '<div class="value">' . $post_max_size . ' (' . number_format($post_max_bytes / 1024 / 1024, 2) . ' MB)</div>';
        if (!$post_ok) {
            echo '<div style="color: #856404; margin-top: 5px;">⚠️ 建议设置为 100M 或更大</div>';
        }
        echo '</div>';
        
        echo '<div class="item ' . ($post_larger ? 'ok' : 'error') . '">';
        echo '<div class="label">配置关系检查</div>';
        if ($post_larger) {
            echo '<div class="value">✓ post_max_size >= upload_max_filesize (正确)</div>';
        } else {
            echo '<div class="value">✗ post_max_size < upload_max_filesize (错误)</div>';
            echo '<div style="color: #721c24; margin-top: 5px;">⚠️ post_max_size 必须大于或等于 upload_max_filesize</div>';
        }
        echo '</div>';
        
        echo '<div class="item">';
        echo '<div class="label">max_execution_time (最大执行时间)</div>';
        echo '<div class="value">' . $max_execution_time . ' 秒</div>';
        echo '</div>';
        
        echo '<div class="item">';
        echo '<div class="label">memory_limit (内存限制)</div>';
        echo '<div class="value">' . $memory_limit . '</div>';
        echo '</div>';
        ?>
        
        <div class="help">
            <h3>🔧 如何修复上传大小限制</h3>
            
            <h4>方法一：修改 php.ini 文件（推荐）</h4>
            <p>找到 PHP 的配置文件 <code>php.ini</code>，修改以下配置：</p>
            <pre style="background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto;">
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
memory_limit = 256M</pre>
            <p>修改后需要重启 Web 服务器（Apache/Nginx）和 PHP-FPM。</p>
            
            <h4>方法二：在 .htaccess 中设置（Apache）</h4>
            <p>在 <code>public/.htaccess</code> 文件中添加：</p>
            <pre style="background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto;">
php_value upload_max_filesize 100M
php_value post_max_size 100M
php_value max_execution_time 300
php_value memory_limit 256M</pre>
            <p><strong>注意：</strong> 如果服务器禁用了 .htaccess 中的 PHP 配置，此方法可能无效。</p>
            
            <h4>方法三：在 Nginx 配置中设置（重要！）</h4>
            <p><strong style="color: #dc3545;">如果使用 Nginx，这是最可能的原因！</strong></p>
            <p>在 Nginx 配置文件中（通常在 <code>/etc/nginx/nginx.conf</code> 或站点配置文件中）添加：</p>
            <pre style="background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto;">
# 在 http 块或 server 块中添加
client_max_body_size 100M;

# 如果使用 phpstudy，配置文件通常在：
# phpstudy安装目录/Extensions/Nginx/conf/nginx.conf
# 或者在站点配置文件中</pre>
            <p><strong>phpstudy 用户：</strong></p>
            <ol>
                <li>打开 phpstudy 控制面板</li>
                <li>点击"网站" → 找到你的站点 → "管理" → "配置文件"</li>
                <li>在 <code>server {}</code> 块中添加 <code>client_max_body_size 100M;</code></li>
                <li>保存并重启 Nginx</li>
            </ol>
            <p>同时确保 PHP-FPM 的配置也正确。</p>
            
            <h4>方法四：在代码中设置（临时方案）</h4>
            <p>在 <code>public/admin.php</code> 文件开头添加：</p>
            <pre style="background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto;">
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('max_execution_time', '300');
ini_set('memory_limit', '256M');</pre>
            <p><strong>注意：</strong> 此方法可能无效，因为某些配置项只能在 php.ini 中设置。</p>
            
            <h4>📝 重要提示</h4>
            <ul>
                <li><code>post_max_size</code> 必须大于或等于 <code>upload_max_filesize</code></li>
                <li><strong style="color: #dc3545;">如果使用 Nginx，必须设置 <code>client_max_body_size</code>，这是 413 错误最常见的原因！</strong></li>
                <li>如果使用 Apache，可能需要设置 <code>LimitRequestBody</code></li>
                <li>修改配置后必须重启 Web 服务器才能生效</li>
                <li>如果上传多个文件，总大小不能超过 <code>post_max_size</code> 和 <code>client_max_body_size</code></li>
                <li>如果修改后仍然出现 413 错误，请检查是否有反向代理（CDN、负载均衡）也限制了大小</li>
            </ul>
            
            <h4>🔍 如何找到 Nginx 配置文件（phpstudy）</h4>
            <ol>
                <li>打开 phpstudy 控制面板</li>
                <li>点击"网站"标签</li>
                <li>找到你的站点，点击"管理"按钮</li>
                <li>选择"配置文件"或"修改配置"</li>
                <li>在打开的配置文件中，找到 <code>server {}</code> 块</li>
                <li>在 <code>server {}</code> 块内添加：<code>client_max_body_size 100M;</code></li>
                <li>保存文件</li>
                <li>在 phpstudy 控制面板中重启 Nginx</li>
            </ol>
            
            <h4>📋 完整的 Nginx 配置示例</h4>
            <pre style="background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto;">
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/videotool/public;
    index index.php;
    
    # 设置上传大小限制（重要！）
    client_max_body_size 100M;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}</pre>
        </div>
        
        <div style="margin-top: 30px; text-align: center; color: #666;">
            <p>检查时间: <?php echo date('Y-m-d H:i:s'); ?></p>
            <p><a href="/admin.php">返回后台管理</a></p>
        </div>
    </div>
</body>
</html>

