<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseConnectionType;
use App\Enums\ExternalQueryJobType;
use App\Enums\ExternalQueryStatus;
use App\Http\Requests\StockReconLogDetailRequest;
use App\Http\Requests\StockReconQtySumRequest;
use App\Http\Requests\StoreStockReconciliationConnectionRequest;
use App\Http\Requests\StoreStockReconciliationCsvRequest;
use App\Http\Requests\SyncStockReconReportRowRequest;
use App\Jobs\FetchStockReconLogDetailsJob;
use App\Jobs\FetchStockReconQtySumsJob;
use App\Jobs\ParseStockReconciliationCsv;
use App\Jobs\PullErpStockFromConnectionJob;
use App\Jobs\PullZwingStockFromConnectionJob;
use App\Jobs\SyncStockReconReportRowJob;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\StockReconErpLog;
use App\Models\StockReconSession;
use App\Models\StockReconZwingLog;
use App\Models\User;
use App\Services\StockReconLogDetailService;
use App\Support\DatabaseHost;
use App\Support\ExternalQueryQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class StockTransactionReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $sessions = StockReconSession::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get([
                'id',
                'name',
                'v_id',
                'source',
                'zwing_file_name',
                'erp_file_name',
                'zwing_row_count',
                'erp_row_count',
                'status',
                'reconciled_at',
                'created_at',
            ]);

        return Inertia::render('stock-transaction-reconciliation/index', [
            'sessions' => $sessions,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('stock-transaction-reconciliation/create');
    }

    public function createFromConnections(Request $request): Response
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

        return Inertia::render('stock-transaction-reconciliation/create-from-connections', [
            'organizations' => $organizationPayload,
            // Stable SSR/client first paint — avoid Date.now()/toLocaleString hydration mismatch.
            'suggestedSessionName' => $firstOrganization !== null
                ? sprintf(
                    '%s · connection stock · %s',
                    $firstOrganization->name,
                    now()->format('Y-m-d h:i A'),
                )
                : '',
        ]);
    }

    public function storeFromConnections(StoreStockReconciliationConnectionRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail($request->integer('organization_id'));

        $includeZwing = $request->boolean('include_zwing');
        $includeErp = $request->boolean('include_erp');
        $pgsqlConnectionId = $request->integer('pgsql_connection_id') ?: null;

        $sessionName = $request->string('name')->trim()->toString();

        if ($sessionName === '') {
            $sessionName = sprintf(
                '%s · connection stock · %s',
                $organization->name,
                now()->format('Y-m-d h:i A'),
            );
        }

        $session = StockReconSession::query()->create([
            'user_id' => $user->id,
            'name' => $sessionName,
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
                'stock_recon_session_id' => $session->id,
                'job_type' => ExternalQueryJobType::PullStockZwing,
                'status' => ExternalQueryStatus::Pending,
                'context' => [
                    'side' => 'zwing',
                    'pgsql_connection_id' => $pgsqlConnectionId,
                ],
            ]);

            $jobs[] = new PullZwingStockFromConnectionJob(
                sessionId: $session->id,
                externalQueryLogId: $zwingLog->id,
                completeSession: ! $includeErp,
            );
        }

        if ($includeErp) {
            $erpLog = ExternalQueryLog::query()->create([
                'user_id' => $user->id,
                'stock_recon_session_id' => $session->id,
                'job_type' => ExternalQueryJobType::PullStockErp,
                'status' => ExternalQueryStatus::Pending,
                'context' => [
                    'side' => 'erp',
                    'pgsql_connection_id' => $pgsqlConnectionId,
                ],
            ]);

            $jobs[] = new PullErpStockFromConnectionJob(
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

                StockReconSession::query()
                    ->where('id', $sessionId)
                    ->where('status', '!=', 'failed')
                    ->update([
                        'status' => 'failed',
                        'failure_reason' => Str::limit($message, 2000),
                    ]);
            })
            ->dispatch();

        return redirect()->route('stock-transaction-reconciliation.show', $session);
    }

    public function show(Request $request, StockReconSession $stockReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($stockReconSession->user_id !== $request->user()->id, 403);

        return Inertia::render('stock-transaction-reconciliation/show', [
            'session' => $stockReconSession->only([
                'id',
                'name',
                'v_id',
                'source',
                'organization_id',
                'zwing_file_name',
                'erp_file_name',
                'zwing_log_file_name',
                'erp_log_file_name',
                'zwing_row_count',
                'erp_row_count',
                'zwing_log_row_count',
                'erp_log_row_count',
                'zwing_processed_rows',
                'erp_processed_rows',
                'zwing_log_processed_rows',
                'erp_log_processed_rows',
                'zwing_skipped_rows',
                'erp_skipped_rows',
                'zwing_query_ms',
                'erp_query_ms',
                'zwing_log_skipped_rows',
                'erp_log_skipped_rows',
                'status',
                'failure_reason',
                'reconciled_at',
                'created_at',
            ]),
        ]);
    }

    public function report(Request $request, StockReconSession $stockReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($stockReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $icodeQuery = trim((string) $request->get('icode_query', ''));
        $siteCode = trim((string) $request->get('site_code', ''));
        $stockPoint = trim((string) $request->get('stock_point', ''));
        $difference = $request->string('difference')->toString();
        $difference = in_array($difference, ['all', 'zero', 'non_zero', 'missing_side'], true) ? $difference : 'all';
        $perPage = 100;
        $page = max(1, (int) $request->get('page', 1));
        $sessionId = $stockReconSession->id;

        [$filterClause, $filterParams] = $this->buildReportConstraints(
            filter: $filter,
            icodeQuery: $icodeQuery,
            siteCode: $siteCode,
            stockPoint: $stockPoint,
            difference: $difference,
        );

        [$summary, $totalRows, $rows, $siteCodeOptions, $stockPointOptions] = $this->loadReportPageData(
            sessionId: $sessionId,
            filterClause: $filterClause,
            filterParams: $filterParams,
            perPage: $perPage,
            page: $page,
        );

        return Inertia::render('stock-transaction-reconciliation/report', [
            'session' => $stockReconSession->only([
                'id',
                'name',
                'v_id',
                'status',
                'source',
                'zwing_file_name',
                'erp_file_name',
                'zwing_log_file_name',
                'erp_log_file_name',
            ]),
            'summary' => $summary,
            'rows' => $rows,
            'pagination' => [
                'total' => $totalRows,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($totalRows / $perPage),
            ],
            'filter' => $filter,
            'filters' => [
                'icode_query' => $icodeQuery,
                'site_code' => $siteCode,
                'stock_point' => $stockPoint,
                'difference' => $difference,
            ],
            'siteCodeOptions' => $siteCodeOptions,
            'stockPointOptions' => $stockPointOptions,
        ]);
    }

    public function reportLogDetails(
        StockReconLogDetailRequest $request,
        StockReconSession $stockReconSession,
        StockReconLogDetailService $logDetailService,
    ): JsonResponse {
        if (($stockReconSession->source ?? 'csv') !== 'connection') {
            return response()->json(
                $logDetailService->forSku(
                    session: $stockReconSession,
                    siteCode: $request->siteCode(),
                    icode: $request->icode(),
                    batchNo: $request->batchNo(),
                    sprefcode: $request->sprefcode(),
                ),
            );
        }

        $log = ExternalQueryLog::query()->create([
            'user_id' => $request->user()->id,
            'stock_recon_session_id' => $stockReconSession->id,
            'job_type' => ExternalQueryJobType::LogDetails,
            'status' => ExternalQueryStatus::Pending,
            'context' => [
                'site_code' => $request->siteCode(),
                'icode' => $request->icode(),
                'batch_no' => $request->batchNo(),
                'sprefcode' => $request->sprefcode(),
            ],
        ]);

        FetchStockReconLogDetailsJob::dispatch(
            externalQueryLogId: $log->id,
            sessionId: $stockReconSession->id,
            siteCode: $request->siteCode(),
            icode: $request->icode(),
            batchNo: $request->batchNo(),
            sprefcode: $request->sprefcode(),
        );

        return response()->json($log->fresh()?->toPollPayload() ?? $log->toPollPayload(), 202);
    }

    public function reportQtySums(
        StockReconQtySumRequest $request,
        StockReconSession $stockReconSession,
    ): JsonResponse {
        abort_unless(($stockReconSession->source ?? 'csv') === 'connection', 422);

        $log = ExternalQueryLog::query()->create([
            'user_id' => $request->user()->id,
            'stock_recon_session_id' => $stockReconSession->id,
            'job_type' => ExternalQueryJobType::QtySums,
            'status' => ExternalQueryStatus::Pending,
            'context' => [
                'site_code' => $request->siteCode(),
                'icode' => $request->icode(),
                'sprefcode' => $request->sprefcode(),
            ],
        ]);

        FetchStockReconQtySumsJob::dispatch(
            externalQueryLogId: $log->id,
            sessionId: $stockReconSession->id,
            siteCode: $request->siteCode(),
            icode: $request->icode(),
            sprefcode: $request->sprefcode(),
        );

        return response()->json($log->fresh()?->toPollPayload() ?? $log->toPollPayload(), 202);
    }

    public function syncReportRow(
        SyncStockReconReportRowRequest $request,
        StockReconSession $stockReconSession,
    ): JsonResponse {
        $log = ExternalQueryLog::query()->create([
            'user_id' => $request->user()->id,
            'stock_recon_session_id' => $stockReconSession->id,
            'job_type' => ExternalQueryJobType::SyncRow,
            'status' => ExternalQueryStatus::Pending,
            'context' => [
                'site_code' => $request->siteCode(),
                'icode' => $request->icode(),
                'batch_no' => $request->batchNo(),
                'sprefcode' => $request->sprefcode(),
            ],
        ]);

        SyncStockReconReportRowJob::dispatch(
            externalQueryLogId: $log->id,
            sessionId: $stockReconSession->id,
            siteCode: $request->siteCode(),
            icode: $request->icode(),
            batchNo: $request->batchNo(),
            sprefcode: $request->sprefcode(),
        );

        return response()->json($log->fresh()?->toPollPayload() ?? $log->toPollPayload(), 202);
    }

    public function exportReport(Request $request, StockReconSession $stockReconSession): StreamedResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($stockReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $icodeQuery = trim((string) $request->get('icode_query', ''));
        $siteCode = trim((string) $request->get('site_code', ''));
        $stockPoint = trim((string) $request->get('stock_point', ''));
        $difference = $request->string('difference')->toString();
        $difference = in_array($difference, ['all', 'zero', 'non_zero', 'missing_side'], true) ? $difference : 'all';
        $sessionId = $stockReconSession->id;
        $comparisonSql = $this->comparisonSql();
        [$filterClause, $filterParams] = $this->buildReportConstraints(
            filter: $filter,
            icodeQuery: $icodeQuery,
            siteCode: $siteCode,
            stockPoint: $stockPoint,
            difference: $difference,
        );

        $sql = "SELECT * FROM ({$comparisonSql}) AS cmp {$filterClause} ORDER BY site_code, icode";
        $bindings = array_merge($this->comparisonBindings($sessionId), $filterParams);

        $segment = $filter === 'all' ? 'all' : $filter;
        if ($icodeQuery !== '' || $siteCode !== '' || $stockPoint !== '' || $difference !== 'all') {
            $segment .= '-filtered';
        }
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $stockReconSession->name);
        $filename = "{$slug}-{$segment}.csv";

        return response()->streamDownload(function () use ($sql, $bindings): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['site_code', 'icode', 'batch_no', 'sprefcode', 'stock_point_name', 'zwing_qty', 'erp_qty', 'difference', 'status']);

            foreach (DB::cursor($sql, $bindings) as $row) {
                $zwingQty = $row->zwing_qty;
                $erpQty = $row->erp_qty;
                $diff = ($zwingQty !== null && $erpQty !== null) ? $zwingQty - $erpQty : '';

                fputcsv($handle, [
                    $row->site_code,
                    $row->icode,
                    $row->batch_no ?? '',
                    $row->sprefcode,
                    $row->stock_point_name,
                    $zwingQty ?? '',
                    $erpQty ?? '',
                    $diff,
                    $row->match_status,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function uploadCsv(StoreStockReconciliationCsvRequest $request): RedirectResponse
    {
        /** @var UploadedFile|null $zwing */
        $zwing = $request->file('zwing_csv');

        /** @var UploadedFile|null $erp */
        $erp = $request->file('erp_csv');

        /** @var UploadedFile|null $zwingLog */
        $zwingLog = $request->file('zwing_log_csv');

        /** @var UploadedFile|null $erpLog */
        $erpLog = $request->file('erp_log_csv');

        $zwingPath = '';
        $erpPath = '';
        $zwingLogPath = '';
        $erpLogPath = '';
        $zwingRowCount = null;
        $erpRowCount = null;
        $zwingLogRowCount = null;
        $erpLogRowCount = null;

        if ($zwing !== null) {
            $zwingPath = $zwing->store('reconciliation/zwing', 'local');
            $zwingRowCount = $this->countLines($zwing->getRealPath());
        }

        if ($erp !== null) {
            $erpPath = $erp->store('reconciliation/erp', 'local');
            $erpRowCount = $this->countLines($erp->getRealPath());
        }

        if ($zwingLog !== null) {
            $zwingLogPath = $zwingLog->store('reconciliation/zwing-logs', 'local');
            $zwingLogRowCount = $this->countLines($zwingLog->getRealPath());
        }

        if ($erpLog !== null) {
            $erpLogPath = $erpLog->store('reconciliation/erp-logs', 'local');
            $erpLogRowCount = $this->countLines($erpLog->getRealPath());
        }

        /** @var User $user */
        $user = $request->user();

        $session = StockReconSession::create([
            'user_id' => $user->id,
            'name' => $request->string('name')->toString(),
            'v_id' => $request->integer('v_id'),
            'source' => 'csv',
            'zwing_file_name' => $zwing?->getClientOriginalName(),
            'erp_file_name' => $erp?->getClientOriginalName(),
            'zwing_log_file_name' => $zwingLog?->getClientOriginalName(),
            'erp_log_file_name' => $erpLog?->getClientOriginalName(),
            'zwing_row_count' => $zwingRowCount,
            'erp_row_count' => $erpRowCount,
            'zwing_log_row_count' => $zwingLogRowCount,
            'erp_log_row_count' => $erpLogRowCount,
            'status' => 'pending',
        ]);

        ParseStockReconciliationCsv::dispatch(
            sessionId: $session->id,
            zwingPath: $zwingPath !== '' ? storage_path("app/private/{$zwingPath}") : '',
            erpPath: $erpPath !== '' ? storage_path("app/private/{$erpPath}") : '',
            zwingLogPath: $zwingLogPath !== '' ? storage_path("app/private/{$zwingLogPath}") : '',
            erpLogPath: $erpLogPath !== '' ? storage_path("app/private/{$erpLogPath}") : '',
        );

        return redirect()->route('stock-transaction-reconciliation.show', $session);
    }

    public function zwingLogs(Request $request, StockReconSession $stockReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($stockReconSession->user_id !== $request->user()->id, 403);

        $perPage = 100;
        $page = max(1, (int) $request->get('page', 1));
        $search = (string) $request->get('search', '');
        $siteCode = trim((string) $request->get('site_code', ''));

        $query = StockReconZwingLog::where('stock_recon_session_id', $stockReconSession->id);

        if ($siteCode !== '') {
            $query->where('site_code', $siteCode);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('icode', 'ilike', "%{$search}%")
                    ->orWhere('site_code', 'ilike', "%{$search}%")
                    ->orWhere('doc_no', 'ilike', "%{$search}%");
            });
        }

        $total = $query->count();
        $rows = $query->orderBy('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get(['id', 'v_id', 'site_code', 'icode', 'batch_no', 'sprefcode', 'doc_no', 'enttype', 'qty']);

        return Inertia::render('stock-transaction-reconciliation/zwing-logs', [
            'session' => $stockReconSession->only(['id', 'name', 'v_id', 'status']),
            'rows' => $rows,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'search' => $search,
            'site_code' => $siteCode,
            'siteCodeOptions' => $this->distinctZwingLogSiteCodeOptions($stockReconSession->id),
        ]);
    }

    public function erpLogs(Request $request, StockReconSession $stockReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($stockReconSession->user_id !== $request->user()->id, 403);

        $perPage = 100;
        $page = max(1, (int) $request->get('page', 1));
        $search = (string) $request->get('search', '');
        $siteCode = trim((string) $request->get('site_code', ''));

        $query = StockReconErpLog::where('stock_recon_session_id', $stockReconSession->id);

        if ($siteCode !== '') {
            $query->where('site_code', $siteCode);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('icode', 'ilike', "%{$search}%")
                    ->orWhere('site_code', 'ilike', "%{$search}%")
                    ->orWhere('doc_no', 'ilike', "%{$search}%");
            });
        }

        $total = $query->count();
        $rows = $query->orderBy('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get(['id', 'v_id', 'site_code', 'icode', 'batch_no', 'sprefcode', 'doc_no', 'enttype', 'qty']);

        return Inertia::render('stock-transaction-reconciliation/erp-logs', [
            'session' => $stockReconSession->only(['id', 'name', 'v_id', 'status']),
            'rows' => $rows,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
            ],
            'search' => $search,
            'site_code' => $siteCode,
            'siteCodeOptions' => $this->distinctErpLogSiteCodeOptions($stockReconSession->id),
        ]);
    }

    public function destroy(Request $request, StockReconSession $stockReconSession): RedirectResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($stockReconSession->user_id !== $request->user()->id, 403);

        $name = $stockReconSession->name;
        $stockReconSession->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Reconciliation session ":name" deleted.', ['name' => $name]),
        ]);

        return redirect()->route('stock-transaction-reconciliation.index');
    }

    /**
     * Materialize the session comparison once, then run summary/count/page against it.
     *
     * @param  array<int, mixed>  $filterParams
     * @return array{0: object, 1: int, 2: list<object>, 3: list<string>, 4: list<string>}
     */
    private function loadReportPageData(
        int $sessionId,
        string $filterClause,
        array $filterParams,
        int $perPage,
        int $page,
    ): array {
        $table = 'stock_recon_report_cmp_'.bin2hex(random_bytes(8));

        return DB::transaction(function () use ($sessionId, $filterClause, $filterParams, $perPage, $page, $table): array {
            DB::statement(
                "CREATE TEMPORARY TABLE {$table} AS {$this->comparisonSql()}",
                $this->comparisonBindings($sessionId),
            );

            try {
                $summary = DB::selectOne(<<<SQL
                    SELECT
                        COUNT(*) AS total,
                        COUNT(*) FILTER (WHERE match_status = 'matched')      AS matched,
                        COUNT(*) FILTER (WHERE match_status = 'qty_mismatch') AS qty_mismatch,
                        COUNT(*) FILTER (WHERE match_status = 'zwing_only')   AS zwing_only,
                        COUNT(*) FILTER (WHERE match_status = 'erp_only')     AS erp_only
                    FROM {$table}
                SQL);

                $totalRows = (int) DB::selectOne(
                    "SELECT COUNT(*) AS total FROM {$table} {$filterClause}",
                    $filterParams,
                )->total;

                $rows = DB::select(
                    "SELECT * FROM {$table} {$filterClause} ORDER BY site_code, icode LIMIT ? OFFSET ?",
                    array_merge($filterParams, [$perPage, ($page - 1) * $perPage]),
                );

                [$siteCodeOptions, $stockPointOptions] = $this->reportFilterOptions($sessionId);

                return [$summary, $totalRows, $rows, $siteCodeOptions, $stockPointOptions];
            } finally {
                DB::statement("DROP TABLE IF EXISTS {$table}");
            }
        });
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function comparisonBindings(int $sessionId): array
    {
        return [$sessionId, $sessionId];
    }

    private function comparisonSql(): string
    {
        return <<<'SQL'
            SELECT
                COALESCE(z.site_code, e.site_code)               AS site_code,
                COALESCE(z.icode, e.icode)                       AS icode,
                COALESCE(z.batch_no, e.batch_no)                 AS batch_no,
                CAST(COALESCE(z.sprefcode, e.sprefcode) AS TEXT) AS sprefcode,
                COALESCE(z.stock_point_name, e.stock_point_name) AS stock_point_name,
                z.qty                                             AS zwing_qty,
                e.qty                                             AS erp_qty,
                CASE
                    WHEN z.id IS NULL THEN 'erp_only'
                    WHEN e.id IS NULL THEN 'zwing_only'
                    WHEN z.qty = e.qty THEN 'matched'
                    ELSE 'qty_mismatch'
                END AS match_status
            FROM (
                SELECT id, site_code, icode, batch_no, sprefcode, stock_point_name, qty
                FROM zwing_stock_reconsile
                WHERE session_id = ?
            ) z
            FULL OUTER JOIN (
                SELECT id, site_code, icode, batch_no, sprefcode, stock_point_name, qty
                FROM erp_stock_reconsile
                WHERE session_id = ?
            ) e
                ON  z.site_code  = e.site_code
                AND z.icode      = e.icode
                AND z.batch_no   = e.batch_no
                AND z.sprefcode  = e.sprefcode
        SQL;
    }

    private function countLines(string $path): int
    {
        $count = 0;
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return 0;
        }

        while (fgets($handle) !== false) {
            $count++;
        }

        fclose($handle);

        /** Subtract 1 for the header row */
        return max(0, $count - 1);
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function reportFilterOptions(int $sessionId): array
    {
        $rows = DB::select(<<<'SQL'
            SELECT kind, value
            FROM (
                SELECT 'site_code' AS kind, site_code AS value
                FROM zwing_stock_reconsile
                WHERE session_id = ?
                  AND site_code IS NOT NULL
                  AND site_code != ''
                UNION
                SELECT 'site_code', site_code
                FROM erp_stock_reconsile
                WHERE session_id = ?
                  AND site_code IS NOT NULL
                  AND site_code != ''
                UNION
                SELECT 'stock_point', stock_point_name
                FROM zwing_stock_reconsile
                WHERE session_id = ?
                  AND stock_point_name IS NOT NULL
                  AND stock_point_name != ''
                UNION
                SELECT 'stock_point', stock_point_name
                FROM erp_stock_reconsile
                WHERE session_id = ?
                  AND stock_point_name IS NOT NULL
                  AND stock_point_name != ''
            ) AS opts
            ORDER BY kind, value
        SQL, [$sessionId, $sessionId, $sessionId, $sessionId]);

        $siteCodes = [];
        $stockPoints = [];

        foreach ($rows as $row) {
            if ($row->kind === 'site_code') {
                $siteCodes[] = (string) $row->value;
            } else {
                $stockPoints[] = (string) $row->value;
            }
        }

        return [$siteCodes, $stockPoints];
    }

    /**
     * @return array<int, string>
     */
    private function distinctZwingLogSiteCodeOptions(int $sessionId): array
    {
        return StockReconZwingLog::query()
            ->where('stock_recon_session_id', $sessionId)
            ->whereNotNull('site_code')
            ->where('site_code', '!=', '')
            ->distinct()
            ->orderBy('site_code')
            ->pluck('site_code')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function distinctErpLogSiteCodeOptions(int $sessionId): array
    {
        return StockReconErpLog::query()
            ->where('stock_recon_session_id', $sessionId)
            ->whereNotNull('site_code')
            ->where('site_code', '!=', '')
            ->distinct()
            ->orderBy('site_code')
            ->pluck('site_code')
            ->all();
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildReportConstraints(
        string $filter,
        string $icodeQuery,
        string $siteCode,
        string $stockPoint,
        string $difference,
    ): array {
        $clauses = [];
        $params = [];

        if ($filter !== 'all') {
            $clauses[] = 'match_status = ?';
            $params[] = $filter;
        }

        if ($icodeQuery !== '') {
            $likeOperator = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $clauses[] = "icode {$likeOperator} ?";
            $params[] = "%{$icodeQuery}%";
        }

        if ($siteCode !== '') {
            $clauses[] = 'site_code = ?';
            $params[] = $siteCode;
        }

        if ($stockPoint !== '') {
            $clauses[] = 'stock_point_name = ?';
            $params[] = $stockPoint;
        }

        if ($difference === 'zero') {
            $clauses[] = 'zwing_qty IS NOT NULL AND erp_qty IS NOT NULL AND zwing_qty = erp_qty';
        } elseif ($difference === 'non_zero') {
            $clauses[] = 'zwing_qty IS NOT NULL AND erp_qty IS NOT NULL AND zwing_qty <> erp_qty';
        } elseif ($difference === 'missing_side') {
            $clauses[] = '(zwing_qty IS NULL OR erp_qty IS NULL)';
        }

        if ($clauses === []) {
            return ['', []];
        }

        return ['WHERE '.implode(' AND ', $clauses), $params];
    }
}
