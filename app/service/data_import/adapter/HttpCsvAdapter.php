<?php
declare(strict_types=1);

namespace app\service\data_import\adapter;

use app\service\DataImportService;

class HttpCsvAdapter implements SourceAdapterInterface
{
    public function key(): string
    {
        return 'http_csv';
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
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headerLines),
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $content = @file_get_contents($url, false, $context);
        if (!is_string($content) || $content === '') {
            throw new \RuntimeException('adapter_fetch_failed');
        }

        $tmp = tempnam(sys_get_temp_dir(), 'ops2_csv_');
        if (!is_string($tmp) || $tmp === '') {
            throw new \RuntimeException('adapter_temp_file_failed');
        }
        try {
            file_put_contents($tmp, $content);
            $parsed = DataImportService::parseCsvFile($tmp);
            return $parsed['rows'];
        } finally {
            @unlink($tmp);
        }
    }
}

