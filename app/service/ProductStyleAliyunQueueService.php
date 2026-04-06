<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 寻款导入后写入阿里云图搜的异步队列（同请求末尾或后台「同步队列」消费）
 */
class ProductStyleAliyunQueueService
{
    public static function queueDir(): string
    {
        $d = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'is_queue';
        if (!is_dir($d)) {
            @mkdir($d, 0755, true);
        }

        return $d;
    }

    public static function makePicName(string $imageRef, string $tempPath): string
    {
        $path = parse_url($imageRef, PHP_URL_PATH);
        $base = is_string($path) && $path !== '' ? basename($path) : '';
        if ($base === '' || $base === '/' || $base === '.') {
            $base = basename($tempPath);
        }
        $base = (string) preg_replace('/[^a-zA-Z0-9._\x{4e00}-\x{9fa5}-]/u', '_', $base);
        if ($base === '' || $base === '_') {
            $base = 'pic.jpg';
        }

        return self::clipStr($base, 256);
    }

    public static function buildCustom(string $hotType): string
    {
        $j = json_encode(['hot_type' => $hotType], JSON_UNESCAPED_UNICODE);
        if (strlen($j) > 4096) {
            return substr($j, 0, 4096);
        }

        return $j;
    }

    /**
     * 复制图片到 runtime 并入队；导入流程在删除临时文件前调用。
     */
    public static function enqueue(string $productCode, string $sourceAbsPath, string $picName, string $hotType): bool
    {
        if (!AliyunImageSearchConfig::get()['enabled']) {
            return false;
        }
        $src = realpath($sourceAbsPath);
        if ($src === false || !is_readable($src)) {
            return false;
        }
        $ext = pathinfo($picName, PATHINFO_EXTENSION);
        if ($ext === '') {
            $ext = 'jpg';
        }
        $extSafe = preg_replace('/[^a-z0-9]/i', '', $ext) ?: 'jpg';
        $dest = self::queueDir() . DIRECTORY_SEPARATOR . bin2hex(random_bytes(16)) . '.' . $extSafe;
        if (!@copy($src, $dest)) {
            return false;
        }
        try {
            Db::name('product_style_is_queue')->insert([
                'product_code' => self::clipStr($productCode, 128),
                'pic_name' => self::clipStr($picName, 256),
                'image_path' => $dest,
                'custom_content' => self::buildCustom($hotType),
                'status' => 0,
                'attempts' => 0,
            ]);

            return true;
        } catch (\Throwable $e) {
            @unlink($dest);

            return false;
        }
    }

    /**
     * @return array{processed:int, ok:int, fail:int}
     */
    public static function drain(int $maxJobs, ?int $timeLimitSec = null): array
    {
        $cfg = AliyunImageSearchConfig::get();
        $stats = ['processed' => 0, 'ok' => 0, 'fail' => 0];
        if (!$cfg['enabled']) {
            return $stats;
        }
        $deadline = $timeLimitSec !== null ? time() + $timeLimitSec : null;
        $delayUs = max(0, (int) $cfg['qps_delay_ms']) * 1000;
        $throttleBursts = 0;

        for ($i = 0; $i < $maxJobs; $i++) {
            if ($deadline !== null && time() >= $deadline) {
                break;
            }
            $job = Db::name('product_style_is_queue')->where('status', 0)->where('attempts', '<', 8)->order('id')->find();
            if (!$job) {
                break;
            }
            if ($delayUs > 0 && $stats['processed'] > 0) {
                usleep($delayUs);
            }

            $id = (int) $job['id'];
            $path = (string) $job['image_path'];
            if (!is_file($path)) {
                Db::name('product_style_is_queue')->where('id', $id)->update([
                    'status' => 2,
                    'error_msg' => '队列文件不存在',
                ]);
                $stats['processed']++;
                $stats['fail']++;

                continue;
            }

            $r = AliyunImageSearchService::addImageFromPath(
                (string) $job['product_code'],
                (string) $job['pic_name'],
                $path,
                (string) $job['custom_content'],
                $cfg['category_id']
            );

            if ($r['ok'] ?? false) {
                Db::name('product_style_is_queue')->where('id', $id)->update(['status' => 1, 'error_msg' => '']);
                @unlink($path);
                $stats['ok']++;
                $stats['processed']++;

                continue;
            }

            if (!empty($r['throttle'])) {
                ++$throttleBursts;
                if ($throttleBursts > 25) {
                    break;
                }
                sleep(3);
                --$i;

                continue;
            }
            $throttleBursts = 0;

            $attempts = (int) $job['attempts'] + 1;
            $err = self::clipStr((string) ($r['error'] ?? 'fail'), 500);
            if ($attempts >= 8) {
                Db::name('product_style_is_queue')->where('id', $id)->update([
                    'status' => 2,
                    'attempts' => $attempts,
                    'error_msg' => $err,
                ]);
                @unlink($path);
                $stats['fail']++;
            } else {
                Db::name('product_style_is_queue')->where('id', $id)->update([
                    'attempts' => $attempts,
                    'error_msg' => $err,
                ]);
            }
            $stats['processed']++;
        }

        return $stats;
    }

    public static function pendingCount(): int
    {
        try {
            return (int) Db::name('product_style_is_queue')->where('status', 0)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private static function clipStr(string $s, int $max): string
    {
        if (strlen($s) <= $max) {
            return $s;
        }

        return substr($s, 0, $max);
    }
}
