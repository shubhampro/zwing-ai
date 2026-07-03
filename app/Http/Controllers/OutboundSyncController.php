<?php

namespace App\Http\Controllers;

use App\Services\OutboundSyncService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OutboundSyncController extends Controller
{
    /**
     * @return array{v_id: int, partner_code: string, start_date: string, end_date: string}
     */
    private static function defaultFilters(): array
    {
        $endDate = Carbon::today();

        return [
            'v_id' => 266,
            'partner_code' => 'VT2345RTZW87',
            'start_date' => $endDate->copy()->subDays(7)->toDateString(),
            'end_date' => $endDate->toDateString(),
        ];
    }

    public function index(): Response
    {
        abort_if(auth()->user() === null, 403);

        return Inertia::render('outbound-sync/index', [
            'defaultFilters' => self::defaultFilters(),
        ]);
    }

    public function fetch(Request $request, OutboundSyncService $outboundSyncService): JsonResponse
    {
        abort_if($request->user() === null, 403);

        $validated = $request->validate([
            'v_id' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'partner_code' => ['required', 'string', 'max:255'],
        ]);

        $response = $outboundSyncService->fetchUnsyncList(
            $validated['v_id'],
            $validated['start_date'],
            $validated['end_date'],
            $validated['partner_code'],
        );

        if (! $response->successful()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch outbound sync data from Connect API.',
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ], 502);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Unexpected response from Connect API.',
            ], 502);
        }

        return response()->json([
            'success' => (bool) ($payload['success'] ?? false),
            'result' => $payload['result'] ?? [],
            'stats' => $payload['stats'] ?? null,
        ]);
    }
}
