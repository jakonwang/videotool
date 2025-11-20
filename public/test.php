<?php
// 测试PHP环境
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "PHP运行正常！<br>";

// 测试autoload
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "vendor/autoload.php 存在<br>";
    require __DIR__ . '/../vendor/autoload.php';
    echo "autoload加载成功<br>";
} else {
    echo "错误：vendor/autoload.php 不存在<br>";
}

// 测试ThinkPHP
try {
    $app = new \think\App();
    echo "ThinkPHP App初始化成功<br>";
} catch (Exception $e) {
    echo "错误：" . $e->getMessage() . "<br>";
    echo "文件：" . $e->getFile() . " 行号：" . $e->getLine() . "<br>";
}

phpinfo();

