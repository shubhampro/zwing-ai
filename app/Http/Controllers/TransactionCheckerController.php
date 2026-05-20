<?php

namespace App\Http\Controllers;

use App\Models\Organization;
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
        'mongodb_ssh' => 'MongoDB (SSH)',
    ];

    /** @var array<string, string> */
    private const TRANSACTION_TYPES = [
        'grn' => 'GRN – Goods Receipt Note',
        'grt' => 'GRT – Goods Return to Vendor',
        'sst' => 'SST – Stock Transfer',
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

        return response()->json($results);
    }

    /**
     * @return array{summary: array<string, int>, rows: array<int, object>}
     */
    private function runCheck(string $connection, string $txnType, int $orgId, string $database): array
    {
        // Dynamically set the database on the connection
        $config = Config::get("database.connections.{$connection}");
        $config['database'] = $database;
        Config::set("database.connections.{$connection}_dynamic", $config);

        $headerTable = "{$txnType}_header";

        $rows = DB::connection("{$connection}_dynamic")
            ->table($headerTable.' as h')
            ->select(['h.id', 'h.doc_no', 'h.doc_date', 'h.site_code'])
            ->orderBy('h.doc_date', 'desc')
            ->limit(500)
            ->get()
            ->toArray();

        $summary = [
            'total' => count($rows),
            'matched' => 0,
            'mismatch' => 0,
            'missing_details' => 0,
        ];

        return compact('summary', 'rows');
    }
}
