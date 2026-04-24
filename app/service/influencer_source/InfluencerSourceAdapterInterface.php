<?php
declare(strict_types=1);

namespace app\service\influencer_source;

interface InfluencerSourceAdapterInterface
{
    /**
     * @param array<string, mixed> $options
     * @return array{headers:list<string>,rows:list<list<string>>}
     */
    public function parseRows(string $absPath, array $options = []): array;
}

