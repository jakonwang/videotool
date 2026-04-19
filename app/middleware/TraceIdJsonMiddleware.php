<?php
declare(strict_types=1);

namespace app\middleware;

use app\service\TraceIdService;
use Closure;
use think\Request;
use think\Response;

class TraceIdJsonMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $traceId = TraceIdService::ensure($request);
        $response = $next($request);

        if ($response instanceof Response) {
            $response->header(['X-Trace-Id' => $traceId]);
            $this->patchJsonResponse($response, $traceId);
        }

        TraceIdService::clear($request);
        return $response;
    }

    private function patchJsonResponse(Response $response, string $traceId): void
    {
        if (!$this->shouldTreatAsJson($response)) {
            return;
        }

        $raw = (string) $response->getContent();
        if ($raw === '') {
            return;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return;
        }

        if (!array_key_exists('trace_id', $decoded) || trim((string) ($decoded['trace_id'] ?? '')) === '') {
            $decoded['trace_id'] = $traceId;
        }

        $code = (int) ($decoded['code'] ?? 0);
        if ($code !== 0 && trim((string) ($decoded['error_key'] ?? '')) === '') {
            $decoded['error_key'] = $this->inferErrorKey((string) ($decoded['msg'] ?? ''), $code);
        }

        $response->content((string) json_encode($decoded, JSON_UNESCAPED_UNICODE));
    }

    private function shouldTreatAsJson(Response $response): bool
    {
        $contentType = strtolower((string) $response->getHeader('Content-Type'));
        if (str_contains($contentType, 'application/json')) {
            return true;
        }
        if (str_contains($contentType, 'text/json')) {
            return true;
        }

        $raw = trim((string) $response->getContent());
        return $raw !== '' && ($raw[0] === '{' || $raw[0] === '[');
    }

    private function inferErrorKey(string $msg, int $code): string
    {
        if ($code === 401) {
            return 'common.sessionExpired';
        }
        if ($code === 403) {
            return 'common.forbidden';
        }

        $text = strtolower(trim($msg));
        if ($text === '') {
            return '';
        }

        $contains = static function (string $needle) use ($text): bool {
            return $needle !== '' && str_contains($text, strtolower($needle));
        };

        if ($contains('only_post')) {
            return 'common.onlyPost';
        }
        if ($contains('not_logged_in') || $contains('session_expired') || $contains('unauthorized')) {
            return 'common.sessionExpired';
        }
        if ($contains('forbidden')) {
            return 'common.forbidden';
        }
        if ($contains('invalid') || $contains('param')) {
            return 'common.invalidParams';
        }
        if ($contains('not_found')) {
            return 'common.notFound';
        }

        return '';
    }
}

