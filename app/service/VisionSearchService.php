<?php
declare(strict_types=1);

namespace app\service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Log;

/**
 * OpenAI gpt-4o-mini Vision：库内耳环特征描述生成 + 实拍图语义匹配
 */
class VisionSearchService
{
    /**
     * 为参考图生成一句中文视觉特征（导入时调用）
     */
    public static function describeEarringImage(string $absolutePath): ?string
    {
        $cfg = VisionOpenAIConfig::get();
        if (!$cfg['enabled'] || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        $dataUrl = self::fileToDataUrl($absolutePath);
        if ($dataUrl === null) {
            return null;
        }
        $body = [
            'model' => $cfg['model'],
            'max_tokens' => $cfg['describe_max_tokens'],
            'temperature' => 0.3,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是珠宝电商视觉标注员。只输出一行中文，50～120 字：耳环的颜色、金属色、造型轮廓（如圆形/水滴/几何）、镶嵌（碎钻/珍珠/无）、风格（极简/复古/华丽等）。不要编号、不要价格、不要营销语。',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => '请根据这张耳环商品参考图，输出上述格式的一行特征描述。'],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ],
                ],
            ],
        ];

        $text = self::chatCompletion($cfg, $body);
        if ($text === null) {
            return null;
        }
        $text = preg_replace("/\s+/u", ' ', trim($text));

        return $text !== '' ? mb_substr($text, 0, 500) : null;
    }

    /**
     * 实拍图 + 编号|特征 列表，返回最多 5 条匹配
     *
     * @param list<array{code:string,desc:string,hot:string}> $catalog
     * @return array{ok:bool, matches?: list<array{product_code:string, score:float, reason:string}>, error?:string, raw?:string}
     */
    public static function matchPhotoToCatalog(string $absolutePath, array $catalog): array
    {
        $cfg = VisionOpenAIConfig::get();
        if (!$cfg['enabled']) {
            return ['ok' => false, 'error' => '未配置 OpenAI API Key'];
        }
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return ['ok' => false, 'error' => '无法读取查询图片'];
        }
        if ($catalog === []) {
            return ['ok' => false, 'error' => '库内无可用索引'];
        }
        $dataUrl = self::fileToDataUrl($absolutePath);
        if ($dataUrl === null) {
            return ['ok' => false, 'error' => '图片编码失败'];
        }

        $lines = [];
        foreach ($catalog as $row) {
            $code = $row['code'];
            $desc = str_replace(["\r", "\n", '|'], ['', '', '／'], $row['desc']);
            $hot = str_replace(["\r", "\n", '|'], ['', '', '／'], $row['hot']);
            $desc = mb_substr(trim($desc), 0, 200);
            $lines[] = $code . '|' . $desc . ($hot !== '' ? '|爆款:' . mb_substr($hot, 0, 80) : '');
        }
        $block = implode("\n", $lines);

        $instruction = <<<TXT
下面是耳环款式库的「产品编号|视觉特征描述|可选爆款备注」列表（每行一条）。另附一张用户**实拍**照片。

任务：
1. 判断实拍主体是否为耳环或耳饰；若完全无法辨认耳饰，matches 为空数组。
2. 根据颜色、造型、材质感、风格，从列表中选出**最多 5 个**最可能对应的**产品编号**（只能从列表已出现的编号中选，禁止编造编号）。
3. 为每项给出 0～1 的置信度 score（浮点数），并简短说明 reason（中文，20 字内）。

**只输出一个 JSON 对象**，不要 markdown，不要其它文字。格式严格如下：
{"matches":[{"product_code":"编号","score":0.95,"reason":"..."}],"note":"可选，一句话"}

列表：
{$block}
TXT;

        $body = [
            'model' => $cfg['model'],
            'max_tokens' => $cfg['match_max_tokens'],
            'temperature' => 0.15,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是耳环识款助手。只输出合法 JSON，键名使用英文 product_code、score、reason、matches、note。',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $instruction],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ],
                ],
            ],
        ];

        $raw = self::chatCompletionRaw($cfg, $body);
        if ($raw === null) {
            return ['ok' => false, 'error' => 'OpenAI 请求失败'];
        }
        $json = self::extractJsonObject($raw);
        if ($json === null) {
            Log::warning('vision_match parse fail: ' . substr($raw, 0, 800));

            return ['ok' => false, 'error' => '模型返回无法解析，请重试', 'raw' => substr($raw, 0, 200)];
        }
        $matches = $json['matches'] ?? [];
        if (!is_array($matches)) {
            return ['ok' => false, 'error' => '返回格式异常'];
        }
        $out = [];
        foreach ($matches as $m) {
            if (!is_array($m)) {
                continue;
            }
            $code = trim((string) ($m['product_code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $score = (float) ($m['score'] ?? 0);
            if ($score > 1) {
                $score = 1.0;
            }
            if ($score < 0) {
                $score = 0.0;
            }
            $out[] = [
                'product_code' => $code,
                'score' => $score,
                'reason' => mb_substr(trim((string) ($m['reason'] ?? '')), 0, 120),
            ];
            if (count($out) >= 5) {
                break;
            }
        }
        usort($out, static fn ($a, $b) => $b['score'] <=> $a['score']);

        return ['ok' => true, 'matches' => $out];
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $body
     */
    private static function chatCompletion(array $cfg, array $body): ?string
    {
        $raw = self::chatCompletionRaw($cfg, $body);
        if ($raw === null) {
            return null;
        }
        $dec = json_decode($raw, true);
        if (!is_array($dec)) {
            return null;
        }
        $choices = $dec['choices'] ?? null;
        if (!is_array($choices) || !isset($choices[0])) {
            return null;
        }
        $msg = $choices[0]['message'] ?? null;
        if (!is_array($msg)) {
            return null;
        }
        $content = $msg['content'] ?? '';
        if (is_string($content)) {
            return trim($content);
        }
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $p) {
                if (is_array($p) && isset($p['text'])) {
                    $parts[] = (string) $p['text'];
                }
            }

            return trim(implode('', $parts));
        }

        return null;
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $body
     */
    private static function chatCompletionRaw(array $cfg, array $body): ?string
    {
        $url = $cfg['base_url'] . '/chat/completions';
        try {
            $client = new Client([
                'timeout' => $cfg['timeout_seconds'],
                'connect_timeout' => 15,
                'http_errors' => false,
            ]);
            $res = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $cfg['api_key'],
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);
            $code = $res->getStatusCode();
            $raw = (string) $res->getBody();
            if ($code < 200 || $code >= 300) {
                Log::warning('OpenAI HTTP ' . $code . ' ' . substr($raw, 0, 500));

                return null;
            }

            return $raw;
        } catch (GuzzleException $e) {
            Log::warning('OpenAI guzzle: ' . $e->getMessage());

            return null;
        }
    }

    private static function fileToDataUrl(string $path): ?string
    {
        $bin = @file_get_contents($path);
        if ($bin === false || $bin === '') {
            return null;
        }
        if (strlen($bin) > 8 * 1024 * 1024) {
            return null;
        }
        $mime = 'image/jpeg';
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi) {
                $m = finfo_file($fi, $path);
                finfo_close($fi);
                if (is_string($m) && str_starts_with($m, 'image/')) {
                    $mime = $m;
                }
            }
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bin);
    }

    private static function extractJsonObject(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $dec = json_decode($m[0], true);
            if (is_array($dec)) {
                return $dec;
            }
        }

        return null;
    }
}
