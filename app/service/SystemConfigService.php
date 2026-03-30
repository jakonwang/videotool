<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Db;

/**
 * 系统键值配置（不继承 Model，避免与 think\Model::set() 等实例方法冲突）
 */
class SystemConfigService
{
    /** @var array<string, string|null> */
    private static $cache = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        try {
            $row = Db::name('system_settings')->where('skey', $key)->find();
            $v = $row ? (string) $row['svalue'] : $default;
            self::$cache[$key] = $v;

            return $v;
        } catch (\Throwable $e) {
            self::$cache[$key] = $default;

            return $default;
        }
    }

    public static function set(string $key, string $value): void
    {
        self::$cache[$key] = $value;
        try {
            $exists = Db::name('system_settings')->where('skey', $key)->find();
            if ($exists) {
                Db::name('system_settings')->where('skey', $key)->update(['svalue' => $value]);
            } else {
                Db::name('system_settings')->insert(['skey' => $key, 'svalue' => $value]);
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
