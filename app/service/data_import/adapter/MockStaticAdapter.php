<?php
declare(strict_types=1);

namespace app\service\data_import\adapter;

class MockStaticAdapter implements SourceAdapterInterface
{
    public function key(): string
    {
        return 'mock_static';
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, string>>
     */
    public function fetchRows(array $config, string $domain): array
    {
        $rows = $config['rows'] ?? [];
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
}

