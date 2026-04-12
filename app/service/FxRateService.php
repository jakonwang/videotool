<?php
declare(strict_types=1);

namespace app\service;

use GuzzleHttp\Client;
use think\facade\Db;

class FxRateService
{
    public const BASE_CURRENCY = 'CNY';
    public const STATUS_EXACT = 'exact';
    public const STATUS_FALLBACK_LATEST = 'fallback_latest';
    public const STATUS_IDENTITY = 'identity';
    public const STATUS_MISSING = 'missing';

    /**
     * @var array<string, bool>
     */
    private static array $tableExistsCache = [];

    public static function todayDate(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Bangkok')))->format('Y-m-d');
    }

    public static function normalizeDate(string $date): string
    {
        $raw = trim($date);
        if ($raw === '') {
            return self::todayDate();
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return self::todayDate();
        }

        return date('Y-m-d', $ts);
    }

    public static function normalizeCurrency(string $currency): string
    {
        $raw = strtoupper(trim($currency));
        if ($raw === '') {
            return self::BASE_CURRENCY;
        }
        if (!preg_match('/^[A-Z]{3}$/', $raw)) {
            return self::BASE_CURRENCY;
        }

        return $raw;
    }

    /**
     * @return string[]
     */
    public static function supportedCurrencies(): array
    {
        return ['CNY', 'USD', 'VND'];
    }

    /**
     * @param string[] $currencies
     * @return array<int, array<string,mixed>>
     */
    public static function syncRatesForDate(string $rateDate, array $currencies, int $tenantId): array
    {
        $date = self::normalizeDate($rateDate);
        $tenantId = max(1, $tenantId);
        $results = [];
        $normalized = [];
        foreach ($currencies as $currency) {
            $cur = self::normalizeCurrency((string) $currency);
            if (!in_array($cur, self::supportedCurrencies(), true)) {
                continue;
            }
            $normalized[$cur] = true;
        }
        if ($normalized === []) {
            $normalized = ['USD' => true, 'VND' => true];
        }

        foreach (array_keys($normalized) as $currency) {
            $found = self::resolveRateToCny($currency, $date, $tenantId, true);
            if ($found === null) {
                $results[] = [
                    'rate_date' => $date,
                    'from_currency' => $currency,
                    'to_currency' => self::BASE_CURRENCY,
                    'rate' => 0.0,
                    'source' => '',
                    'status' => self::STATUS_MISSING,
                    'is_fallback' => 1,
                ];
                continue;
            }
            $results[] = $found;
        }

        return $results;
    }

