<?php

namespace App\Services\Salesforce;

interface SalesforceSoqlClient
{
    /**
     * @return list<array<string, mixed>>
     */
    public function query(string $soql): array;
}
