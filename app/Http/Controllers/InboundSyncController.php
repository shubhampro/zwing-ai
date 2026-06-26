<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckInboundSyncRequest;
use App\Models\Organization;
use App\Services\ErpToZwing\InboundSyncQueryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InboundSyncController extends Controller
{
    public function index(Request $request, InboundSyncQueryService $queryService): Response
    {
        abort_if($request->user() === null, 403);

        $organizations = Organization::orderBy('name')
            ->get(['id', 'name', 'ba_code', 'vendor_id'])
            ->map(fn (Organization $org) => [
                'id' => $org->id,
                'label' => "{$org->name} ({$org->ba_code})",
                'vendor_id' => $org->vendor_id,
                'client_id' => $org->ba_code,
            ]);

        return Inertia::render('inbound-sync/index', [
            'organizations' => $organizations,
            'defaultStartDate' => now()->subDays(30)->startOfDay()->format('Y-m-d\TH:i'),
            'defaultEndDate' => now()->endOfDay()->format('Y-m-d\TH:i'),
            'mongoConfigured' => $queryService->isConfigured(),
        ]);
    }

    public function check(CheckInboundSyncRequest $request, InboundSyncQueryService $queryService): JsonResponse
    {
        abort_if($request->user() === null, 403);

        if (! $queryService->isConfigured()) {
            return response()->json([
                'message' => 'MongoDB is not configured. Set MONGODB_SSH_DATABASE (or MONGO_DB_DATABASE) in .env.',
            ], 503);
        }

        $validated = $request->validated();
        $clientId = trim((string) ($validated['client_id'] ?? ''));
        $clientEventName = trim((string) ($validated['client_event_name'] ?? ''));
        $clientEventUniqueCode = trim((string) ($validated['client_event_unique_code'] ?? ''));

        try {
            $payload = $queryService->fetch(
                vendorId: (int) $validated['v_id'],
                startDate: Carbon::parse($validated['start_date'])->utc(),
                endDate: Carbon::parse($validated['end_date'])->utc()->endOfMinute(),
                clientId: $clientId !== '' ? $clientId : null,
                clientEventName: $clientEventName !== '' ? $clientEventName : null,
                clientEventUniqueCode: $clientEventUniqueCode !== '' ? $clientEventUniqueCode : null,
            );
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }

        $result = collect($payload['result'])
            ->when($clientEventName !== '', fn ($rows) => $rows->filter(
                fn (array $row) => Str::lower((string) ($row['name'] ?? '')) === Str::lower($clientEventName)
            ))
            ->map(function (array $row): array {
                $needToSync = is_array($row['need_to_sync'] ?? null) ? $row['need_to_sync'] : [];
                $eventMiss = is_array($row['event_miss'] ?? null) ? $row['event_miss'] : [];
                $pending = (int) ($row['pending'] ?? 0);
                $failCnt = (int) ($row['fail_cnt'] ?? 0);
                $trans = (int) ($row['trans'] ?? 0);

                return [
                    'name' => (string) ($row['name'] ?? 'unknown'),
                    'trans' => $trans,
                    'val' => (int) ($row['val'] ?? 0),
                    'fail_cnt' => $failCnt,
                    'pending' => $pending,
                    'success_sync' => $row['success_sync'] ?? 0,
                    'need_to_sync' => $needToSync,
                    'event_miss' => $eventMiss,
                    'failed_details' => is_array($row['failed_details'] ?? null) ? $row['failed_details'] : [],
                    'pending_details' => is_array($row['pending_details'] ?? null) ? $row['pending_details'] : [],
                    'need_to_sync_count' => $failCnt,
                    'event_miss_count' => $pending,
                    'status' => $this->resolveStatus($trans, $pending, $failCnt, $needToSync, $eventMiss),
                ];
            })
            ->values()
            ->all();

        $stats = $clientEventName !== '' && count($result) === 1
            ? $this->statsFromResult($result[0])
            : $payload['stats'];

        return response()->json([
            'filters' => [
                'v_id' => (int) $validated['v_id'],
                'client_id' => $clientId !== '' ? $clientId : null,
                'client_event_name' => $clientEventName !== '' ? $clientEventName : null,
                'client_event_unique_code' => $clientEventUniqueCode !== '' ? $clientEventUniqueCode : null,
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
}
