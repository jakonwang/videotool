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
     * 将可能的 GBK/GB18030 输入统一转为 UTF-8，并做基础清洗。
     */
    public static function normalizeInputText(?string $raw, int $maxLen = 0): string
    {
        if ($raw === null) {
            return '';
        }
        $s = (string) $raw;
        if ($s === '') {
            return '';
        }
        // 移除 UTF-8 BOM
        $s = (string) preg_replace('/^\xEF\xBB\xBF/', '', $s);

        if (!mb_check_encoding($s, 'UTF-8')) {
            $detected = mb_detect_encoding($s, ['GB18030', 'GBK', 'BIG5', 'UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
            if (is_string($detected) && strtoupper($detected) !== 'UTF-8') {
                $conv = @mb_convert_encoding($s, 'UTF-8', $detected);
                if (is_string($conv) && $conv !== '') {
                    $s = $conv;
                }
            } else {
                $conv = @iconv('GB18030', 'UTF-8//IGNORE', $s);
                if (is_string($conv) && $conv !== '') {
                    $s = $conv;
                }
            }
        }

        $s = trim($s);
        if ($maxLen > 0) {
            $s = mb_substr($s, 0, $maxLen);
        }

        return $s;
    }

    /**
     * 将用户输入规范为库内唯一键：小写、前导 @
     */
    public static function normalizeTiktokId(string $raw): ?string
    {
        $s = self::normalizeInputText($raw);
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
     * @return array{tiktok:int, category:?int, nickname:?int, avatar:?int, followers:?int, contact:?int, region:?int, status:?int}|null
     */
    public static function mapHeader(array $row): ?array
    {
        $norm = [];
        foreach ($row as $i => $cell) {
            $norm[$i] = self::normHeaderCell(self::normalizeInputText((string) $cell));
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

        $whatsappIdx = $find($norm, ['whatsapp', 'wa', 'whatapp']);
        $zaloIdx = $find($norm, ['zalo']);

        return [
            'tiktok' => $tikTokIdx,
            'category' => $find($norm, ['category', '分类', '类目', '分组', 'tag', '标签']),
            'nickname' => $find($norm, ['nickname', '昵称', 'name', '名称']),
            'avatar' => $find($norm, ['avatar', '头像', 'head']),
            'followers' => $find($norm, ['follower', '粉丝', 'fans']),
            'contact' => $find($norm, ['contact', '联系', 'wechat', '微信', 'line', 'phone', '手机', '电话', '邮箱', 'email']),
            'whatsapp' => $whatsappIdx,
            'zalo' => $zaloIdx,
            'region' => $find($norm, ['region', '地区', '国家', 'country']),
            'status' => $find($norm, ['status', '状态']),
        ];
    }

    private static function normHeaderCell(string $s): string
    {
        $s = mb_strtolower(self::normalizeInputText($s), 'UTF-8');

        return preg_replace('/\s+/u', '', $s) ?? $s;
    }

    /**
     * 粉丝数：支持 1.2万、12000、1,200
     */
    public static function parseFollowerCount(string $raw): int
    {
        $s = self::normalizeInputText($raw);
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
     * 0待联系 1已发私信 2已回复 3待寄样 4已寄样 5合作中 6黑名单
     */
    public static function parseStatus(string $raw): ?int
    {
        $s = self::normalizeInputText($raw);
        if ($s === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $s)) {
            $n = (int) $s;
            if ($n >= 0 && $n <= 6) {
                return $n;
            }
        }
        $l = mb_strtolower($s, 'UTF-8');
        if (str_contains($l, '黑') || str_contains($l, 'block')) {
            return 6;
        }
        if (str_contains($l, '合作') || str_contains($l, 'active')) {
            return 5;
        }
        if (str_contains($l, '私信') || str_contains($l, 'dm')) {
            return 1;
        }
        if (str_contains($l, '回复') || str_contains($l, 'reply')) {
            return 2;
        }
        if (str_contains($l, '寄样') || str_contains($l, 'sample')) {
            return 3;
        }
        if (str_contains($l, '待') || str_contains($l, 'pending') || str_contains($l, '新')) {
            return 0;
        }

        return null;
    }

    /**
     * WhatsApp / wa.me 用：仅数字，越南常见 0 开头或缺 84 时补全为 84…
     */
    public static function normalizeWhatsappNumber(string $raw): string
    {
        $s = preg_replace('/\D+/', '', self::normalizeInputText($raw)) ?? '';
        if ($s === '') {
            return '';
        }
        if (str_starts_with($s, '84')) {
            return $s;
        }
        if (str_starts_with($s, '0')) {
            return '84' . substr($s, 1);
        }
        if (strlen($s) >= 9 && strlen($s) <= 11 && ($s[0] ?? '') === '9') {
            return '84' . $s;
        }

        return $s;
    }

    /**
     * Zalo：支持 zalo.me/xxx 链接、纯数字（按手机号规范）、或 Zalo UID 字符串
     */
    public static function normalizeZaloToken(string $raw): string
    {
        $t = self::normalizeInputText($raw);
        if ($t === '') {
            return '';
        }
        // Use ~ as delimiter to avoid clash with '#' inside character class.
        if (preg_match('~^https?://(?:www\.)?zalo\.me/([^/?#\s]+)~i', $t, $m)) {
            return $m[1];
        }
        $digits = preg_replace('/\D+/', '', $t) ?? '';
        if ($digits !== '' && strlen($digits) >= 8 && $digits === preg_replace('/\D+/', '', $t)) {
            return self::normalizeWhatsappNumber($t);
        }

        return preg_replace('/\s+/u', '', $t) ?? $t;
    }

    /**
     * 从导入行提取联系方式片段（非空键才写入）
     *
     * @param array<string, int|null> $map
     * @param list<string|null> $row
     *
     * @return array<string, string>
     */
    public static function contactPartsFromImportRow(array $map, array $row): array
    {
        $parts = [];
        if (isset($map['whatsapp'], $row[$map['whatsapp']])) {
            $w = self::normalizeWhatsappNumber((string) $row[$map['whatsapp']]);
            if ($w !== '') {
                $parts['whatsapp'] = $w;
            }
        }
        if (isset($map['zalo'], $row[$map['zalo']])) {
            $z = self::normalizeZaloToken((string) $row[$map['zalo']]);
            if ($z !== '') {
                $parts['zalo'] = $z;
            }
        }
        if (isset($map['contact'], $row[$map['contact']])) {
            $raw = self::normalizeInputText((string) $row[$map['contact']]);
            if ($raw !== '') {
                if ($raw[0] === '{' || $raw[0] === '[') {
                    $j = json_decode($raw, true);
                    if (is_array($j)) {
                        foreach (['whatsapp', 'zalo', 'text', 'telegram', 'email'] as $k) {
                            if (!isset($j[$k]) || $j[$k] === '' || $j[$k] === null) {
                                continue;
                            }
                            $v = self::normalizeInputText((string) $j[$k]);
                            if ($v === '') {
                                continue;
                            }
                            if ($k === 'whatsapp') {
                                $v = self::normalizeWhatsappNumber($v);
                            } elseif ($k === 'zalo') {
                                $v = self::normalizeZaloToken($v);
                            }
                            if ($v !== '') {
                                $parts[$k] = $v;
                            }
                        }
                    }
                } else {
                    $parts['text'] = $raw;
                }
            }
        }

        return $parts;
    }

    /**
     * 合并导入/编辑产生的联系方式到已有 JSON
     *
     * @param array<string, string> $parts
     */
    public static function mergeContactPartsInto(?string $existingJson, array $parts): ?string
    {
        if ($parts === []) {
            return null;
        }
        $base = [];
        if ($existingJson !== null && trim($existingJson) !== '') {
            $j = json_decode($existingJson, true);
            if (is_array($j)) {
                $base = $j;
            }
        }
        foreach ($parts as $k => $v) {
            if ($v === '' || $v === null) {
                continue;
            }
            $base[$k] = $v;
        }
        if ($base === []) {
            return null;
        }
        $enc = json_encode($base, JSON_UNESCAPED_UNICODE);

        return $enc !== false ? $enc : null;
    }

    /**
     * 列表/API：解析 contact_info，生成打开 App 用链接
     *
     * @return array{whatsapp:string,zalo:string,telegram:string,email:string,text:string,wa_me:string,zalo_open:string}
     */
    public static function contactChannelsFromStored(?string $contactRaw): array
    {
        $out = [
            'whatsapp' => '',
            'zalo' => '',
            'telegram' => '',
            'email' => '',
            'text' => '',
            'wa_me' => '',
            'zalo_open' => '',
        ];
        if ($contactRaw === null || trim($contactRaw) === '') {
            return $out;
        }
        $j = json_decode($contactRaw, true);
        if (!is_array($j)) {
            $out['text'] = $contactRaw;

            return $out;
        }
        $out['whatsapp'] = isset($j['whatsapp']) ? trim((string) $j['whatsapp']) : '';
        $out['zalo'] = isset($j['zalo']) ? trim((string) $j['zalo']) : '';
        $out['telegram'] = isset($j['telegram']) ? trim((string) $j['telegram']) : '';
        $out['email'] = isset($j['email']) ? trim((string) $j['email']) : '';
        if (isset($j['text'])) {
            $out['text'] = trim((string) $j['text']);
        }
        if ($out['whatsapp'] !== '') {
            $out['wa_me'] = 'https://wa.me/' . $out['whatsapp'];
        }
        if ($out['zalo'] !== '') {
            $out['zalo_open'] = 'https://zalo.me/' . $out['zalo'];
        }

        return $out;
    }

    public static function contactDisplayLine(array $channels): string
    {
        if (($channels['text'] ?? '') !== '') {
            return (string) $channels['text'];
        }
        $bits = [];
        if (($channels['whatsapp'] ?? '') !== '') {
            $bits[] = 'WhatsApp';
        }
        if (($channels['zalo'] ?? '') !== '') {
            $bits[] = 'Zalo';
        }
        if (($channels['telegram'] ?? '') !== '') {
            $bits[] = 'Telegram';
        }
        if (($channels['email'] ?? '') !== '') {
            $bits[] = 'Email';
        }

        return $bits !== [] ? implode(' · ', $bits) : '';
    }

    /**
     * 后台编辑：按字段合并或清空 contact_info
     *
     * @param array<string, mixed> $payload
     */
    public static function mergeContactFromUpdatePayload(?string $existingJson, array $payload): ?string
    {
        $structured = array_key_exists('contact_whatsapp', $payload)
            || array_key_exists('contact_zalo', $payload)
            || array_key_exists('contact_note', $payload);
        if (!$structured && array_key_exists('contact_text', $payload)) {
            $t = trim((string) $payload['contact_text']);
            if ($t === '') {
                return null;
            }

            return self::normalizeContactInfo($t);
        }
        if (!$structured) {
            return $existingJson !== null && trim((string) $existingJson) !== '' ? $existingJson : null;
        }

        $base = [];
        if ($existingJson !== null && trim((string) $existingJson) !== '') {
            $j = json_decode((string) $existingJson, true);
            if (is_array($j)) {
                $base = $j;
            }
        }
        if (array_key_exists('contact_whatsapp', $payload)) {
            $v = trim((string) $payload['contact_whatsapp']);
            if ($v === '') {
                unset($base['whatsapp']);
            } else {
                $base['whatsapp'] = self::normalizeWhatsappNumber($v);
            }
        }
        if (array_key_exists('contact_zalo', $payload)) {
            $v = trim((string) $payload['contact_zalo']);
            if ($v === '') {
                unset($base['zalo']);
            } else {
                $base['zalo'] = self::normalizeZaloToken($v);
            }
        }
        if (array_key_exists('contact_note', $payload)) {
            $v = trim((string) $payload['contact_note']);
            if ($v === '') {
                unset($base['text']);
            } else {
                $base['text'] = $v;
            }
        }
        if ($base === []) {
            return null;
        }
        $enc = json_encode($base, JSON_UNESCAPED_UNICODE);

        return $enc !== false ? $enc : null;
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
     * @param array{tiktok:int, category:?int, nickname:?int, avatar:?int, followers:?int, contact:?int, region:?int, status:?int} $map
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
        $category = null;
        if (isset($map['category']) && $map['category'] !== null && isset($row[$map['category']])) {
            $c = self::normalizeInputText((string) $row[$map['category']], 64);
            $category = $c !== '' ? $c : null;
        }
        if (isset($map['nickname']) && $map['nickname'] !== null && isset($row[$map['nickname']])) {
            $nick = self::normalizeInputText((string) $row[$map['nickname']], 120);
        }
        $avatar = null;
        if (isset($map['avatar']) && $map['avatar'] !== null && isset($row[$map['avatar']])) {
            $a = self::normalizeInputText((string) $row[$map['avatar']], 1024);
            $avatar = $a !== '' ? $a : null;
        }
        $followers = 0;
        if (isset($map['followers']) && $map['followers'] !== null && isset($row[$map['followers']])) {
            $followers = self::parseFollowerCount((string) $row[$map['followers']]);
        }
        $contactParts = self::contactPartsFromImportRow($map, $row);
        $region = null;
        if (isset($map['region']) && $map['region'] !== null && isset($row[$map['region']])) {
            $r = self::normalizeInputText((string) $row[$map['region']], 64);
            $region = $r !== '' ? $r : null;
        }
        $status = 0;
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
            if ($category !== null) {
                $exists->category_name = $category;
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
            if ($contactParts !== []) {
                $merged = self::mergeContactPartsInto((string) ($exists->contact_info ?? ''), $contactParts);
                if ($merged !== null) {
                    $exists->contact_info = $merged;
                    $changed = true;
                }
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

        $newContact = $contactParts !== [] ? self::mergeContactPartsInto(null, $contactParts) : null;

        Influencer::create([
            'tiktok_id' => $handle,
            'category_name' => $category,
            'nickname' => $nick,
            'avatar_url' => $avatar,
            'follower_count' => $followers,
            'contact_info' => $newContact,
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
