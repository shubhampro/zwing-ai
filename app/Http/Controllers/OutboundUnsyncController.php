<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckOutboundUnsyncRequest;
use App\Models\Organization;
use App\Services\ZwingToErp\OutboundUnsyncClient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class OutboundUnsyncController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $organizations = Organization::orderBy('name')
            ->get(['id', 'name', 'ba_code', 'vendor_id'])
            ->map(fn (Organization $org) => [
                'id' => $org->id,
                'label' => "{$org->name} ({$org->ba_code})",
                'vendor_id' => $org->vendor_id,
                'partner_code' => $org->ba_code,
            ]);

        return Inertia::render('outbound-unsync/index', [
            'organizations' => $organizations,
            'defaultStartDate' => now()->subDays(30)->startOfDay()->format('Y-m-d\TH:i'),
            'defaultEndDate' => now()->endOfDay()->format('Y-m-d\TH:i'),
            'defaultPartnerCode' => 'VT2345RTZW87',
            'apiConfigured' => $this->isApiConfigured(),
        ]);
    }

    public function check(CheckOutboundUnsyncRequest $request, OutboundUnsyncClient $client): JsonResponse
    {
        abort_if($request->user() === null, 403);

        if (! $this->isApiConfigured()) {
            return response()->json([
                'message' => 'ZWING To ERP API is not configured. Set ZWING_TO_ERP_BASE_URL, ZWING_TO_ERP_USERNAME, and ZWING_TO_ERP_PASSWORD in .env.',
            ], 503);
        }

        $validated = $request->validated();
        $eventName = trim((string) ($validated['event_name'] ?? ''));

        $startDate = Carbon::parse($validated['start_date'])->utc()->format('Y-m-d\TH:i:s.000\Z');
        $endDate = Carbon::parse($validated['end_date'])->utc()->format('Y-m-d\TH:i:s.000\Z');

        try {
            $payload = $client->fetch(
                vendorId: (int) $validated['v_id'],
                partnerCode: $validated['partner_code'],
                startDate: $startDate,
                endDate: $endDate,
                eventName: $eventName !== '' ? $eventName : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $result = collect($payload['result'])
            ->when($eventName !== '', fn ($rows) => $rows->filter(
                fn (array $row) => Str::lower((string) ($row['name'] ?? '')) === Str::lower($eventName)
            ))
            ->map(function (array $row): array {
                $needToSync = is_array($row['needToSync'] ?? null) ? $row['needToSync'] : [];
                $eventMiss = is_array($row['eventMiss'] ?? null) ? $row['eventMiss'] : [];
                $pending = (int) ($row['pending'] ?? 0);
                $failCnt = (int) ($row['failCnt'] ?? 0);
                $trans = (int) ($row['trans'] ?? 0);

                return [
                    'name' => (string) ($row['name'] ?? 'unknown'),
                    'trans' => $trans,
                    'val' => (int) ($row['val'] ?? 0),
                    'fail_cnt' => $failCnt,
                    'pending' => $pending,
                    'success_sync' => $row['successSync'] ?? 0,
                    'need_to_sync' => $needToSync,
                    'event_miss' => $eventMiss,
                    'need_to_sync_count' => count($needToSync),
                    'event_miss_count' => count($eventMiss),
                    'status' => $this->resolveStatus($trans, $pending, $failCnt, $needToSync, $eventMiss),
                ];
            })
            ->values()
            ->all();

        $stats = $eventName !== '' && count($result) === 1
            ? $this->statsFromResult($result[0])
            : [
                'total_sync' => (int) ($payload['stats']['totalSync'] ?? 0),
                'success_sync' => (int) ($payload['stats']['sucessSync'] ?? 0),
                'pending' => (int) ($payload['stats']['pending'] ?? 0),
                'remain_sync' => (int) ($payload['stats']['remain_sync'] ?? 0),
                'sync_percentage' => (float) ($payload['stats']['total_sync'] ?? 0),
            ];

        return response()->json([
            'filters' => [
                'v_id' => (int) $validated['v_id'],
                'partner_code' => $validated['partner_code'],
                'event_name' => $eventName !== '' ? $eventName : null,
            ],
            'date_range' => [
                'start' => $validated['start_date'],
                'end' => $validated['end_date'],
            ],
            'stats' => $stats,
            'result' => $result,
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{total_sync: int, success_sync: int, pending: int, remain_sync: int, sync_percentage: float}
     */
    private function statsFromResult(array $row): array
    {
        $total = (int) ($row['trans'] ?? 0);
        $successSync = is_array($row['success_sync'] ?? null)
            ? count($row['success_sync'])
            : (int) ($row['success_sync'] ?? 0);
        $pending = (int) ($row['pending'] ?? 0);

        return [
            'total_sync' => $total,
            'success_sync' => $successSync,
            'pending' => $pending,
            'remain_sync' => max($total - $successSync, 0),
            'sync_percentage' => $total > 0 ? round(($successSync / $total) * 100, 2) : 0.0,
        ];
    }

    /**
     * @param  array<int, mixed>  $needToSync
     * @param  array<int, mixed>  $eventMiss
     */
    private function resolveStatus(int $trans, int $pending, int $failCnt, array $needToSync, array $eventMiss): string
    {
        if ($trans === 0) {
            return 'idle';
        }

        if ($failCnt > 0 || count($needToSync) > 0) {
            return 'failed';
        }

        if ($pending > 0 || count($eventMiss) > 0) {
            return 'pending';
        }

        return 'synced';
    }

    private function isApiConfigured(): bool
    {
        return config('services.zwing_to_erp.base_url') !== null
            && config('services.zwing_to_erp.base_url') !== ''
            && config('services.zwing_to_erp.username') !== null
            && config('services.zwing_to_erp.username') !== ''
            && config('services.zwing_to_erp.password') !== null
            && config('services.zwing_to_erp.password') !== '';
    }
}
