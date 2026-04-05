<?php
declare(strict_types=1);

namespace app\service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\facade\Log;

/**
 * 火山方舟多模态：豆包 vision（接入点 ID 作为 model）实拍图 + 产品清单 JSON 匹配。
 * API 兼容 OpenAI Chat Completions（/chat/completions）。
 */
class VolcArkVisionService
{
    /**
     * 为参考图生成一句中文视觉特征（导入 / 编辑换图时调用，与 OpenAI 描述格式一致）。
     */
    public static function describeEarringImage(string $absolutePath): ?string
    {
        $cfg = VolcArkVisionConfig::get();
        if (!$cfg['enabled'] || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        $dataUrl = self::fileToDataUrl($absolutePath);
        if ($dataUrl === null) {
            return null;
        }
        $body = [
            'model' => $cfg['endpoint_id'],
            'max_tokens' => $cfg['describe_max_tokens'],
            'temperature' => 0.3,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是珠宝电商视觉标注员。只输出一行中文，50～140 字。请先判断品类（耳环/耳钉/耳坠、手链/手镯、项链/吊坠、戒指等饰品），再写清：金属色与光泽、主石/镶嵌（锆石/珍珠/贝母/无等）、造型结构（耳针/耳圈、链节与扣头、吊坠轮廓等）、风格（极简/复古/轻奢等）。不要编号、不要价格、不要营销语。',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => '请根据这张饰品商品参考图，输出上述格式的一行特征描述。'],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ],
                ],
            ],
        ];
        $raw = self::chatCompletionRaw($cfg, $body, 'describe_earring');
        $text = self::parseChatCompletionText($raw);
        if ($text === null) {
            return null;
        }
        $text = preg_replace("/\s+/u", ' ', trim($text));

        return $text !== '' ? mb_substr($text, 0, 500) : null;
    }

    /**
     * 寻款批量导入专用：输出「核心视觉指纹」（颜色、材质、造型、挂钩/耳针类型等），短句利于向量侧与检索。
     */
    public static function describeImportFingerprintImage(string $absolutePath): ?string
    {
        $cfg = VolcArkVisionConfig::get();
        if (!$cfg['enabled'] || !is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        $dataUrl = self::fileToDataUrl($absolutePath);
        if ($dataUrl === null) {
            return null;
        }
        $maxTok = min(220, max(96, (int) $cfg['describe_max_tokens']));
        $body = [
            'model' => $cfg['endpoint_id'],
            'max_tokens' => $maxTok,
            'temperature' => 0.2,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是珠宝/饰品图像指纹标注员。只输出一行中文，30～90 字。必须包含：主色与金属光泽、材质（合金/银/锆石/珍珠等）、整体造型轮廓、佩戴结构（耳针/耳钩/耳圈/吊坠扣/链扣等）。不要编号、价格、营销语、品牌名。',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => '请提取这张饰品参考图的「核心视觉指纹」，一行输出，便于以文搜图与去重。'],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ],
                ],
            ],
        ];
        $raw = self::chatCompletionRaw($cfg, $body, 'describe_import_fingerprint');
        $text = self::parseChatCompletionText($raw);
        if ($text === null) {
            return null;
        }
        $text = preg_replace("/\s+/u", ' ', trim($text));

        return $text !== '' ? mb_substr($text, 0, 500) : null;
    }

    /**
     * @param list<array{code:string,desc:string,hot:string}> $catalog
     * @return array{ok:bool, matches?: list<array{product_code:string, score:float, reason:string}>, error?:string, raw?:string, fallback_keyword?:bool}
     */
    public static function matchPhotoToCatalog(string $absolutePath, array $catalog, ?string $userHint = null): array
    {
        $cfg = VolcArkVisionConfig::get();
        if (!$cfg['enabled']) {
            return ['ok' => false, 'error' => '未配置火山方舟接入点'];
        }
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return ['ok' => false, 'error' => '无法读取查询图片'];
        }
        if ($catalog === []) {
            return ['ok' => false, 'error' => '库内无可用清单'];
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
            $desc = mb_substr(trim($desc), 0, 240);
            $lines[] = $code . '|' . $desc . ($hot !== '' ? '|爆款:' . mb_substr($hot, 0, 80) : '');
        }
        $block = implode("\n", $lines);

        $hintBlock = '';
        if ($userHint !== null && trim($userHint) !== '') {
            $hintBlock = "\n\n用户补充说明（请结合实拍与下列清单综合判断）：" . mb_substr(trim($userHint), 0, 300);
        }

        $instruction = <<<TXT
