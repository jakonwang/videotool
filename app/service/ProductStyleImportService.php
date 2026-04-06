<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;

/**
 * CSV 行解析：产品编号、图片 URL/Base64/路径、爆款类型
 */
class ProductStyleImportService
{
    /** @return array{code:int, image:int, hot:?int} 列下标 */
    public static function mapHeader(array $headerCells): ?array
    {
        $norm = [];
        foreach ($headerCells as $i => $h) {
            $norm[$i] = mb_strtolower(trim((string) $h), 'UTF-8');
        }
        $codeIdx = null;
        $imgIdx = null;
        $hotIdx = null;

        $codeKeys = ['产品编号', '编号', 'id', '款号', '货号', 'product_code', 'code', 'sku'];
        $imgKeys = ['图片', '图片路径', '图', 'image', 'url', '图片url', '图片地址', '路径', 'path'];
        $hotKeys = ['爆款类型', '爆款', '类型', 'hot_type', 'category', '类目'];

        foreach ($norm as $i => $h) {
            foreach ($codeKeys as $k) {
                if ($h === mb_strtolower($k, 'UTF-8') || strpos($h, mb_strtolower($k, 'UTF-8')) !== false) {
                    $codeIdx = $i;
                    break 2;
                }
            }
        }
        foreach ($norm as $i => $h) {
            foreach ($imgKeys as $k) {
                if ($h === mb_strtolower($k, 'UTF-8') || strpos($h, mb_strtolower($k, 'UTF-8')) !== false) {
                    $imgIdx = $i;
                    break 2;
                }
            }
        }
        foreach ($norm as $i => $h) {
            foreach ($hotKeys as $k) {
                if ($h === mb_strtolower($k, 'UTF-8') || strpos($h, mb_strtolower($k, 'UTF-8')) !== false) {
                    $hotIdx = $i;
                    break 2;
                }
            }
        }

        if ($codeIdx === null || $imgIdx === null) {
            return null;
        }

        return ['code' => $codeIdx, 'image' => $imgIdx, 'hot' => $hotIdx];
    }

