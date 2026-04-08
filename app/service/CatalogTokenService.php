<?php
declare(strict_types=1);

namespace app\service;

use think\facade\Config;

/**
 * Stateless catalog access token for level-based pricing.
 */
class CatalogTokenService
{
    /**
     * @return array{token:string,expire_at:int,price_level:string}
     */
    public static function generate(string $priceLevel, int $expireDays = 30): array
    {
        $level = self::normalizeLevel($priceLevel);
        $days = max(1, min(3650, $expireDays));
        $now = time();
        $exp = $now + ($days * 86400);
        $nonce = bin2hex(random_bytes(6));
        $payload = [
            'v' => 1,
            'iat' => $now,
            'exp' => $exp,
            'lvl' => $level,
            'n' => $nonce,
        ];
        $payload['sig'] = self::signature($payload);

        return [
            'token' => self::base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            'expire_at' => $exp,
            'price_level' => $level,
        ];
    }

    /**
     * @return array{ok:bool,price_level:string,msg:string,expired:bool,expire_at:int}
     */
    public static function verify(string $token): array
    {
        $raw = trim($token);
        if ($raw === '') {
            return ['ok' => false, 'price_level' => '', 'msg' => 'empty_token', 'expired' => false, 'expire_at' => 0];
        }
        $json = self::base64UrlDecode($raw);
        if ($json === '') {
            return ['ok' => false, 'price_level' => '', 'msg' => 'invalid_token', 'expired' => false, 'expire_at' => 0];
        }
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return ['ok' => false, 'price_level' => '', 'msg' => 'invalid_token', 'expired' => false, 'expire_at' => 0];
        }

        $exp = (int) ($data['exp'] ?? 0);
        $lvl = self::normalizeLevel((string) ($data['lvl'] ?? ''));
        $sig = (string) ($data['sig'] ?? '');
        if ($exp <= 0 || $lvl === '' || $sig === '') {
            return ['ok' => false, 'price_level' => '', 'msg' => 'invalid_payload', 'expired' => false, 'expire_at' => 0];
        }
        if ($exp < time()) {
            return ['ok' => false, 'price_level' => '', 'msg' => 'token_expired', 'expired' => true, 'expire_at' => $exp];
        }
        if (!hash_equals(self::signature($data), $sig)) {
            return ['ok' => false, 'price_level' => '', 'msg' => 'bad_signature', 'expired' => false, 'expire_at' => $exp];
        }

        return ['ok' => true, 'price_level' => $lvl, 'msg' => 'ok', 'expired' => false, 'expire_at' => $exp];
    }

    public static function normalizeLevel(string $priceLevel): string
    {
        $level = strtolower(trim($priceLevel));
        if ($level === '' || !preg_match('/^[a-z0-9_-]{1,32}$/', $level)) {
            return 'level1';
        }
        return $level;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function signature(array $payload): string
    {
        $v = (int) ($payload['v'] ?? 1);
        $iat = (int) ($payload['iat'] ?? 0);
        $exp = (int) ($payload['exp'] ?? 0);
        $lvl = (string) ($payload['lvl'] ?? '');
        $nonce = (string) ($payload['n'] ?? '');
        $plain = $v . '|' . $iat . '|' . $exp . '|' . $lvl . '|' . $nonce;
        return hash_hmac('sha256', $plain, self::secret());
    }

    private static function secret(): string
    {
        $fromEnv = trim((string) env('catalog.token_secret', ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }
        $dbName = (string) Config::get('database.connections.mysql.database', 'tikstar');
        return hash('sha256', 'tikstar-catalog-token:' . $dbName);
    }

    private static function base64UrlEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $encoded): string
    {
        $s = strtr($encoded, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad > 0) {
            $s .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($s, true);
        return is_string($decoded) ? $decoded : '';
    }
}