你是一个仓库对货助手。请识别这张实拍图中的**饰品**（耳环、手链、项链、吊坠、戒指等）特征，并在以下产品清单中找到最匹配的编号。

清单格式：每行「产品编号|视觉特征描述|可选爆款备注」。只能从清单中已出现的编号里选择，禁止编造编号。结合品类、金属质感、造型、镶嵌与风格比对。

{$hintBlock}

**只输出一个 JSON 对象**，不要 markdown，不要其它文字。格式严格如下：
{"matches":[{"product_code":"编号","score":0.95,"reason":"..."}],"note":"可选"}
matches 最多 5 条，score 为 0～1 的浮点数，reason 为中文短语（36 字内，可说明品类与相似依据）。

清单：
{$block}
TXT;

        $body = [
            'model' => $cfg['endpoint_id'],
            'max_tokens' => $cfg['match_max_tokens'],
            'temperature' => 0.15,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是饰品仓库对货助手（耳环、手链、项链等）。只输出合法 JSON，键名使用英文 product_code、score、reason、matches、note。',
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

        $retries = (int) $cfg['retry_times'];
        $lastRaw = null;
        for ($i = 0; $i < $retries; $i++) {
            if ($i > 0) {
                usleep(400000);
            }
            $raw = self::chatCompletionRaw($cfg, $body, 'match_catalog');
            $lastRaw = $raw;
            if ($raw === null) {
                continue;
            }
            $json = self::extractJsonObject($raw);
            if ($json === null) {
                Log::warning('volc_ark_match parse fail attempt ' . ($i + 1) . ': ' . substr($raw, 0, 400));
                continue;
            }
            $matches = $json['matches'] ?? [];
            if (!is_array($matches)) {
                continue;
            }
            $out = [];
            foreach ($matches as $m) {
                if (!is_array($m)) {
                    continue;
                }
                $code = trim((string) ($m['product_code'] ?? $m['Product_ID'] ?? $m['product_id'] ?? ''));
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

        return [
            'ok' => false,
            'error' => '豆包视觉请求失败或返回无法解析，请稍后重试',
            'raw' => $lastRaw !== null ? mb_substr((string) $lastRaw, 0, 200) : null,
        ];
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $body
     */
    private static function chatCompletionRaw(array $cfg, array $body, string $purpose = 'chat'): ?string
    {
        $url = $cfg['base_url'] . '/chat/completions';
        $endpointId = (string) ($cfg['endpoint_id'] ?? '');
        Log::info('[volc_ark] 豆包请求开始', [
            'purpose' => $purpose,
            'endpoint_id' => $endpointId,
            'base_url' => (string) ($cfg['base_url'] ?? ''),
            'url' => $url,
        ]);
        try {
            $client = new Client([
                'timeout' => $cfg['timeout_seconds'],
                'connect_timeout' => 20,
                'http_errors' => false,
                'verify' => true,
            ]);
            $res = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $cfg['access_key'],
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);
            $code = $res->getStatusCode();
            $raw = (string) $res->getBody();
            if ($code < 200 || $code >= 300) {
                Log::warning('[volc_ark] 豆包 HTTP 失败', [
                    'purpose' => $purpose,
                    'endpoint_id' => $endpointId,
                    'http' => $code,
                    'body_snip' => mb_substr($raw, 0, 600),
                ]);

                return null;
            }

            $replyText = self::parseChatCompletionText($raw);
            $preview = $replyText !== null && $replyText !== ''
                ? mb_substr(preg_replace("/\s+/u", ' ', $replyText), 0, 120)
                : null;
            Log::info('[volc_ark] 豆包请求成功', [
                'purpose' => $purpose,
                'endpoint_id' => $endpointId,
                'http' => $code,
                'raw_bytes' => strlen($raw),
                'reply_text_len' => $replyText !== null ? mb_strlen($replyText) : 0,
                'reply_preview' => $preview,
            ]);

            return $raw;
        } catch (GuzzleException $e) {
            Log::warning('[volc_ark] 豆包网络异常', [
                'purpose' => $purpose,
                'endpoint_id' => $endpointId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private static function parseChatCompletionText(?string $raw): ?string
    {
        if ($raw === null || $raw === '') {
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

    private static function fileToDataUrl(string $path): ?string
    {
        $bin = @file_get_contents($path);
        if ($bin === false || $bin === '') {
            return null;
        }
        if (strlen($bin) > 12 * 1024 * 1024) {
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
