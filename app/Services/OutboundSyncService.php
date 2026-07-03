<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OutboundSyncService
{
    public function fetchUnsyncList(int $vId, string $startDate, string $endDate, string $partnerCode): Response
    {
        $url = rtrim(config('services.gozwing.connect_url'), '/').'/log/unsynclist';

        $startIso = Carbon::parse($startDate)
            ->utc()
            ->startOfDay()
            ->format('Y-m-d\TH:i:s.000\Z');

        $endIso = Carbon::parse($endDate)
            ->utc()
            ->setTime(23, 59, 0)
            ->format('Y-m-d\TH:i:s.000\Z');

        return Http::timeout(60)
            ->connectTimeout(10)
            ->acceptJson()
            ->withBasicAuth(
                (string) config('services.gozwing.connect_username'),
                (string) config('services.gozwing.connect_password'),
            )
            ->post($url, [
                'v_id' => $vId,
                'startDate' => $startIso,
                'endDate' => $endIso,
                'partner_code' => $partnerCode,
            ]);
    }
}