    /**
     * @return array<string,mixed>
     */
    public static function convertToCny(float $amount, string $currency, string $rateDate, int $tenantId): array
    {
        $cur = self::normalizeCurrency($currency);
        $date = self::normalizeDate($rateDate);
        $tenantId = max(1, $tenantId);
        $amt = max(0.0, (float) $amount);

        if ($cur === self::BASE_CURRENCY) {
            return [
                'amount' => round($amt, 2),
                'currency' => $cur,
                'rate_date' => $date,
                'rate' => 1.0,
                'amount_cny' => round($amt, 2),
                'source' => 'identity',
                'status' => self::STATUS_IDENTITY,
                'is_fallback' => 0,
            ];
        }

        $rateRow = self::resolveRateToCny($cur, $date, $tenantId, true);
        if ($rateRow === null || (float) ($rateRow['rate'] ?? 0) <= 0) {
            return [
                'amount' => round($amt, 2),
                'currency' => $cur,
                'rate_date' => $date,
                'rate' => 0.0,
                'amount_cny' => 0.0,
                'source' => '',
                'status' => self::STATUS_MISSING,
                'is_fallback' => 1,
            ];
        }

        $rate = (float) $rateRow['rate'];
        return [
            'amount' => round($amt, 2),
            'currency' => $cur,
            'rate_date' => (string) ($rateRow['rate_date'] ?? $date),
            'rate' => $rate,
            'amount_cny' => round($amt * $rate, 2),
            'source' => (string) ($rateRow['source'] ?? ''),
            'status' => (string) ($rateRow['status'] ?? self::STATUS_EXACT),
            'is_fallback' => (int) ($rateRow['is_fallback'] ?? 0),
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function resolveRateToCny(string $fromCurrency, string $rateDate, int $tenantId, bool $allowRemoteFetch = true): ?array
    {
        $from = self::normalizeCurrency($fromCurrency);
        $date = self::normalizeDate($rateDate);
        $tenantId = max(1, $tenantId);

        if ($from === self::BASE_CURRENCY) {
            return [
                'rate_date' => $date,
                'from_currency' => $from,
                'to_currency' => self::BASE_CURRENCY,
                'rate' => 1.0,
                'source' => 'identity',
                'status' => self::STATUS_IDENTITY,
                'is_fallback' => 0,
            ];
        }

        $exact = self::findExactRate($tenantId, $date, $from, self::BASE_CURRENCY);
        if ($exact !== null) {
            $exact['status'] = ((int) ($exact['is_fallback'] ?? 0) === 1) ? self::STATUS_FALLBACK_LATEST : self::STATUS_EXACT;
            return $exact;
        }

        if ($allowRemoteFetch) {
            $remote = self::fetchFromCurrencyApi($from, $date);
            if ($remote['ok']) {
                self::upsertRate(
                    $tenantId,
                    $date,
                    $from,
                    self::BASE_CURRENCY,
                    (float) $remote['rate'],
                    (string) $remote['source'],
                    0,
                    ['provider_date' => $remote['provider_date'] ?? $date]
                );

                return [
                    'rate_date' => $date,
                    'from_currency' => $from,
                    'to_currency' => self::BASE_CURRENCY,
                    'rate' => (float) $remote['rate'],
                    'source' => (string) $remote['source'],
                    'status' => self::STATUS_EXACT,
                    'is_fallback' => 0,
                ];
            }

            // Fallback to latest when exact-date provider fetch failed.
            $remoteLatest = self::fetchFromCurrencyApi($from, 'latest');
            if ($remoteLatest['ok']) {
                self::upsertRate(
                    $tenantId,
                    $date,
                    $from,
                    self::BASE_CURRENCY,
                    (float) $remoteLatest['rate'],
                    (string) $remoteLatest['source'],
                    1,
                    ['provider_date' => $remoteLatest['provider_date'] ?? self::todayDate(), 'fallback' => 'latest']
                );

                return [
                    'rate_date' => $date,
                    'from_currency' => $from,
                    'to_currency' => self::BASE_CURRENCY,
                    'rate' => (float) $remoteLatest['rate'],
                    'source' => (string) $remoteLatest['source'],
                    'status' => self::STATUS_FALLBACK_LATEST,
                    'is_fallback' => 1,
                ];
            }

            $openEr = self::fetchFromOpenErApiLatest($from);
            if ($openEr['ok']) {
                self::upsertRate(
                    $tenantId,
                    $date,
                    $from,
                    self::BASE_CURRENCY,
                    (float) $openEr['rate'],
                    (string) $openEr['source'],
                    1,
                    ['provider_date' => self::todayDate(), 'fallback' => 'open_er_latest']
                );

                return [
                    'rate_date' => $date,
                    'from_currency' => $from,
                    'to_currency' => self::BASE_CURRENCY,
                    'rate' => (float) $openEr['rate'],
                    'source' => (string) $openEr['source'],
                    'status' => self::STATUS_FALLBACK_LATEST,
                    'is_fallback' => 1,
                ];
            }

            $fxRatesApi = self::fetchFromFxRatesApiLatest($from);
            if ($fxRatesApi['ok']) {
                self::upsertRate(
                    $tenantId,
                    $date,
                    $from,
                    self::BASE_CURRENCY,
                    (float) $fxRatesApi['rate'],
                    (string) $fxRatesApi['source'],
                    1,
                    ['provider_date' => self::todayDate(), 'fallback' => 'fxratesapi_latest']
                );

                return [
                    'rate_date' => $date,
                    'from_currency' => $from,
                    'to_currency' => self::BASE_CURRENCY,
                    'rate' => (float) $fxRatesApi['rate'],
                    'source' => (string) $fxRatesApi['source'],
                    'status' => self::STATUS_FALLBACK_LATEST,
                    'is_fallback' => 1,
                ];
            }
        }

        $latest = self::findLatestRate($tenantId, $from, self::BASE_CURRENCY);
        if ($latest !== null) {
            $latest['status'] = self::STATUS_FALLBACK_LATEST;
            $latest['is_fallback'] = 1;
            return $latest;
        }

        return null;
    }

    /**
     * @return array{ok:bool,rate?:float,source?:string,provider_date?:string,error?:string}
     */
    private static function fetchFromCurrencyApi(string $fromCurrency, string $dateOrLatest): array
    {
        $tag = trim($dateOrLatest) === '' ? 'latest' : trim($dateOrLatest);
        $url = 'https://cdn.jsdelivr.net/npm/@fawazahmed0/currency-api@'
            . rawurlencode($tag)
            . '/v1/currencies/cny.json';

        $payload = self::requestJson($url);
        if (!($payload['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($payload['error'] ?? 'request_failed')];
        }
        $json = $payload['json'] ?? null;
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }
        $base = $json['cny'] ?? null;
        if (!is_array($base)) {
            return ['ok' => false, 'error' => 'missing_cny_key'];
        }
        $key = strtolower($fromCurrency);
        $cnyToFrom = (float) ($base[$key] ?? 0.0);
        if ($cnyToFrom <= 0.0) {
            return ['ok' => false, 'error' => 'rate_missing'];
        }
        $rate = 1.0 / $cnyToFrom;
        return [
            'ok' => true,
            'rate' => $rate,
            'source' => 'currency_api_jsdelivr',
            'provider_date' => (string) ($json['date'] ?? ''),
        ];
    }

    /**
     * @return array{ok:bool,rate?:float,source?:string,error?:string}
     */
    private static function fetchFromOpenErApiLatest(string $fromCurrency): array
    {
        $url = 'https://open.er-api.com/v6/latest/' . rawurlencode($fromCurrency);
        $payload = self::requestJson($url);
        if (!($payload['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($payload['error'] ?? 'request_failed')];
        }
        $json = $payload['json'] ?? null;
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }
        $rate = (float) (($json['rates'][self::BASE_CURRENCY] ?? 0.0));
        if ($rate <= 0.0) {
            return ['ok' => false, 'error' => 'rate_missing'];
        }
        return ['ok' => true, 'rate' => $rate, 'source' => 'open_er_api_latest'];
    }

    /**
     * @return array{ok:bool,rate?:float,source?:string,error?:string}
     */
    private static function fetchFromFxRatesApiLatest(string $fromCurrency): array
    {
        $url = 'https://api.fxratesapi.com/latest?base='
            . rawurlencode($fromCurrency)
            . '&currencies='
            . rawurlencode(self::BASE_CURRENCY);
        $payload = self::requestJson($url);
        if (!($payload['ok'] ?? false)) {
            return ['ok' => false, 'error' => (string) ($payload['error'] ?? 'request_failed')];
        }
        $json = $payload['json'] ?? null;
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }
        if (!((bool) ($json['success'] ?? true))) {
            return ['ok' => false, 'error' => 'provider_failed'];
        }
        $rate = (float) (($json['rates'][self::BASE_CURRENCY] ?? 0.0));
        if ($rate <= 0.0) {
            return ['ok' => false, 'error' => 'rate_missing'];
        }
        return ['ok' => true, 'rate' => $rate, 'source' => 'fxratesapi_latest'];
    }

    /**
     * @return array{ok:bool,json?:array<string,mixed>,error?:string}
     */
    private static function requestJson(string $url): array
    {
        $target = trim($url);
        if ($target === '') {
            return ['ok' => false, 'error' => 'empty_url'];
        }

        try {
            $client = new Client(['timeout' => 8, 'connect_timeout' => 5]);
            $resp = $client->get($target, [
                'headers' => [
                    'Accept' => 'application/json',
                    'User-Agent' => 'TikStarOPS-FX/1.0',
                ],
            ]);
            $json = json_decode((string) $resp->getBody(), true);
            if (is_array($json)) {
                return ['ok' => true, 'json' => $json];
            }
        } catch (\Throwable $e) {
            // Fallback to stream fetch for Windows environments where cURL CA chain is missing.
        }

        if (!function_exists('file_get_contents')) {
            return ['ok' => false, 'error' => 'stream_unavailable'];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 8,
                'header' => "Accept: application/json\r\nUser-Agent: TikStarOPS-FX/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);
        $raw = @file_get_contents($target, false, $context);
        if ($raw === false || $raw === '') {
            $err = error_get_last();
            return ['ok' => false, 'error' => (string) ($err['message'] ?? 'stream_fetch_failed')];
        }
        $json = json_decode((string) $raw, true);
        if (!is_array($json)) {
            return ['ok' => false, 'error' => 'invalid_json'];
        }
        return ['ok' => true, 'json' => $json];
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function findExactRate(int $tenantId, string $rateDate, string $fromCurrency, string $toCurrency): ?array
    {
        if (!self::tableExists('growth_fx_rates')) {
            return null;
        }
        try {
            $row = Db::name('growth_fx_rates')
                ->where('tenant_id', $tenantId)
                ->where('rate_date', $rateDate)
                ->where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->find();
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private static function findLatestRate(int $tenantId, string $fromCurrency, string $toCurrency): ?array
    {
        if (!self::tableExists('growth_fx_rates')) {
            return null;
        }
        try {
            $row = Db::name('growth_fx_rates')
                ->where('tenant_id', $tenantId)
                ->where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->order('rate_date', 'desc')
                ->order('id', 'desc')
                ->find();
            return is_array($row) ? $row : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $meta
     */
    private static function upsertRate(
        int $tenantId,
        string $rateDate,
        string $fromCurrency,
        string $toCurrency,
        float $rate,
        string $source,
        int $isFallback,
        array $meta = []
    ): void {
        if (!self::tableExists('growth_fx_rates') || $rate <= 0.0) {
            return;
        }
        try {
            $exists = Db::name('growth_fx_rates')
                ->where('tenant_id', $tenantId)
                ->where('rate_date', $rateDate)
                ->where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->find();
            $payload = [
                'tenant_id' => $tenantId,
                'rate_date' => $rateDate,
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
                'rate' => $rate,
                'source' => mb_substr(trim($source), 0, 64),
                'is_fallback' => $isFallback === 1 ? 1 : 0,
                'meta_json' => $meta !== [] ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($exists) {
                Db::name('growth_fx_rates')->where('id', (int) ($exists['id'] ?? 0))->update($payload);
                return;
            }
            $payload['created_at'] = date('Y-m-d H:i:s');
            Db::name('growth_fx_rates')->insert($payload);
        } catch (\Throwable $e) {
            // Ignore rate cache write errors, do not block业务流程.
        }
    }

    private static function tableExists(string $table): bool
    {
        $name = strtolower(trim($table));
        if ($name === '') {
            return false;
        }
        if (array_key_exists($name, self::$tableExistsCache)) {
            return self::$tableExistsCache[$name];
        }
        try {
            Db::name($name)->where('id', 0)->find();
            self::$tableExistsCache[$name] = true;
        } catch (\Throwable $e) {
            self::$tableExistsCache[$name] = false;
        }

        return self::$tableExistsCache[$name];
    }
}
