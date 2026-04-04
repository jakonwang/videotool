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
    public static function pythonBinary(): string
    {
        return (string) (Config::get('product_search.python_bin') ?: 'python');
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
        $py = self::pythonBinary();
        $script = self::embedScriptPath();
        if (!is_file($script)) {
            Log::error('embed_image.py 不存在: ' . $script);

            return null;
        }
        $cmd = sprintf(
            '%s %s %s',
            escapeshellarg($py),
            escapeshellarg($script),
            escapeshellarg($absolutePath)
        );
        $out = [];
        $code = 0;
        exec($cmd . ' 2>&1', $out, $code);
        $line = trim(implode("\n", $out));
        if ($line === '') {
            Log::error('embed 无输出: ' . $cmd);

            return null;
        }
        $data = json_decode($line, true);
        if (!is_array($data)) {
            Log::error('embed JSON 无效: ' . $line);

            return null;
        }
        if (isset($data['error'])) {
            Log::warning('embed 失败: ' . (string) $data['error']);

            return null;
        }
        if (!isset($data[0]) || !is_numeric($data[0])) {
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
