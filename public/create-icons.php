<?php
/**
 * 自动生成PWA图标文件
 * 访问此文件即可自动生成图标
 */

// 创建图标函数
function createIcon($size, $filename) {
    // 创建画布
    $image = imagecreatetruecolor($size, $size);
    
    // 创建渐变背景（从紫色到深紫色）
    $color1 = imagecolorallocate($image, 102, 126, 234); // #667eea
    $color2 = imagecolorallocate($image, 118, 75, 162);  // #764ba2
    $white = imagecolorallocate($image, 255, 255, 255);
    
    // 填充渐变背景（简化版：使用主色）
    imagefill($image, 0, 0, $color1);
    
    // 绘制圆形背景
    $centerX = $size / 2;
    $centerY = $size / 2;
    $radius = $size / 2.5;
    
    // 绘制外圈
    imagefilledellipse($image, $centerX, $centerY, $radius * 2, $radius * 2, $color2);
    
    // 绘制播放按钮（三角形）
    $triangleSize = $size / 3;
    $points = array(
        $centerX - $triangleSize / 3, $centerY - $triangleSize / 2,  // 左上
        $centerX - $triangleSize / 3, $centerY + $triangleSize / 2,  // 左下
        $centerX + $triangleSize / 2, $centerY                        // 右
    );
    imagefilledpolygon($image, $points, 3, $white);
    
    // 保存为PNG
    imagepng($image, $filename);
    imagedestroy($image);
    
    return file_exists($filename);
}

// 生成图标
$icon192 = __DIR__ . '/icon-192.png';
$icon512 = __DIR__ . '/icon-512.png';

$success = true;
$messages = [];

if (!file_exists($icon192)) {
    if (createIcon(192, $icon192)) {
        $messages[] = "✅ 已生成 icon-192.png";
    } else {
        $messages[] = "❌ 生成 icon-192.png 失败";
        $success = false;
    }
} else {
    $messages[] = "ℹ️ icon-192.png 已存在";
}

if (!file_exists($icon512)) {
    if (createIcon(512, $icon512)) {
        $messages[] = "✅ 已生成 icon-512.png";
    } else {
        $messages[] = "❌ 生成 icon-512.png 失败";
        $success = false;
    }
} else {
    $messages[] = "ℹ️ icon-512.png 已存在";
}

// 输出结果
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>图标生成结果</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 40px;
            background: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            background: #f9f9f9;
        }
        .success {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .error {
            background: #ffebee;
            color: #c62828;
        }
        .info {
            background: #e3f2fd;
            color: #1565c0;
        }
        a {
            color: #667eea;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎨 PWA 图标生成结果</h1>
        
        <?php foreach ($messages as $msg): ?>
            <div class="message <?php echo strpos($msg, '✅') !== false ? 'success' : (strpos($msg, '❌') !== false ? 'error' : 'info'); ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endforeach; ?>
        
        <?php if ($success): ?>
            <div class="message success">
                <h3>✅ 图标生成成功！</h3>
                <p>图标文件已保存到 <code>public</code> 目录：</p>
                <ul>
                    <li><a href="/icon-192.png" target="_blank">icon-192.png</a></li>
                    <li><a href="/icon-512.png" target="_blank">icon-512.png</a></li>
                </ul>
                <p>现在可以使用 PWA Builder 生成 APK 了！</p>
            </div>
        <?php else: ?>
            <div class="message error">
                <h3>❌ 生成失败</h3>
                <p>请确保 PHP 已启用 GD 扩展，或者使用 <a href="/generate-icons.html">HTML 版本</a> 手动生成图标。</p>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 20px;">
            <a href="/platforms.html">← 返回平台选择页面</a>
        </div>
    </div>
</body>
</html>

