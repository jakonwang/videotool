<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Log;

/**
 * 款式图向量：调用本地 Python MobileNet 脚本提取特征
 */
class ProductStyleEmbeddingService
{
    /** @var string 最近一次 embed 子进程原始输出（截断），用于后台诊断 */
    private static string $lastRawOutput = '';

    public static function pythonBinary(): string
    {
        $configured = trim((string) (Config::get('product_search.python_bin') ?? ''));

        return $configured !== '' ? $configured : (PHP_OS_FAMILY === 'Windows' ? 'py -3' : 'python3');
    }

    public static function getLastRawOutput(): string
    {
        return self::$lastRawOutput;
    }

    public static function embedScriptPath(): string
    {
        return root_path() . 'tools' . DIRECTORY_SEPARATOR . 'product_style_search' . DIRECTORY_SEPARATOR . 'embed_image.py';
    }

    /**
     * 对本地可读图片文件提取向量；失败返回 null
     *
     * @return float[]|null
     */
    public static function embedFile(string $absolutePath): ?array
    {
        $absolutePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $absolutePath);
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        $script = self::embedScriptPath();
        if (!is_file($script)) {
            Log::error('embed_image.py 不存在: ' . $script);

            return null;
        }
        $baseCmd = self::buildEmbedCommand($script, $absolutePath);
        $raw = self::runShellCapture($baseCmd);
        self::$lastRawOutput = \strlen($raw) > 4000 ? \substr($raw, -4000) : $raw;
        if ($raw === '') {
            Log::error('embed 无输出（请检查 php.ini 是否禁用 exec/proc_open/shell_exec，或 Python 命令是否可用）: ' . $baseCmd);

            return null;
        }
        $vec = self::parseEmbedJsonLines($raw);
        if ($vec === null) {
            Log::error('embed JSON 无效: ' . \substr($raw, 0, 800));
        }

        return $vec;
    }

    /**
     * 执行子进程并合并 stdout/stderr。优先 exec；若被 disable_functions 禁用则依次尝试 proc_open、shell_exec。
     */
    private static function runShellCapture(string $baseCmd): string
    {
        $merged = $baseCmd . ' 2>&1';
        if (\function_exists('exec')) {
            $out = [];
            $code = 0;
            @\exec($merged, $out, $code);

            return \trim(\implode("\n", $out));
        }
        if (\function_exists('proc_open')) {
            $spec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = @\proc_open($baseCmd, $spec, $pipes, null, null);
            if (\is_resource($proc)) {
                \fclose($pipes[0]);
                $stdout = (string) \stream_get_contents($pipes[1]);
                \fclose($pipes[1]);
                $stderr = (string) \stream_get_contents($pipes[2]);
                \fclose($pipes[2]);
                \proc_close($proc);
                $combined = $stdout;
                if ($stderr !== '') {
                    $combined .= ($stdout !== '' ? "\n" : '') . $stderr;
                }

                return \trim($combined);
            }
        }
        if (\function_exists('shell_exec')) {
            $r = @\shell_exec($merged);

            return \trim((string) $r);
        }
        Log::error('embed 无法执行子进程：exec、proc_open、shell_exec 均不可用（可能被 php.ini 的 disable_functions 禁用）');

        return '';
    }

    /**
     * 配置非空：单一可执行文件路径；配置为空：Windows 使用 py -3，否则 python3
     */
    private static function buildEmbedCommand(string $script, string $absolutePath): string
    {
        $configured = trim((string) (Config::get('product_search.python_bin') ?? ''));
        if ($configured !== '') {
            return sprintf(
                '%s %s %s',
                \escapeshellarg($configured),
                \escapeshellarg($script),
                \escapeshellarg($absolutePath)
            );
        }
        if (PHP_OS_FAMILY === 'Windows') {
            return sprintf(
                'py -3 %s %s',
                \escapeshellarg($script),
                \escapeshellarg($absolutePath)
            );
        }

        return sprintf(
            '%s %s %s',
            \escapeshellarg('python3'),
            \escapeshellarg($script),
            \escapeshellarg($absolutePath)
        );
    }

    /**
     * 解析脚本 stdout（可能含 PyTorch 等杂行）；取最后一行合法 JSON
     *
     * @return float[]|null
     */
    private static function parseEmbedJsonLines(string $raw): ?array
    {
        $data = json_decode($raw, true);
        if (is_array($data) && self::isEmbedPayload($data)) {
            return self::payloadToVector($data);
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            $t = trim((string) $lines[$i]);
            if ($t === '') {
                continue;
            }
            $data = json_decode($t, true);
            if (is_array($data) && self::isEmbedPayload($data)) {
                return self::payloadToVector($data);
            }
        }

        return null;
    }

    private static function isEmbedPayload(array $data): bool
    {
        if (array_key_exists('error', $data)) {
            return true;
        }

        return isset($data[0]) && is_numeric($data[0]);
    }

    /**
     * @return float[]|null
     */
    private static function payloadToVector(array $data): ?array
    {
        if (isset($data['error'])) {
            Log::warning('embed 失败: ' . (string) $data['error']);

            return null;
        }

        return array_map(static function ($v) {
            return (float) $v;
        }, $data);
    }

    /**
     * @param float[] $a
     * @param float[] $b
     */
    public static function cosineSimilarity(array $a, array $b): float
    {
        $n = min(count($a), count($b));
        if ($n <= 0) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $dot += $a[$i] * $b[$i];
            $na += $a[$i] * $a[$i];
            $nb += $b[$i] * $b[$i];
        }
        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
