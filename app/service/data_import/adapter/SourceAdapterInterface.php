<?php
declare(strict_types=1);

namespace app\service\data_import\adapter;

interface SourceAdapterInterface
{
    public function key(): string;

    /**
     * @param array<string, mixed> $config
     * @return list<array<string, string>>
     */
    public function fetchRows(array $config, string $domain): array;
}

