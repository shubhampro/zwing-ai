<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PrintInvoiceService
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function printInvoice(string $url, string $tenantId, string $token, array $body): Response
    {
        $bearerToken = str_starts_with($token, 'Bearer ')
            ? $token
            : 'Bearer '.$token;

        return Http::timeout(60)
            ->connectTimeout(10)
            ->withHeaders([
                'accept' => 'application/json, text/plain, */*',
                'authorization' => $bearerToken,
                'content-type' => 'application/json',
                'x-tenant-id' => $tenantId,
            ])
            ->post($url, $body);
    }
}
