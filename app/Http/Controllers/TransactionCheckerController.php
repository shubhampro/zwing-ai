<?php

namespace App\Http\Controllers;

use App\Enums\ExternalQueryJobType;
use App\Models\Organization;
use App\Models\TransactionCheckerSession;
use App\Services\ExternalQueryDispatcher;
use App\Services\TransactionCheckerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionCheckerController extends Controller
{
    /** @var array<string, string> */
    private const TRANSACTION_TYPES = [
        'grn' => 'GRN – Goods Receipt Note',
        'grt' => 'GRT – Goods Return to Vendor',
        'sst' => 'SST – Stock Store Transfer',
    ];

    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $organizations = Organization::orderBy('name')
            ->get(['id', 'name', 'ba_code'])
            ->map(fn (Organization $org) => [
                'id' => $org->id,
                'label' => "{$org->name} ({$org->ba_code})",
            ]);

        return Inertia::render('transaction-checker/index', [
            'connections' => TransactionCheckerService::CONNECTIONS,
            'transactionTypes' => self::TRANSACTION_TYPES,
            'organizations' => $organizations,
            'sessions' => Inertia::defer(fn () => TransactionCheckerSession::with('organization')
                ->where('user_id', $request->user()->id)
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn (TransactionCheckerSession $session) => [
                    'id' => $session->id,
                    'org_label' => $session->organization?->name.' ('.$session->organization?->ba_code.')',
                    'connection' => $session->connection,
                    'transaction_type' => $session->transaction_type,
                    'database' => $session->database,
                    'summary' => $session->summary,
                    'ran_at' => $session->created_at->diffForHumans(),
                    'org_id' => (string) $session->org_id,
                ])
            ),
        ]);
    }

    public function databases(Request $request, ExternalQueryDispatcher $dispatcher): JsonResponse
    {
        abort_if($request->user() === null, 403);

        $request->validate([
            'org_id' => ['required', 'integer', 'exists:organizations,id'],
        ]);

        $log = $dispatcher->dispatch(
            jobType: ExternalQueryJobType::ListTxnCheckerDatabases,
            user: $request->user(),
            context: [
                'org_id' => $request->integer('org_id'),
            ],
        );

        return response()->json($log->toPollPayload(), 202);
    }

    public function check(Request $request, ExternalQueryDispatcher $dispatcher): JsonResponse
    {
        abort_if($request->user() === null, 403);

        $validated = $request->validate([
            'connection' => ['required', 'string', 'in:'.implode(',', array_keys(TransactionCheckerService::CONNECTIONS))],
            'transaction_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TRANSACTION_TYPES))],
            'org_id' => ['required', 'integer', 'exists:organizations,id'],
            'database' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/'],
        ]);

        $log = $dispatcher->dispatch(
            jobType: ExternalQueryJobType::RunTxnChecker,
            user: $request->user(),
            context: $validated,
        );

        return response()->json($log->toPollPayload(), 202);
    }

    public function destroySession(Request $request, TransactionCheckerSession $session): JsonResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($session->user_id !== $request->user()->id, 403);

        return response()->json(['message' => 'Deleted.']);
    }
}