    /**
     * @return array{ref: string, temp: string, ok: bool} ref 写入库展示；temp 供向量提取
     */
    public static function resolveImage(string $raw, string $publicRoot): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return ['ref' => '', 'temp' => '', 'ok' => false];
        }
        $publicRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $publicRoot), DIRECTORY_SEPARATOR);

        if (preg_match('#^data:image/\w+;base64,#i', $raw)) {
            $b64 = preg_replace('#^data:image/\w+;base64,#i', '', $raw);
            $bin = base64_decode($b64, true);
            if ($bin === false || $bin === '') {
                return ['ref' => '', 'temp' => '', 'ok' => false];
            }
            $tmp = self::tempPath('jpg');
            file_put_contents($tmp, $bin);

            return ['ref' => '(Base64)', 'temp' => $tmp, 'ok' => true];
        }

        if (preg_match('#^https?://#i', $raw)) {
            $timeout = (int) (Config::get('product_search.fetch_image_timeout') ?: 30);
            $ctx = stream_context_create([
                'http' => ['timeout' => $timeout],
                'https' => ['timeout' => $timeout],
            ]);
            $bin = @file_get_contents($raw, false, $ctx);
            if ($bin === false || $bin === '') {
                return ['ref' => $raw, 'temp' => '', 'ok' => false];
            }
            $ext = 'jpg';
            if (preg_match('#\.(png|jpe?g|gif|webp)(\?|$)#i', $raw, $mm)) {
                $ext = strtolower($mm[1]);
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
            }
            $tmp = self::tempPath($ext);
            file_put_contents($tmp, $bin);

            return ['ref' => $raw, 'temp' => $tmp, 'ok' => true];
        }

        $rel = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $raw);
        if ($rel !== '' && ($rel[0] === '/' || $rel[0] === '\\')) {
            $path = $publicRoot . $rel;
        } else {
            $path = $publicRoot . DIRECTORY_SEPARATOR . $rel;
        }
        if (is_file($path) && is_readable($path)) {
            $webRef = (strpos($raw, '/') === 0 || strpos($raw, '\\') === 0) ? $raw : ('/' . str_replace(DIRECTORY_SEPARATOR, '/', $rel));

            return ['ref' => $webRef, 'temp' => $path, 'ok' => true];
        }

        return ['ref' => $raw, 'temp' => '', 'ok' => false];
    }

    /**
     * 将临时/提取的图片复制到 public/uploads/product_style/，便于列表与 H5 展示
     *
     * @return string|null 站内路径如 /uploads/product_style/ps_xxx.jpg，失败返回 null
     */
    public static function persistStyleImageToPublic(string $sourceAbsolute, string $publicRoot): ?string
    {
        $sourceAbsolute = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourceAbsolute);
        if (!\is_file($sourceAbsolute) || !\is_readable($sourceAbsolute)) {
            return null;
        }
        $publicRoot = \rtrim(\str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $publicRoot), DIRECTORY_SEPARATOR);
        $sub = 'uploads' . DIRECTORY_SEPARATOR . 'product_style';
        $dir = $publicRoot . DIRECTORY_SEPARATOR . $sub;
        if (!\is_dir($dir) && !@\mkdir($dir, 0755, true) && !\is_dir($dir)) {
            return null;
        }
        $ext = \strtolower(\pathinfo($sourceAbsolute, PATHINFO_EXTENSION) ?: 'jpg');
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!\in_array($ext, $allowed, true)) {
            $ext = 'jpg';
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $name = 'ps_' . \date('Ymd') . '_' . \bin2hex(\random_bytes(8)) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
        if (!@\copy($sourceAbsolute, $dest)) {
            return null;
        }

        return '/' . \str_replace(DIRECTORY_SEPARATOR, '/', $sub . DIRECTORY_SEPARATOR . $name);
    }

    /**
     * Excel 导入：按文件内容 md5 命名保存到 public/uploads/products/，供寻款索引与豆包识别。
     *
     * @return string|null 站内路径如 /uploads/products/{md5}.jpg
     */
    public static function saveImportImageToProductsDir(string $sourceAbsolute, string $publicRoot): ?string
    {
        $sourceAbsolute = \str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $sourceAbsolute);
        if (!\is_file($sourceAbsolute) || !\is_readable($sourceAbsolute)) {
            return null;
        }
        $bin = @\file_get_contents($sourceAbsolute);
        if ($bin === false || $bin === '') {
            return null;
        }
        $publicRoot = \rtrim(\str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $publicRoot), DIRECTORY_SEPARATOR);
        $sub = 'uploads' . DIRECTORY_SEPARATOR . 'products';
        $dir = $publicRoot . DIRECTORY_SEPARATOR . $sub;
        if (!\is_dir($dir) && !@\mkdir($dir, 0755, true) && !\is_dir($dir)) {
            return null;
        }
        $ext = \strtolower(\pathinfo($sourceAbsolute, PATHINFO_EXTENSION) ?: 'jpg');
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
        if (!\in_array($ext, $allowed, true)) {
            $ext = 'jpg';
        }
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $name = \md5($bin) . '.' . $ext;
        $dest = $dir . DIRECTORY_SEPARATOR . $name;
        if (\is_file($dest)) {
            return '/' . \str_replace(DIRECTORY_SEPARATOR, '/', $sub . DIRECTORY_SEPARATOR . $name);
        }
        if (!@\file_put_contents($dest, $bin)) {
            return null;
        }

        return '/' . \str_replace(DIRECTORY_SEPARATOR, '/', $sub . DIRECTORY_SEPARATOR . $name);
    }

    /** 供 Excel 嵌入图等写入临时文件 */
    public static function createTempImagePath(string $ext): string
    {
        return self::tempPath($ext);
    }

    private static function tempPath(string $ext): string
    {
        $dir = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . 'style_import';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir . DIRECTORY_SEPARATOR . uniqid('img_', true) . '.' . ltrim($ext, '.');

    }
}
