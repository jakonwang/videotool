<?php
declare(strict_types=1);

namespace app\service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use think\facade\Log;

/**
 * 火山方舟多模态：实拍图 + 产品清单 JSON 匹配。
 * 与官方一致：`POST .../chat/completions`，`Authorization: Bearer <API Key>`，请求体 `model` 为模型 ID 或推理接入点 ep-（见 VolcArkVisionConfig）。
 */
class VolcArkVisionService
{
    /** @param array<string, mixed> $cfg VolcArkVisionConfig::get() */
    private static function chatModel(array $cfg): string
    {
        $m = trim((string) ($cfg['model'] ?? ''));
        if ($m !== '') {
            return $m;
        }

        return trim((string) ($cfg['endpoint_id'] ?? ''));
    }

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
            'model' => self::chatModel($cfg),
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
            'model' => self::chatModel($cfg),
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
     * 批量导入专用（推荐）：**单次**豆包请求，合并原「指纹 + 全量描述」意图，避免每行最多 2 次 HTTP 串行等待。
     */
    public static function describeImportSinglePassImage(string $absolutePath): ?string
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
            'model' => self::chatModel($cfg),
            'max_tokens' => $maxTok,
            'temperature' => 0.25,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是珠宝电商视觉标注员。只输出一行中文，50～140 字。请先判断品类（耳环/耳钉/耳坠、手链/手镯、项链/吊坠、戒指等饰品），再写清：主色与金属光泽、材质（合金/银/锆石/珍珠等）、造型轮廓与佩戴结构（耳针/耳钩/耳圈、链节与扣头、吊坠轮廓等）、风格（极简/复古/轻奢等）。不要编号、不要价格、不要营销语、不要品牌名。',
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => '请根据这张饰品参考图，输出一行可用于检索与寻款的视觉特征描述（兼顾指纹信息与外观细节）。'],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ],
                ],
            ],
        ];
        $raw = self::chatCompletionRaw($cfg, $body, 'describe_import_single');
        $text = self::parseChatCompletionText($raw);
        if ($text === null) {
            return null;
        }
        $text = preg_replace("/\s+/u", ' ', trim($text));

        return $text !== '' ? mb_substr($text, 0, 500) : null;
    }

    /**
     * 全自动寻款：system 注入候选清单，user 仅含实拍图；模型只输出一行编号或 NULL。
     *
     * @param list<array{code:string,desc:string,hot:string,thumb_url?:string}> $catalog 建议 ≤50 条
     * @return array{ok:bool, code?:string, is_null?:bool, error?:string, raw?:string}
     */
    public static function matchPhotoAutoWarehouse(string $absolutePath, array $catalog, ?string $userHint = null): array
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

        $allowed = [];
        $lines = [];
        foreach ($catalog as $row) {
            $code = trim((string) ($row['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $allowed[$code] = true;
            $desc = str_replace(["\r", "\n", '|'], ['', '', '／'], (string) ($row['desc'] ?? ''));
            $hot = str_replace(["\r", "\n", '|'], ['', '', '／'], (string) ($row['hot'] ?? ''));
            $desc = mb_substr(trim($desc), 0, 220);
            $thumb = isset($row['thumb_url']) ? trim((string) $row['thumb_url']) : '';
            if ($thumb === '') {
                $thumb = '-';
            }
            $hotPart = $hot !== '' ? mb_substr($hot, 0, 80) : '-';
            $lines[] = $code . '|' . $desc . '|' . $thumb . '|' . $hotPart;
        }
        if ($lines === []) {
            return ['ok' => false, 'error' => '库内无有效编号'];
        }
        $block = implode("\n", $lines);

        $systemText = <<<TXT
你是一个自动化仓库扫描器。我会给你一张实物图，请在以下库中选出最相似的编号。禁止任何开场白，禁止要求补充描述，只需直接输出编号（如：A-114），如果找不到请输出「NULL」。

【库内候选】每行格式：编号|款式描述或备注|参考图完整 URL（无则 -）|爆款类型（无则 -）
只能从下列已出现的编号中选择；禁止编造编号。若多候选接近，输出最相似的一个编号。

候选清单：
{$block}
TXT;

        $userText = '请只输出一行：清单内最相似产品编号，或单词 NULL。不要其它任何字符。';
        if ($userHint !== null && trim($userHint) !== '') {
            $userText .= ' 附加线索（可选）：' . mb_substr(trim($userHint), 0, 200);
        }

        $body = [
            'model' => self::chatModel($cfg),
            'max_tokens' => (int) ($cfg['auto_match_max_tokens'] ?? 64),
            'temperature' => 0.1,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemText,
                ],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $userText],
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
            $raw = self::chatCompletionRaw($cfg, $body, 'match_auto_warehouse');
            $lastRaw = $raw;
            if ($raw === null) {
                continue;
            }
            $reply = self::parseChatCompletionText($raw);
            if ($reply === null || trim($reply) === '') {
                Log::warning('volc_ark_auto empty reply attempt ' . ($i + 1));

                continue;
            }
            $cls = self::classifyWarehouseReply($reply, $allowed);
            if ($cls['kind'] === 'null') {
                return ['ok' => true, 'is_null' => true];
            }
            if ($cls['kind'] === 'code' && isset($cls['code'])) {
                return ['ok' => true, 'code' => (string) $cls['code']];
            }
            Log::warning('volc_ark_auto unparsed attempt ' . ($i + 1) . ': ' . mb_substr($reply, 0, 200));
        }

        return [
            'ok' => false,
            'error' => '豆包视觉请求失败或返回无法解析，请稍后重试',
            'raw' => $lastRaw !== null ? mb_substr((string) $lastRaw, 0, 200) : null,
        ];
    }

    /**
     * @param array<string, true> $allowedMap
     * @return array{kind:'code', code:string}|array{kind:'null'}|array{kind:'unparsed'}
     */
    private static function classifyWarehouseReply(string $text, array $allowedMap): array
    {
        $t = trim($text);
        if ($t === '') {
            return ['kind' => 'unparsed'];
        }
        if (preg_match('/```([\s\S]*?)```/', $t, $m)) {
            $t = trim($m[1]);
        }
        $t = trim(preg_replace('/^\*+\s*/u', '', $t));
        $lines = preg_split("/\r\n|\r|\n/", $t);
        $first = trim((string) ($lines[0] ?? ''));
        $first = trim($first, " \t\"'「」`：:*#");
        if (preg_match('/^(NULL|NONE|N\/A|NA|无|没有|未找到)$/iu', $first)) {
            return ['kind' => 'null'];
        }
        if (isset($allowedMap[$first])) {
            return ['kind' => 'code', 'code' => $first];
        }
        $codes = array_keys($allowedMap);
        usort($codes, static fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($codes as $c) {
            if ($c !== '' && (str_contains($t, $c) || str_contains($first, $c))) {
                return ['kind' => 'code', 'code' => $c];
            }
        }
        foreach ($codes as $c) {
            if ($c !== '' && strcasecmp($first, $c) === 0) {
                return ['kind' => 'code', 'code' => $c];
            }
        }

        return ['kind' => 'unparsed'];
    }

    /**
     * 运维/本地排查：发一条极简文本对话，验证 API Key、Endpoint、base_url 是否可通（不读图片）。
     * 与导入识图失败区分：若 ping 成功而导入仍失败，多为图片过大或 data URL 问题。
     *
     * @return array{
     *   ok: bool,
     *   http: int|null,
     *   ark_error: ?string,
     *   body_snip: string,
     *   hint?: string,
     *   reply_preview?: ?string,
     *   exception?: string,
     *   error?: string,
     *   curl_errno?: int
     * }
     */
    public static function pingChatCompletion(): array
    {
        $cfg = VolcArkVisionConfig::get();
        $bearer = (string) ($cfg['access_key'] ?? '');
        $model = self::chatModel($cfg);
        $baseUrl = rtrim((string) ($cfg['base_url'] ?? ''), '/');
        $verifySsl = (bool) ($cfg['verify_ssl'] ?? true);
        $timeout = (int) ($cfg['timeout_seconds'] ?? 120);

        if ($bearer === '' || $model === '') {
            return [
                'ok' => false,
                'http' => null,
                'ark_error' => null,
                'body_snip' => '',
                'hint' => '缺少 API Key 或 model：按官方快速入门需 Bearer + 请求体 model。请填写 API Key，并在「模型 ID」或「接入点 ep-」中至少一项，或设置 VOLC_ARK_MODEL / VOLC_ENDPOINT_ID。',
            ];
        }
        if ($baseUrl === '') {
            $baseUrl = 'https://ark.cn-beijing.volces.com/api/v3';
        }
        $url = $baseUrl . '/chat/completions';
        $body = [
            'model' => $model,
            'max_tokens' => 16,
            'temperature' => 0,
            'messages' => [
                ['role' => 'user', 'content' => '只回复两个汉字：好的'],
            ],
        ];
        try {
            $client = new Client([
                'timeout' => min(\max(30, $timeout), 90),
                'connect_timeout' => 25,
                'http_errors' => false,
                'verify' => $verifySsl,
            ]);
            $res = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $bearer,
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
            ]);
            $code = (int) $res->getStatusCode();
            $raw = (string) $res->getBody();
            $arkErr = self::parseArkErrorMessage($raw);
            if ($code < 200 || $code >= 300) {
                return [
                    'ok' => false,
                    'http' => $code,
                    'ark_error' => $arkErr,
                    'body_snip' => \mb_substr($raw, 0, 800),
                    'hint' => self::pingFailureHint($code, $arkErr),
                ];
            }
            $reply = self::parseChatCompletionText($raw);

            return [
                'ok' => true,
                'http' => $code,
                'ark_error' => null,
                'body_snip' => \mb_substr($raw, 0, 240),
                'reply_preview' => $reply !== null ? \mb_substr(\preg_replace("/\s+/u", ' ', $reply), 0, 80) : null,
            ];
        } catch (\Throwable $e) {
            $out = [
                'ok' => false,
                'http' => null,
                'ark_error' => null,
                'body_snip' => '',
                'exception' => \get_class($e),
                'error' => $e->getMessage(),
            ];
            if ($e instanceof RequestException) {
                $hc = $e->getHandlerContext();
                if (isset($hc['errno'])) {
                    $out['curl_errno'] = (int) $hc['errno'];
                }
            }
            $out['hint'] = '网络/TLS 层失败，见 error 与 curl_errno；本机可试 VOLC_ARK_VERIFY_SSL=false 或 php.ini 配置 openssl.cafile';

            return $out;
        }
    }

    /**
     * Build remix/editing suggestions for short videos using Doubao text reasoning.
     *
     * @param array<string, mixed> $context
     */
    public static function generateVideoRemixSuggestion(array $context): ?string
    {
        $cfg = VolcArkVisionConfig::get();
        if (!$cfg['enabled']) {
            return null;
        }

        $title = trim((string) ($context['title'] ?? ''));
        $videoUrl = trim((string) ($context['video_url'] ?? ''));
        $usedByInfluencers = (int) ($context['used_by_influencers'] ?? 0);
        $totalGmv = (float) ($context['total_gmv'] ?? 0);
        $platform = trim((string) ($context['platform'] ?? 'tiktok'));
        $creativeCode = trim((string) ($context['ad_creative_code'] ?? ''));

        $input = [
            'title' => $title,
            'platform' => $platform,
            'creative_code' => $creativeCode,
            'used_by_influencers' => $usedByInfluencers,
            'total_gmv' => $totalGmv,
            'video_url' => $videoUrl,
        ];

        $body = [
            'model' => self::chatModel($cfg),
            'max_tokens' => min(400, max(180, (int) ($cfg['describe_max_tokens'] ?? 220))),
            'temperature' => 0.35,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => '你是 TikTok 广告素材优化专家。请输出 3-5 条可执行的混剪建议，每条包含具体秒段和动作，中文输出。',
                ],
                [
                    'role' => 'user',
                    'content' => '请根据以下素材上下文给出混剪建议（包含前3秒钩子、主体节奏、结尾CTA）。上下文: '
                        . json_encode($input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ],
            ],
        ];

        $raw = self::chatCompletionRaw($cfg, $body, 'video_remix_suggestion');
        $text = self::parseChatCompletionText($raw);
        if ($text === null) {
            return null;
        }
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, 1200);
    }

    private static function pingFailureHint(int $http, ?string $arkErr): string
    {
        if ($http === 401 || $http === 403) {
            return '鉴权失败：请确认方舟控制台 API Key 有效、已复制完整，且后台「豆包视觉」已保存。';
        }
        if ($http === 500 && $arkErr !== null && \str_contains((string) $arkErr, 'InternalServiceError')) {
            return '服务端 500：若 Base URL 含 /chat/completions 请改为仅到 /api/v3（更新代码后会自动纠正）；仍失败多为方舟侧抖动，稍后重试或换时段。';
        }
        if ($http === 404) {
            return '请求路径不存在：检查 VOLC_ARK_BASE_URL 是否为 https://ark.cn-beijing.volces.com/api/v3（无尾部斜杠、勿含 /chat/completions）。';
        }
        $msg = (string) $arkErr;
        if ($msg !== '' && (\str_contains($msg, 'model') || \str_contains($msg, 'Model') || \str_contains($msg, 'endpoint'))) {
            return 'model 字段无效：请填火山「模型列表」中的 Model ID（见文档 82379/1330310），或推理接入点 ep-（须支持对话/多模态）；勿用仅 Embedding 的接入点。';
        }

        return '请根据 ark_error 与下方原始 body 对照方舟控制台文档；仍失败时将本脚本完整输出与 runtime/log 中 [volc_ark] 豆包 HTTP 失败 条目一并排查。';
    }

    /**
     * @param array<string, mixed> $cfg
     * @param array<string, mixed> $body
     */
    private static function chatCompletionRaw(array $cfg, array $body, string $purpose = 'chat'): ?string
    {
        $url = $cfg['base_url'] . '/chat/completions';
        $modelId = self::chatModel($cfg);
        Log::info('[volc_ark] 豆包请求开始', [
            'purpose' => $purpose,
            'model' => $modelId,
            'base_url' => (string) ($cfg['base_url'] ?? ''),
            'url' => $url,
        ]);
        $verifySsl = (bool) ($cfg['verify_ssl'] ?? true);
        try {
            $client = new Client([
                'timeout' => $cfg['timeout_seconds'],
                'connect_timeout' => 25,
                'http_errors' => false,
                'verify' => $verifySsl,
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
                $arkErr = self::parseArkErrorMessage($raw);
                Log::warning('[volc_ark] 豆包 HTTP 失败', [
                    'purpose' => $purpose,
                    'model' => $modelId,
                    'http' => $code,
                    'ark_error' => $arkErr,
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
                'model' => $modelId,
                'http' => $code,
                'raw_bytes' => strlen($raw),
                'reply_text_len' => $replyText !== null ? mb_strlen($replyText) : 0,
                'reply_preview' => $preview,
            ]);

            return $raw;
        } catch (\Throwable $e) {
            $log = [
                'purpose' => $purpose,
                'model' => $modelId,
                'url' => $url,
                'verify_ssl' => $verifySsl,
                'exception' => \get_class($e),
                'error' => $e->getMessage(),
            ];
            if ($e instanceof RequestException) {
                if ($e->hasResponse()) {
                    $log['response_http'] = $e->getResponse()->getStatusCode();
                    $log['response_snip'] = \mb_substr((string) $e->getResponse()->getBody(), 0, 400);
                }
                $hc = $e->getHandlerContext();
                if (isset($hc['errno'])) {
                    $log['curl_errno'] = $hc['errno'];
                }
                if (isset($hc['error']) && \is_string($hc['error'])) {
                    $log['curl_error'] = $hc['error'];
                }
            }
            if (!$verifySsl) {
                $log['note'] = 'verify_ssl=false，仅用于排查；生产请开启并配置 CA';
            }
            Log::warning('[volc_ark] 豆包网络异常', $log);

            return null;
        }
    }

    /**
     * 方舟错误体常见格式：{"error":{"message":"...","code":"..."}} 或顶层 message。
     */
    private static function parseArkErrorMessage(string $raw): ?string
    {
        $dec = json_decode($raw, true);
        if (!is_array($dec)) {
            return null;
        }
        $err = $dec['error'] ?? null;
        if (is_array($err)) {
            $msg = isset($err['message']) ? trim((string) $err['message']) : '';
            $c = isset($err['code']) ? trim((string) $err['code']) : '';
            if ($msg === '' && $c === '') {
                return null;
            }
            if ($c !== '' && $msg !== '') {
                return $msg . ' [' . $c . ']';
            }

            return $msg !== '' ? $msg : $c;
        }
        if (isset($dec['message']) && is_string($dec['message'])) {
            $m = trim($dec['message']);

            return $m !== '' ? $m : null;
        }

        return null;
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

}
