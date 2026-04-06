<?php
declare(strict_types=1);

namespace app\service;

use app\model\Influencer;
use think\facade\Db;

/**
 * 达人 tiktok_id 规范化（@handle）、表头映射、行入库
 */
class InfluencerService
{
    /**
     * 将用户输入规范为库内唯一键：小写、前导 @
     */
    public static function normalizeTiktokId(string $raw): ?string
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        if ($s[0] === '@') {
            $s = substr($s, 1);
        }
        $s = trim($s);
        if ($s === '') {
            return null;
        }
        if (!preg_match('/^[a-zA-Z0-9._]{1,120}$/', $s)) {
            return null;
        }

        return '@' . strtolower($s);
    }

    /**
     * @param list<string> $row
     * @return array{tiktok:int, nickname:?int, avatar:?int, followers:?int, contact:?int, region:?int, status:?int}|null
     */
    public static function mapHeader(array $row): ?array
    {
        $norm = [];
        foreach ($row as $i => $cell) {
            $norm[$i] = self::normHeaderCell((string) $cell);
        }

        $tikTokIdx = null;
        foreach ($norm as $i => $h) {
            if ($h === '') {
                continue;
            }
            if (
                str_contains($h, 'tiktok')
                || $h === 'handle'
                || $h === 'username'
                || str_contains($h, '抖音')
                || $h === 'tk'
                || $h === 'tk号'
                || str_contains($h, '用户名')
            ) {
                $tikTokIdx = (int) $i;
                break;
            }
        }

        $find = static function (array $norm, array $needles): ?int {
            foreach ($norm as $i => $h) {
                if ($h === '') {
                    continue;
                }
                foreach ($needles as $n) {
                    if ($h === $n || str_contains($h, $n)) {
                        return (int) $i;
                    }
                }
            }

            return null;
        };

        if ($tikTokIdx === null) {
            if (isset($row[0]) && trim((string) $row[0]) !== '') {
                $h0 = $norm[0] ?? '';
                if ($h0 === '' || preg_match('/^(tiktok|handle|用户|达人)/u', (string) $row[0])) {
                    $tikTokIdx = 0;
                }
            }
        }
        if ($tikTokIdx === null) {
            return null;
        }

        return [
            'tiktok' => $tikTokIdx,
            'nickname' => $find($norm, ['nickname', '昵称', 'name', '名称']),
            'avatar' => $find($norm, ['avatar', '头像', 'head']),
            'followers' => $find($norm, ['follower', '粉丝', 'fans']),
            'contact' => $find($norm, ['contact', '联系', 'wechat', '微信', 'line', 'phone', '手机', '邮箱', 'email', 'whatsapp']),
            'region' => $find($norm, ['region', '地区', '国家', 'country']),
            'status' => $find($norm, ['status', '状态']),
        ];
    }

    private static function normHeaderCell(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');

        return preg_replace('/\s+/u', '', $s) ?? $s;
    }

    /**
     * 粉丝数：支持 1.2万、12000、1,200
     */
    public static function parseFollowerCount(string $raw): int
    {
        $s = trim($raw);
        if ($s === '') {
            return 0;
        }
        if (preg_match('/([\d.]+)\s*万/u', $s, $m)) {
            return (int) round((float) $m[1] * 10000);
        }
        $s = preg_replace('/[^\d]/', '', $s) ?? '';

        return $s === '' ? 0 : (int) $s;
    }

    /**
     * 0 待联系 1 合作中 2 黑名单
     */
    public static function parseStatus(string $raw): ?int
    {
        $s = trim($raw);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $s)) {
            $n = (int) $s;
            if ($n >= 0 && $n <= 2) {
                return $n;
            }
        }
        $l = mb_strtolower($s, 'UTF-8');
        if (str_contains($l, '黑') || str_contains($l, 'block')) {
            return 2;
        }
        if (str_contains($l, '合作') || str_contains($l, 'ok') || str_contains($l, 'active')) {
            return 1;
        }
        if (str_contains($l, '待') || str_contains($l, 'pending') || str_contains($l, '新')) {
            return 0;
        }

        return null;
    }

    /**
     * 写入 contact_info 列：合法 JSON 则原样；否则存 {"text":"..."}
     */
    public static function normalizeContactInfo(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $t = trim($raw);
        if ($t === '') {
            return null;
        }
        if ($t[0] === '{' || $t[0] === '[') {
            $j = json_decode($t, true);
            if (is_array($j)) {
                $enc = json_encode($j, JSON_UNESCAPED_UNICODE);
                if ($enc !== false) {
                    return $enc;
                }
            }
        }

        return json_encode(['text' => $t], JSON_UNESCAPED_UNICODE) ?: null;
    }

    /**
     * @param array{tiktok:int, nickname:?int, avatar:?int, followers:?int, contact:?int, region:?int, status:?int} $map
     * @param list<string|null> $row
     * @return array{ok:bool, inserted?:bool, updated?:bool, skip?:bool, reason?:string}
     */
    public static function upsertFromRow(array $map, array $row): array
    {
        $ti = $map['tiktok'];
        if (!isset($row[$ti])) {
            return ['ok' => false, 'reason' => 'no_tiktok_cell'];
        }
        $handle = self::normalizeTiktokId((string) $row[$ti]);
        if ($handle === null) {
            return ['ok' => false, 'reason' => 'bad_handle'];
        }

        $nick = '';
        if (isset($map['nickname']) && $map['nickname'] !== null && isset($row[$map['nickname']])) {
            $nick = trim((string) $row[$map['nickname']]);
        }
        $avatar = null;
        if (isset($map['avatar']) && $map['avatar'] !== null && isset($row[$map['avatar']])) {
            $a = trim((string) $row[$map['avatar']]);
            $avatar = $a !== '' ? mb_substr($a, 0, 1024) : null;
        }
        $followers = 0;
        if (isset($map['followers']) && $map['followers'] !== null && isset($row[$map['followers']])) {
            $followers = self::parseFollowerCount((string) $row[$map['followers']]);
        }
        $contactJson = null;
        if (isset($map['contact']) && $map['contact'] !== null && isset($row[$map['contact']])) {
            $contactJson = self::normalizeContactInfo((string) $row[$map['contact']]);
        }
        $region = null;
        if (isset($map['region']) && $map['region'] !== null && isset($row[$map['region']])) {
            $r = trim((string) $row[$map['region']]);
            $region = $r !== '' ? mb_substr($r, 0, 64) : null;
        }
        $status = 1;
        if (isset($map['status']) && $map['status'] !== null && isset($row[$map['status']])) {
            $st = self::parseStatus((string) $row[$map['status']]);
            if ($st !== null) {
                $status = $st;
            }
        }

        $exists = Influencer::where('tiktok_id', $handle)->find();
        if ($exists) {
            $changed = false;
            if ($nick !== '') {
                $exists->nickname = $nick;
                $changed = true;
            }
            if ($avatar !== null) {
                $exists->avatar_url = $avatar;
                $changed = true;
            }
            if ($followers > 0) {
                $exists->follower_count = $followers;
                $changed = true;
            }
            if ($contactJson !== null) {
                $exists->contact_info = $contactJson;
                $changed = true;
            }
            if ($region !== null) {
                $exists->region = $region;
                $changed = true;
            }
            $exists->status = $status;
            $changed = true;
            if ($changed) {
                $exists->save();
            }

            return ['ok' => true, 'updated' => true];
        }

        Influencer::create([
            'tiktok_id' => $handle,
            'nickname' => $nick,
            'avatar_url' => $avatar,
            'follower_count' => $followers,
            'contact_info' => $contactJson,
            'region' => $region,
            'status' => $status,
        ]);

        return ['ok' => true, 'inserted' => true];
    }

    /**
     * 下拉/搜索：keyword 匹配 tiktok_id、nickname
     *
     * @return list<array{id:int,tiktok_id:string,nickname:string}>
     */
    public static function searchOptions(string $keyword, int $limit = 20): array
    {
        $keyword = trim($keyword);
        if ($limit <= 0) {
            $limit = 20;
        }
        if ($limit > 50) {
            $limit = 50;
        }
        $q = Db::name('influencers')->order('id', 'desc');
        if ($keyword !== '') {
            $q->where(function ($sub) use ($keyword) {
                $sub->whereLike('tiktok_id', '%' . $keyword . '%')
                    ->whereOr('nickname', 'like', '%' . $keyword . '%');
            });
        }
        $rows = $q->limit($limit)->select();
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id' => (int) $r['id'],
                'tiktok_id' => (string) $r['tiktok_id'],
                'nickname' => (string) ($r['nickname'] ?? ''),
            ];
        }

        return $out;
    }
}
