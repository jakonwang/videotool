<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;
use think\facade\Log;

/**
 * Style-image embedding service.
 * Primary path: Python script `tools/product_style_search/embed_image.py`.
 * Fallback path: deterministic vector from file bytes (to keep imports available).
 */
class ProductStyleEmbeddingService
{
    /** @var string last raw embed output (truncated) for diagnostics */
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
     * Extract feature vector from local image file.
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
            Log::error('embed_image.py not found: ' . $script);

            return self::fallbackVectorIfEnabled($absolutePath, 'script_missing');
        }

        $baseCmd = self::buildEmbedCommand($script, $absolutePath);
        $raw = self::runShellCapture($baseCmd);
        self::$lastRawOutput = strlen($raw) > 4000 ? substr($raw, -4000) : $raw;
        if ($raw === '') {
            Log::error('embed empty output: ' . $baseCmd);

            return self::fallbackVectorIfEnabled($absolutePath, 'empty_output');
        }

        $vec = self::parseEmbedJsonLines($raw);
        if ($vec === null) {
            Log::error('embed invalid json: ' . substr($raw, 0, 800));

            return self::fallbackVectorIfEnabled($absolutePath, 'invalid_json');
        }

        return $vec;
    }

    /**
     * Run subprocess and capture merged stdout/stderr.
     */
    private static function runShellCapture(string $baseCmd): string
    {
        $merged = $baseCmd . ' 2>&1';
        if (function_exists('exec')) {
            $out = [];
            $code = 0;
            @exec($merged, $out, $code);

            return trim(implode("\n", $out));
        }
        if (function_exists('proc_open')) {
            $spec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $proc = @proc_open($baseCmd, $spec, $pipes, null, null);
            if (is_resource($proc)) {
                fclose($pipes[0]);
                $stdout = (string) stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $stderr = (string) stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                proc_close($proc);
                $combined = $stdout;
                if ($stderr !== '') {
                    $combined .= ($stdout !== '' ? "\n" : '') . $stderr;
                }

                return trim($combined);
            }
        }
        if (function_exists('shell_exec')) {
            $r = @shell_exec($merged);

            return trim((string) $r);
        }
        Log::error('embed subprocess disabled: exec/proc_open/shell_exec unavailable (possibly disabled by php.ini disable_functions)');

        return '';
    }

    /**
     * Build python execution command.
     */
    private static function buildEmbedCommand(string $script, string $absolutePath): string
    {
        $configured = trim((string) (Config::get('product_search.python_bin') ?? ''));
        if ($configured !== '') {
            return sprintf(
                '%s %s %s',
                escapeshellarg($configured),
                escapeshellarg($script),
                escapeshellarg($absolutePath)
            );
        }
        if (PHP_OS_FAMILY === 'Windows') {
            return sprintf(
                'py -3 %s %s',
                escapeshellarg($script),
                escapeshellarg($absolutePath)
            );
        }

        return sprintf(
            '%s %s %s',
            escapeshellarg('python3'),
            escapeshellarg($script),
            escapeshellarg($absolutePath)
        );
    }

    /**
     * Parse JSON payload from script output. Output may contain non-JSON lines.
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
            Log::warning('embed failed: ' . (string) $data['error']);

            return null;
        }

        return array_map(static function ($v) {
            return (float) $v;
        }, $data);
    }

    /**
     * @return float[]|null
     */
    private static function fallbackVectorIfEnabled(string $absolutePath, string $reason): ?array
    {
        $enabled = Config::get('product_search.embedding_fallback_enabled');
        if ($enabled === null) {
            $enabled = true;
        }
        if (!$enabled) {
            return null;
        }

        $dims = (int) (Config::get('product_search.embedding_fallback_dims') ?? 128);
        if ($dims < 16) {
            $dims = 16;
        } elseif ($dims > 1024) {
            $dims = 1024;
        }

        $vec = self::buildStableFallbackVector($absolutePath, $dims);
        if ($vec === null) {
            return null;
        }

        self::$lastRawOutput = '[fallback:' . $reason . ']';
        Log::warning('embed fallback enabled: ' . $reason . ', dims=' . $dims);

        return $vec;
    }

    /**
     * Build deterministic pseudo-vector from image bytes.
     *
     * @return float[]|null
     */
    private static function buildStableFallbackVector(string $absolutePath, int $dims): ?array
    {
        $bin = @file_get_contents($absolutePath);
        if ($bin === false || $bin === '') {
            return null;
        }

        $seed = hash('sha256', $bin, true);
        if ($seed === '') {
            return null;
        }

        $out = [];
        $counter = 0;
        while (count($out) < $dims) {
            $chunk = hash('sha256', $seed . pack('N', $counter), true);
            $counter++;
            $len = strlen($chunk);
            for ($i = 0; $i < $len && count($out) < $dims; $i++) {
                $out[] = ord($chunk[$i]) / 255.0;
            }
        }

        return $out;
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

