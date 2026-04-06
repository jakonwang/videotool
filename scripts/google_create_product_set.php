<?php
/**
 * 创建 Google Cloud Vision Product Search 用的 ProductSet（一次性运维脚本）。
 *
 * 用法（Windows / Linux 通用）：
 *   php scripts/google_create_product_set.php --project=YOUR_GCP_PROJECT --location=us-east1 --set-id=earrings_main --display-name=耳环主库
 *
 * 鉴权：设置环境变量 GOOGLE_APPLICATION_CREDENTIALS 指向 JSON 密钥绝对路径，
 * 或增加参数 --key-file=D:\secrets\xxx.json（须不在站点 public 目录下）。
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Google\Cloud\Vision\V1\ProductSearchClient;
use Google\Cloud\Vision\V1\ProductSet;

$opts = getopt('', ['project:', 'location:', 'set-id:', 'display-name:', 'key-file::']);
$project = trim((string) ($opts['project'] ?? ''));
$location = trim((string) ($opts['location'] ?? ''));
$setId = trim((string) ($opts['set-id'] ?? ''));
$display = trim((string) ($opts['display-name'] ?? ''));
$keyFile = trim((string) ($opts['key-file'] ?? getenv('GOOGLE_APPLICATION_CREDENTIALS') ?: ''));

if ($project === '' || $location === '' || $setId === '') {
    fwrite(STDERR, "缺少参数。必填：--project --location --set-id；可选：--display-name --key-file\n");
    exit(1);
}
if ($display === '') {
    $display = $setId;
}
if ($keyFile === '' || !is_file($keyFile)) {
    fwrite(STDERR, "请通过 --key-file 或环境变量 GOOGLE_APPLICATION_CREDENTIALS 指定有效的服务账号 JSON 路径。\n");
    exit(1);
}

$public = realpath($root . '/public');
$keyReal = realpath($keyFile);
if ($public && $keyReal && str_starts_with(str_replace('\\', '/', $keyReal), rtrim(str_replace('\\', '/', $public), '/') . '/')) {
    fwrite(STDERR, "拒绝使用位于 public 目录下的密钥文件，请将 JSON 移到 Web 根目录之外。\n");
    exit(1);
}

$client = new ProductSearchClient(['credentials' => $keyFile]);
try {
    $parent = ProductSearchClient::locationName($project, $location);
    $set = (new ProductSet())->setDisplayName($display);
    $created = $client->createProductSet($parent, $set, ['productSetId' => $setId]);
    echo "OK: 已创建 ProductSet\n";
    echo $created->getName() . "\n";
} catch (\Throwable $e) {
    fwrite(STDERR, '失败: ' . $e->getMessage() . "\n");
    exit(1);
} finally {
    $client->close();
}
