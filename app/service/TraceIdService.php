<?php
declare(strict_types=1);

namespace app\service;

use think\Request;

class TraceIdService
{
    /**
     * @var array<int, string>
     */
    private static array $traceMap = [];

    public static function ensure(?Request $request = null): string
    {
        if ($request !== null) {
            $requestKey = spl_object_id($request);
            if (!empty(self::$traceMap[$requestKey])) {
                return self::$traceMap[$requestKey];
            }

            $headerTrace = self::normalizeTrace((string) $request->header('x-trace-id', ''));
            if ($headerTrace !== '') {
                self::$traceMap[$requestKey] = $headerTrace;
                return $headerTrace;
            }

            $generated = self::generateTraceId();
            self::$traceMap[$requestKey] = $generated;
            return $generated;
        }

        $globalKey = 0;
        if (!empty(self::$traceMap[$globalKey])) {
            return self::$traceMap[$globalKey];
        }
        $generated = self::generateTraceId();
        self::$traceMap[$globalKey] = $generated;
        return $generated;
    }

    public static function clear(?Request $request = null): void
    {
        if ($request !== null) {
            unset(self::$traceMap[spl_object_id($request)]);
            return;
        }

        self::$traceMap = [];
    }

    private static function normalizeTrace(string $trace): string
    {
        $trace = trim($trace);
        if ($trace === '') {
            return '';
        }
        if (strlen($trace) < 8 || strlen($trace) > 96) {
            return '';
        }
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $trace)) {
            return '';
        }

        return $trace;
    }

    private static function generateTraceId(): string
    {
        try {
            $random = bin2hex(random_bytes(8));
        } catch (\Throwable $e) {
            $random = substr(md5((string) microtime(true) . mt_rand()), 0, 16);
        }

        return gmdate('YmdHis') . '-' . $random;
    }
}

