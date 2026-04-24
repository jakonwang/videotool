<?php
declare(strict_types=1);

namespace app\service\influencer_source;

class InfluencerSourceAdapterFactory
{
    public static function make(string $sourceSystem): InfluencerSourceAdapterInterface
    {
        $key = strtolower(trim($sourceSystem));
        if ($key === '' || $key === 'dami') {
            return new CsvInfluencerSourceAdapter();
        }

        throw new \RuntimeException('unsupported_source_system');
    }
}

