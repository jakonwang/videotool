<?php
declare(strict_types=1);

namespace app\service\data_import\adapter;

class HttpJsonArrayAdapter implements SourceAdapterInterface
{
    public function key(): string
    {
        return 'http_json_array';
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, string>>
     */
    public function fetchRows(array $config, string $domain): array
    {
        $url = trim((string) ($config['url'] ?? ''));
        if ($url === '') {
            throw new \RuntimeException('adapter_url_required');
        }
        $method = strtoupper(trim((string) ($config['method'] ?? 'GET')));
        if (!in_array($method, ['GET', 'POST'], true)) {
            $method = 'GET';
        }
        $timeout = (int) ($config['timeout'] ?? 20);
        if ($timeout <= 0) {
            $timeout = 20;
        }
        if ($timeout > 120) {
            $timeout = 120;
        }

        $headers = $config['headers'] ?? [];
        $headerLines = [];
        if (is_array($headers)) {
            foreach ($headers as $k => $v) {
                $key = trim((string) $k);
                if ($key === '') {
                    continue;
                }
                $headerLines[] = $key . ': ' . trim((string) $v);
            }
        }
        $body = $config['body'] ?? null;
        $content = '';
        if ($method === 'POST') {
            if (is_array($body)) {
                $content = json_encode($body, JSON_UNESCAPED_UNICODE) ?: '';
                $hasContentType = false;
                foreach ($headerLines as $line) {
                    if (stripos($line, 'Content-Type:') === 0) {
                        $hasContentType = true;
                        break;
                    }
                }
                if (!$hasContentType) {
                    $headerLines[] = 'Content-Type: application/json; charset=UTF-8';
                }
            } else {
                $content = trim((string) $body);
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headerLines),
                'content' => $content,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            throw new \RuntimeException('adapter_fetch_failed');
        }
        $json = json_decode($raw, true);
        if (!is_array($json)) {
            throw new \RuntimeException('adapter_non_json');
        }

        $path = trim((string) ($config['rows_path'] ?? 'data.rows'));
        $rows = $this->pickRowsByPath($json, $path);
        if (!is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $item = [];
            foreach ($row as $k => $v) {
                $item[(string) $k] = trim((string) $v);
            }
            if ($item !== []) {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $json
     * @return mixed
     */
    private function pickRowsByPath(array $json, string $path)
    {
        if ($path === '') {
            return $json;
        }
        $cursor = $json;
        $parts = preg_split('/\./', $path) ?: [];
        foreach ($parts as $part) {
            $key = trim((string) $part);
            if ($key === '') {
                continue;
            }
            if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
                return [];
            }
            $cursor = $cursor[$key];
        }
        return $cursor;
    }
}

