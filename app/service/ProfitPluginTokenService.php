<?php
declare(strict_types=1);

namespace app\service;

use think\Request;
use think\facade\Db;

class ProfitPluginTokenService
{
    public const SCOPE_INGEST = 'profit_ingest';

    public static function generatePlainToken(): string
    {
        try {
            return 'pcp_' . bin2hex(random_bytes(24));
        } catch (\Throwable $e) {
            return 'pcp_' . md5(uniqid('profit_plugin_', true)) . md5((string) mt_rand());
        }
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', trim($token));
    }

    public static function extractTokenFromRequest(Request $request, ?array $payload = null): string
    {
        $token = trim((string) $request->header('x-profit-plugin-token', ''));
        if ($token === '') {
            $auth = trim((string) $request->header('authorization', ''));
            if (stripos($auth, 'bearer ') === 0) {
                $token = trim(substr($auth, 7));
            }
        }

        if ($token === '' && is_array($payload)) {
            $token = trim((string) ($payload['token'] ?? ''));
        }

        if ($token === '' && $payload === null) {
            $raw = (string) $request->getContent();
            if ($raw !== '' && ($raw[0] === '{' || $raw[0] === '[')) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $token = trim((string) ($decoded['token'] ?? ''));
                }
            }
        }

        if ($token === '') {
            $token = trim((string) $request->param('token', ''));
        }

        return $token;
    }

    /**
     * @return array{
     *   id:int,
     *   token:string,
     *   token_prefix:string,
     *   scope:string,
     *   expires_at:?string
     * }
     */
    public static function createToken(
        int $tenantId,
        int $createdBy = 0,
        string $name = '',
        int $expiresDays = 30,
        string $scope = self::SCOPE_INGEST
    ): array {
        $tenantId = max(1, $tenantId);
        $scope = self::normalizeScope($scope);
        $plain = self::generatePlainToken();
        $now = date('Y-m-d H:i:s');
        $expiresAt = null;
        if ($expiresDays > 0) {
            $expiresAt = date('Y-m-d H:i:s', strtotime('+' . min(3650, $expiresDays) . ' days'));
        }
        $tokenPrefix = substr($plain, 0, 16);
        $id = (int) Db::name('growth_profit_plugin_tokens')->insertGetId([
            'tenant_id' => $tenantId,
            'token_name' => mb_substr(trim($name) !== '' ? trim($name) : 'TikTok Plugin', 0, 64),
            'token_prefix' => $tokenPrefix,
            'token_hash' => self::tokenHash($plain),
            'scope' => $scope,
            'status' => 1,
            'expires_at' => $expiresAt,
            'created_by' => $createdBy > 0 ? $createdBy : null,
            'last_used_at' => null,
            'last_used_ip' => null,
            'revoked_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'id' => $id,
            'token' => $plain,
            'token_prefix' => $tokenPrefix,
            'scope' => $scope,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @return array{ok:bool,reason:string,row?:array<string,mixed>}
     */
    public static function verifyToken(string $token, string $scope = self::SCOPE_INGEST): array
    {
        $raw = trim($token);
        if ($raw === '') {
            return ['ok' => false, 'reason' => 'token_required'];
        }
        $scope = self::normalizeScope($scope);
        try {
            $row = Db::name('growth_profit_plugin_tokens')
                ->where('token_hash', self::tokenHash($raw))
                ->find();
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'token_verify_failed'];
        }
        if (!is_array($row)) {
            return ['ok' => false, 'reason' => 'token_invalid'];
        }
        if ((int) ($row['status'] ?? 0) !== 1 || trim((string) ($row['revoked_at'] ?? '')) !== '') {
            return ['ok' => false, 'reason' => 'token_revoked'];
        }
        if ((string) ($row['scope'] ?? '') !== $scope) {
            return ['ok' => false, 'reason' => 'token_scope_invalid'];
        }
        $expiresAt = trim((string) ($row['expires_at'] ?? ''));
        if ($expiresAt !== '' && strtotime($expiresAt) !== false && strtotime($expiresAt) < time()) {
            return ['ok' => false, 'reason' => 'token_expired'];
        }

        return ['ok' => true, 'reason' => 'ok', 'row' => $row];
    }

    public static function touchTokenUsage(int $tokenId, string $ip = ''): void
    {
        if ($tokenId <= 0) {
            return;
        }
        try {
            Db::name('growth_profit_plugin_tokens')
                ->where('id', $tokenId)
                ->update([
                    'last_used_at' => date('Y-m-d H:i:s'),
                    'last_used_ip' => $ip !== '' ? mb_substr($ip, 0, 64) : null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    public static function revokeToken(int $tenantId, int $tokenId): bool
    {
        if ($tokenId <= 0) {
            return false;
        }
        try {
            $affected = Db::name('growth_profit_plugin_tokens')
                ->where('tenant_id', max(1, $tenantId))
                ->where('id', $tokenId)
                ->where('status', 1)
                ->update([
                    'status' => 0,
                    'revoked_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            return (int) $affected > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function listTokens(int $tenantId, int $limit = 40): array
    {
        $tenantId = max(1, $tenantId);
        $limit = max(1, min(200, $limit));
        try {
            $rows = Db::name('growth_profit_plugin_tokens')
                ->where('tenant_id', $tenantId)
                ->order('id', 'desc')
                ->limit($limit)
                ->select()
                ->toArray();
        } catch (\Throwable $e) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) ($row['id'] ?? 0),
                'token_name' => (string) ($row['token_name'] ?? ''),
                'token_prefix' => (string) ($row['token_prefix'] ?? ''),
                'scope' => (string) ($row['scope'] ?? ''),
                'status' => (int) ($row['status'] ?? 0),
                'expires_at' => (string) ($row['expires_at'] ?? ''),
                'last_used_at' => (string) ($row['last_used_at'] ?? ''),
                'last_used_ip' => (string) ($row['last_used_ip'] ?? ''),
                'created_by' => (int) ($row['created_by'] ?? 0),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'revoked_at' => (string) ($row['revoked_at'] ?? ''),
            ];
        }

        return $items;
    }

    private static function normalizeScope(string $scope): string
    {
        $raw = trim($scope);
        if ($raw === '') {
            return self::SCOPE_INGEST;
        }
        return mb_substr($raw, 0, 32);
    }
}

