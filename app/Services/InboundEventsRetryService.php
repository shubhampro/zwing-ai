<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class InboundEventsRetryService
{
    /** @var list<string> */
    public const QUEUE_NAME_LIST = [
        'Item',
        'Advice',
        'SupplyPriceBook',
        'Grn',
        'Grt',
        'Category',
        'TaxCategory',
        'TaxGroup',
        'Supplier',
        'Batch',
        'Serial',
    ];

    public function retry(string $logId): Response
    {
        $url = rtrim(config('services.gozwing.connect_url'), '/').'/inbound/retry';

        return Http::timeout(60)
            ->connectTimeout(10)
            ->acceptJson()
            ->post($url, [
                'log_id' => $logId,
                'queue_name_list' => self::QUEUE_NAME_LIST,
            ]);
    }
}
