<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseConnectionType;
use App\Enums\ExternalQueryJobType;
use App\Enums\ExternalQueryStatus;
use App\Enums\TransactionReconType;
use App\Http\Requests\StoreTransactionReconciliationConnectionRequest;
use App\Jobs\PullErpTransactionFromConnectionJob;
use App\Jobs\PullZwingTransactionFromConnectionJob;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\TransactionReconSession;
use App\Models\User;
use App\Support\DatabaseHost;
use App\Support\ExternalQueryQueue;
use App\Support\TransactionReconciliationComparison;
use App\Support\TransactionReconciliationQueries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TransactionReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $sessions = TransactionReconSession::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get([
                'id',
                'name',
                'type',
                'v_id',
                'zwing_file_name',
                'erp_file_name',
                'zwing_row_count',
                'erp_row_count',
                'status',
                'reconciled_at',
                'created_at',
            ]);

        return Inertia::render('transaction-reconciliation/index', [
            'sessions' => $sessions->map(fn (TransactionReconSession $session) => [
                ...$session->toArray(),
                'type' => $session->type->value,
                'type_label' => $session->type->label(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $organizations = Organization::query()
            ->whereNotNull('db_name')
            ->with(['databaseConnections' => fn ($query) => $query
                ->active()
                ->ofType(DatabaseConnectionType::Pgsql)
                ->orderBy('type')])
            ->orderBy('name')
            ->get(['id', 'name', 'ba_code', 'vendor_id', 'db_name']);

        $organizationPayload = $organizations
            ->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'ba_code' => $organization->ba_code,
                'vendor_id' => $organization->vendor_id,
                'has_db_name' => filled($organization->db_name),
                'pgsql_connections' => $organization->databaseConnections
                    ->map(fn (OrganizationDatabaseConnection $connection) => [
                        'id' => $connection->id,
                        'type' => $connection->type->value,
                        'host_masked' => DatabaseHost::mask($connection->host),
                        'is_active' => $connection->is_active,
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();

        /** @var Organization|null $firstOrganization */
        $firstOrganization = $organizations->first();

        return Inertia::render('transaction-reconciliation/create', [
            'organizations' => $organizationPayload,
            'types' => collect(TransactionReconType::cases())
                ->map(fn (TransactionReconType $type) => [
                    'key' => $type->value,
                    'label' => $type->label(),
                    'available' => TransactionReconciliationQueries::isAvailable($type),
                ])
                ->values()
                ->all(),
            'suggestedSessionName' => $firstOrganization !== null
                ? sprintf(
                    '%s · %s',
                    $firstOrganization->name,
                    now()->format('Y-m-d h:i A'),
                )
                : '',
        ]);
    }

    public function store(StoreTransactionReconciliationConnectionRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail($request->integer('organization_id'));

        $type = TransactionReconType::from($request->string('type')->toString());
        $includeZwing = $request->boolean('include_zwing');
        $includeErp = $request->boolean('include_erp');
        $pgsqlConnectionId = $request->integer('pgsql_connection_id') ?: null;

        $sessionName = $request->string('name')->trim()->toString();

        if ($sessionName === '') {
            $sessionName = sprintf(
                '%s · %s · %s',
                $organization->name,
                $type->label(),
                now()->format('Y-m-d h:i A'),
            );
        }

        $session = TransactionReconSession::query()->create([
            'user_id' => $user->id,
            'name' => $sessionName,
            'type' => $type,
            'v_id' => $organization->vendor_id,
            'source' => 'connection',
            'organization_id' => $organization->id,
            'pgsql_connection_id' => $pgsqlConnectionId,
            'zwing_file_name' => $includeZwing ? 'mysql_ssh' : null,
            'erp_file_name' => $includeErp ? 'pgsql connection' : null,
            'zwing_row_count' => null,
            'erp_row_count' => null,
            'status' => 'pending',
        ]);

        $jobs = [];

        if ($includeZwing) {
            $zwingLog = ExternalQueryLog::query()->create([
                'user_id' => $user->id,
                'job_type' => ExternalQueryJobType::PullTransactionZwing,
                'status' => ExternalQueryStatus::Pending,
                'context' => [
                    'side' => 'zwing',
                    'transaction_recon_session_id' => $session->id,
                    'type' => $type->value,
                ],
            ]);

            $jobs[] = new PullZwingTransactionFromConnectionJob(
                sessionId: $session->id,
                externalQueryLogId: $zwingLog->id,
                completeSession: ! $includeErp,
            );
        }

        if ($includeErp) {
            $erpLog = ExternalQueryLog::query()->create([
                'user_id' => $user->id,
                'job_type' => ExternalQueryJobType::PullTransactionErp,
                'status' => ExternalQueryStatus::Pending,
                'context' => [
                    'side' => 'erp',
                    'transaction_recon_session_id' => $session->id,
                    'pgsql_connection_id' => $pgsqlConnectionId,
                    'type' => $type->value,
                ],
            ]);

            $jobs[] = new PullErpTransactionFromConnectionJob(
                sessionId: $session->id,
                pgsqlConnectionId: (int) $pgsqlConnectionId,
                externalQueryLogId: $erpLog->id,
            );
        }

        $sessionId = $session->id;

        Bus::chain($jobs)
            ->onQueue(ExternalQueryQueue::NAME)
            ->catch(function (Throwable $exception) use ($sessionId): void {
                $message = preg_replace(
                    '/Database:\s*[^,]*/i',
                    'Database: [hidden]',
                    $exception->getMessage(),
                ) ?? $exception->getMessage();

                TransactionReconSession::query()
                    ->where('id', $sessionId)
                    ->where('status', '!=', 'failed')
                    ->update([
                        'status' => 'failed',
                        'failure_reason' => Str::limit($message, 2000),
                    ]);
            })
            ->dispatch();

        return redirect()->route('transaction-reconciliation.show', $session);
    }

    public function show(Request $request, TransactionReconSession $transactionReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($transactionReconSession->user_id !== $request->user()->id, 403);

        return Inertia::render('transaction-reconciliation/show', [
            'session' => [
                ...$transactionReconSession->only([
                    'id',
                    'name',
                    'v_id',
                    'source',
                    'zwing_file_name',
                    'erp_file_name',
                    'zwing_row_count',
                    'erp_row_count',
                    'zwing_processed_rows',
                    'erp_processed_rows',
                    'zwing_skipped_rows',
                    'erp_skipped_rows',
                    'zwing_query_ms',
                    'erp_query_ms',
                    'status',
                    'failure_reason',
                    'reconciled_at',
                    'created_at',
                ]),
                'type' => $transactionReconSession->type->value,
                'type_label' => $transactionReconSession->type->label(),
            ],
        ]);
    }

    public function report(Request $request, TransactionReconSession $transactionReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($transactionReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $codeQuery = trim((string) $request->get('code_query', ''));
        $zwingStatus = trim((string) $request->get('zwing_status', ''));
        $erpStatus = trim((string) $request->get('erp_status', ''));
        $perPage = 100;
        $page = max(1, (int) $request->get('page', 1));
        $sessionId = $transactionReconSession->id;

        $comparisonSql = TransactionReconciliationComparison::comparisonSql();
        [$filterClause, $filterParams] = $this->buildReportConstraints(
            filter: $filter,
            codeQuery: $codeQuery,
            zwingStatus: $zwingStatus,
            erpStatus: $erpStatus,
        );

        $mismatchStatuses = TransactionReconciliationComparison::mismatchMatchStatusesSqlList();

        $summary = DB::selectOne(<<<SQL
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE match_status = 'matched') AS matched,
                COUNT(*) FILTER (WHERE match_status = 'code_mismatch') AS code_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'type_mismatch') AS type_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'amount_mismatch') AS amount_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'date_mismatch') AS date_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'status_mismatch') AS status_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'packet_not_in_erp') AS zwing_only,
                COUNT(*) FILTER (WHERE match_status = 'packet_not_in_zwing') AS erp_only,
                COUNT(*) FILTER (WHERE match_status IN ({$mismatchStatuses})) AS mismatch
            FROM ({$comparisonSql}) AS cmp
        SQL, [$sessionId, $sessionId]);

        $totalRows = (int) DB::selectOne(
            "SELECT COUNT(*) AS total FROM ({$comparisonSql}) AS cmp {$filterClause}",
            array_merge([$sessionId, $sessionId], $filterParams),
        )->total;

        $rows = DB::select(
            "SELECT * FROM ({$comparisonSql}) AS cmp {$filterClause}
             ORDER BY
                CASE match_status
                    WHEN 'code_mismatch' THEN 0
                    WHEN 'type_mismatch' THEN 1
                    WHEN 'amount_mismatch' THEN 2
                    WHEN 'date_mismatch' THEN 3
                    WHEN 'status_mismatch' THEN 4
                    WHEN 'packet_not_in_erp' THEN 5
                    WHEN 'packet_not_in_zwing' THEN 6
                    ELSE 7
                END,
                code NULLS LAST,
                txn_id
             LIMIT ? OFFSET ?",
            array_merge([$sessionId, $sessionId], $filterParams, [$perPage, ($page - 1) * $perPage]),
        );

        return Inertia::render('transaction-reconciliation/report', [
            'session' => [
                ...$transactionReconSession->only(['id', 'name', 'v_id', 'status']),
                'type' => $transactionReconSession->type->value,
                'type_label' => $transactionReconSession->type->label(),
                'uses_cash_columns' => $transactionReconSession->type->usesCashColumns(),
            ],
            'summary' => [
                'total' => (int) ($summary->total ?? 0),
                'matched' => (int) ($summary->matched ?? 0),
                'mismatch' => (int) ($summary->mismatch ?? 0),
                'code_mismatch' => (int) ($summary->code_mismatch ?? 0),
                'type_mismatch' => (int) ($summary->type_mismatch ?? 0),
                'amount_mismatch' => (int) ($summary->amount_mismatch ?? 0),
                'date_mismatch' => (int) ($summary->date_mismatch ?? 0),
                'status_mismatch' => (int) ($summary->status_mismatch ?? 0),
                'zwing_only' => (int) ($summary->zwing_only ?? 0),
                'erp_only' => (int) ($summary->erp_only ?? 0),
            ],
            'rows' => $rows,
            'pagination' => [
                'total' => $totalRows,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($totalRows / $perPage)),
            ],
            'filter' => $filter,
            'filters' => [
                'code_query' => $codeQuery,
                'zwing_status' => $zwingStatus,
                'erp_status' => $erpStatus,
            ],
            'statusOptions' => $this->distinctStatusOptions($sessionId),
        ]);
    }

    public function exportReport(Request $request, TransactionReconSession $transactionReconSession): StreamedResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($transactionReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $codeQuery = trim((string) $request->get('code_query', ''));
        $zwingStatus = trim((string) $request->get('zwing_status', ''));
        $erpStatus = trim((string) $request->get('erp_status', ''));
        $sessionId = $transactionReconSession->id;
        $comparisonSql = TransactionReconciliationComparison::comparisonSql();
        [$filterClause, $filterParams] = $this->buildReportConstraints(
            filter: $filter,
            codeQuery: $codeQuery,
            zwingStatus: $zwingStatus,
            erpStatus: $erpStatus,
        );

        $rows = DB::select(
            "SELECT * FROM ({$comparisonSql}) AS cmp {$filterClause} ORDER BY txn_id",
            array_merge([$sessionId, $sessionId], $filterParams),
        );

        $segment = $filter === 'all' ? 'all' : $filter;
        if ($codeQuery !== '' || $zwingStatus !== '' || $erpStatus !== '') {
            $segment .= '-filtered';
        }
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $transactionReconSession->name);
        $filename = "{$slug}-{$segment}.csv";

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'txn_id',
                'site_id',
                'zwing_code',
                'erp_code',
                'zwing_type',
                'erp_type',
                'zwing_date',
                'erp_date',
                'zwing_amount',
                'erp_amount',
                'zwing_status',
                'erp_status',
                'match_status',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row->txn_id,
                    $row->site_id ?? '',
                    $row->zwing_code,
                    $row->erp_code,
                    $row->zwing_type,
                    $row->erp_type,
                    $row->zwing_date ?? '',
                    $row->erp_date ?? '',
                    $row->zwing_amount ?? '',
                    $row->erp_amount ?? '',
                    $row->zwing_status,
                    $row->erp_status,
                    $row->match_status,
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function destroy(Request $request, TransactionReconSession $transactionReconSession): RedirectResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($transactionReconSession->user_id !== $request->user()->id, 403);

        $transactionReconSession->delete();

        return redirect()->route('transaction-reconciliation.index');
    }

    /**
     * @return array{zwing: list<string>, erp: list<string>}
     */
    private function distinctStatusOptions(int $sessionId): array
    {
        $zwing = DB::table('zwing_transaction_reconsile')
            ->where('session_id', $sessionId)
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->all();

        $erp = DB::table('erp_transaction_reconsile')
            ->where('session_id', $sessionId)
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->all();

        return [
            'zwing' => array_values($zwing),
            'erp' => array_values($erp),
        ];
    }

    /**
     * @return array{0: string, 1: list<mixed>}
     */
    private function buildReportConstraints(
        string $filter,
        string $codeQuery,
        string $zwingStatus,
        string $erpStatus,
    ): array {
        $clauses = [];
        $params = [];

        if ($filter === 'mismatch') {
            $clauses[] = 'match_status IN ('.TransactionReconciliationComparison::mismatchMatchStatusesSqlList().')';
        } elseif ($filter === 'zwing_only') {
            $clauses[] = "match_status = 'packet_not_in_erp'";
        } elseif ($filter === 'erp_only') {
            $clauses[] = "match_status = 'packet_not_in_zwing'";
        } elseif ($filter !== 'all') {
            $clauses[] = 'match_status = ?';
            $params[] = $filter;
        }

        if ($codeQuery !== '') {
            $clauses[] = '(txn_id ILIKE ? OR code ILIKE ? OR zwing_code ILIKE ? OR erp_code ILIKE ? OR site_id ILIKE ?)';
            $params[] = "%{$codeQuery}%";
            $params[] = "%{$codeQuery}%";
            $params[] = "%{$codeQuery}%";
            $params[] = "%{$codeQuery}%";
            $params[] = "%{$codeQuery}%";
        }

        if ($zwingStatus !== '') {
            $clauses[] = 'zwing_status = ?';
            $params[] = $zwingStatus;
        }

        if ($erpStatus !== '') {
            $clauses[] = 'erp_status = ?';
            $params[] = $erpStatus;
        }

        $filterClause = $clauses === []
            ? ''
            : 'WHERE '.implode(' AND ', $clauses);

        return [$filterClause, $params];
    }
}
