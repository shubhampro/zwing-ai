<?php

namespace App\Services\Salesforce;

class SfMbrSummaryFilter
{
    /**
     * @param  list<int>|null  $applicationIds
     * @param  list<int>|null  $moduleIds
     */
    public function __construct(
        public readonly ?array $applicationIds,
        public readonly ?array $moduleIds,
        public readonly bool $includeMissingModule,
    ) {}

    public static function all(): self
    {
        return new self(null, null, true);
    }

    public function isNarrowed(): bool
    {
        return $this->applicationIds !== null || $this->moduleIds !== null;
    }
}
