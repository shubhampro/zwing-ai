<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\TransactionCheckerSession;
use App\Services\SshTunnelManager;
use App\Services\TransactionChecker\GrnChecker;
use App\Services\TransactionChecker\GrtChecker;
use App\Services\TransactionChecker\SstChecker;
use App\Services\TransactionChecker\TransactionCheckerInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TransactionCheckerController extends Controller
{
    /** @var array<string, string> */
    private const CONNECTIONS = [
        'mysql_ssh' => 'MySQL (SSH)',
    ];

    /** @var array<string, class-string<TransactionCheckerInterface>> */
    private const CHECKERS = [
        'grn' => GrnChecker::class,
        'grt' => GrtChecker::class,
        'sst' => SstChecker::class,
    ];

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
            'connections' => self::CONNECTIONS,
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
                    // restore params
                    'org_id' => (string) $session->org_id,
                ])
            ),
        ]);
    }

    /**
     * List MySQL SSH databases matching the org's vendor_id.
     */
    public function databases(Request $request): JsonResponse
    {
        abort_if($request->user() === null, 403);

        $request->validate([
            'org_id' => ['required', 'integer', 'exists:organizations,id'],
        ]);

        $org = Organization::findOrFail($request->integer('org_id'));

        SshTunnelManager::ensureMysqlOpen();

        // Connect without a specific database to run SHOW DATABASES
        $baseConfig = Config::get('database.connections.mysql_ssh');
        $baseConfig['database'] = '';
        Config::set('database.connections.mysql_ssh_nodatabase', $baseConfig);

        $pattern = "_{$org->vendor_id}_";

        $databases = collect(DB::connection('mysql_ssh_nodatabase')->select('SHOW DATABASES'))
            ->map(fn (object $row) => array_values((array) $row)[0])
            ->filter(fn (string $name) => str_contains($name, $pattern))
            ->values();

        return response()->json(['databases' => $databases]);
    }

    public function check(Request $request): JsonResponse
    {
        abort_if($request->user() === null, 403);

        $validated = $request->validate([
            'connection' => ['required', 'string', 'in:'.implode(',', array_keys(self::CONNECTIONS))],
            'transaction_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TRANSACTION_TYPES))],
            'org_id' => ['required', 'integer', 'exists:organizations,id'],
            'database' => ['required', 'string', 'regex:/^[a-zA-Z0-9_]+$/'],
        ]);

        $results = $this->runCheck(
            $validated['connection'],
            $validated['transaction_type'],
            (int) $validated['org_id'],
            $validated['database'],
        );

        TransactionCheckerSession::create([
            'user_id' => $request->user()->id,
            'org_id' => $validated['org_id'],
            'connection' => $validated['connection'],
            'transaction_type' => $validated['transaction_type'],
            'database' => $validated['database'],
            'summary' => $results['summary'],
        ]);

        return response()->json($results);
    }

    public function destroySession(Request $request, TransactionCheckerSession $session): JsonResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($session->user_id !== $request->user()->id, 403);

        // $session->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>}
     */
    private function runCheck(string $connection, string $txnType, int $orgId, string $database): array
    {
        if ($connection === 'mysql_ssh') {
            SshTunnelManager::ensureMysqlOpen();
        }

        $config = Config::get("database.connections.{$connection}");
        $config['database'] = $database;
        Config::set("database.connections.{$connection}_dynamic", $config);

        $db = DB::connection("{$connection}_dynamic");

        /** @var TransactionCheckerInterface $checker */
        $checker = new (self::CHECKERS[$txnType])();

        return $checker->run($db);
    }
}
