<?php
declare(strict_types=1);

namespace app\service\data_import;

use app\service\data_import\adapter\HttpCsvAdapter;
use app\service\data_import\adapter\HttpJsonArrayAdapter;
use app\service\data_import\adapter\MockStaticAdapter;
use app\service\data_import\adapter\SourceAdapterInterface;

class SourceAdapterRegistry
{
    /**
     * @return list<array<string, string>>
     */
    public static function options(): array
    {
        return [
            [
                'key' => 'mock_static',
                'name_i18n' => 'page.dataImportCenter.adapterMockStatic',
                'desc_i18n' => 'page.dataImportCenter.adapterMockStaticDesc',
            ],
            [
                'key' => 'http_json_array',
                'name_i18n' => 'page.dataImportCenter.adapterHttpJson',
                'desc_i18n' => 'page.dataImportCenter.adapterHttpJsonDesc',
            ],
            [
                'key' => 'http_csv',
                'name_i18n' => 'page.dataImportCenter.adapterHttpCsv',
                'desc_i18n' => 'page.dataImportCenter.adapterHttpCsvDesc',
            ],
        ];
    }

    public static function build(string $key): SourceAdapterInterface
    {
        $normalized = mb_strtolower(trim($key), 'UTF-8');
        if ($normalized === 'mock_static') {
            return new MockStaticAdapter();
        }
        if ($normalized === 'http_json_array') {
            return new HttpJsonArrayAdapter();
        }
        if ($normalized === 'http_csv') {
            return new HttpCsvAdapter();
        }
        throw new \InvalidArgumentException('unknown_adapter');
    }
}

