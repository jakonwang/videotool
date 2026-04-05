<?php
/**
 * 火山方舟豆包连通性探测：与后台「豆包视觉」相同配置，发一条极简文本 chat/completions。
 * 用于区分「Key/Endpoint/base_url 错误」与「仅识图/大图失败」。
 *
 * Windows: php scripts\volc_ark_ping.php
 * Linux:   php scripts/volc_ark_ping.php
 */
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "请使用命令行执行。\n");
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$app = new \think\App();
$app->initialize();

fwrite(STDOUT, "=== 豆包方舟 ping（文本对话，不读图）===\n");
$cfg = \app\service\VolcArkVisionConfig::get();
fwrite(STDOUT, '后台「启用豆包」且配置完整: ' . ($cfg['enabled'] ? '是' : '否') . "\n");
fwrite(STDOUT, "base_url: " . ($cfg['base_url'] ?? '') . "\n");
fwrite(STDOUT, 'model（请求体）: ' . ($cfg['model'] ?? '') . "\n");
fwrite(STDOUT, 'endpoint_id（库内原始字段，可为空）: ' . ($cfg['endpoint_id'] ?? '') . "\n");
fwrite(STDOUT, 'verify_ssl: ' . (($cfg['verify_ssl'] ?? true) ? 'true' : 'false') . "\n\n");

$result = \app\service\VolcArkVisionService::pingChatCompletion();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";

if (!($result['ok'] ?? false)) {
    fwrite(STDERR, "\n失败。请根据 hint、http、ark_error、body_snip 排查；导入仍报「未生成 AI 描述」时对照 runtime/log 中 [volc_ark] 豆包 HTTP 失败。\n");
    exit(2);
}

fwrite(STDOUT, "\n成功：方舟 chat/completions 可用，导入若仍无描述请检查图片大小或查看 describe 相关日志。\n");
exit(0);
