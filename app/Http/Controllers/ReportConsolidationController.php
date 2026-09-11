<?php

namespace App\Http\Controllers;

use App\Enums\ExternalQueryJobType;
use App\Enums\ExternalQueryStatus;
use App\Http\Requests\StoreReportConsolidationRequest;
use App\Jobs\PullReportConsolidationFromConnectionJob;
use App\Models\ExternalQueryLog;
use App\Models\Organization;
use App\Models\ReportReconSession;
use App\Models\User;
use App\Support\ExternalQueryQueue;
use App\Support\ReportConsolidationComparison;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportConsolidationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $sessions = ReportReconSession::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get([
                'id',
                'name',
                'v_id',
                'invoice_row_count',
                'mop_row_count',
                'status',
                'reconciled_at',
                'created_at',
            ]);

        return Inertia::render('report-consolidation/index', [
            'sessions' => $sessions,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $organizations = Organization::query()
            ->whereNotNull('db_name')
            ->orderBy('name')
            ->get(['id', 'name', 'ba_code', 'vendor_id', 'db_name']);

        return Inertia::render('report-consolidation/create', [
            'organizations' => $organizations
                ->map(fn (Organization $organization) => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'ba_code' => $organization->ba_code,
                    'vendor_id' => $organization->vendor_id,
                    'has_db_name' => filled($organization->db_name),
                ])
                ->values()
                ->all(),
        ]);
    }

    public function store(StoreReportConsolidationRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Organization $organization */
        $organization = Organization::query()->findOrFail($request->integer('organization_id'));

        $dateFrom = $request->date('date_from')?->toDateString();
        $dateTo = $request->date('date_to')?->toDateString();

        $sessionName = $request->string('name')->trim()->toString();

        if ($sessionName === '') {
            $sessionName = sprintf(
                '%s · Invoice vs MOP · %s to %s · %s',
                $organization->name,
                $dateFrom,
                $dateTo,
                now()->format('Y-m-d h:i A'),
            );
        }

        $session = ReportReconSession::query()->create([
            'user_id' => $user->id,
            'name' => $sessionName,
            'v_id' => $organization->vendor_id,
            'organization_id' => $organization->id,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'status' => 'pending',
        ]);

        $log = ExternalQueryLog::query()->create([
            'user_id' => $user->id,
            'job_type' => ExternalQueryJobType::PullReportConsolidation,
            'status' => ExternalQueryStatus::Pending,
            'context' => [
                'report_recon_session_id' => $session->id,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);

        PullReportConsolidationFromConnectionJob::dispatch(
            sessionId: $session->id,
            externalQueryLogId: $log->id,
        )->onQueue(ExternalQueryQueue::NAME);

        return redirect()->route('report-consolidation.show', $session);
    }

    public function show(Request $request, ReportReconSession $reportReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($reportReconSession->user_id !== $request->user()->id, 403);

        return Inertia::render('report-consolidation/show', [
            'session' => [
                ...$reportReconSession->only([
                    'id',
                    'name',
                    'v_id',
                    'invoice_row_count',
                    'mop_row_count',
                    'invoice_processed_rows',
                    'mop_processed_rows',
                    'invoice_skipped_rows',
                    'mop_skipped_rows',
                    'invoice_query_ms',
                    'mop_query_ms',
                    'status',
                    'failure_reason',
                    'reconciled_at',
                    'created_at',
                ]),
                'date_from' => $reportReconSession->date_from?->toDateString(),
                'date_to' => $reportReconSession->date_to?->toDateString(),
            ],
        ]);
    }

    public function report(Request $request, ReportReconSession $reportReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($reportReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $invoiceQuery = trim((string) $request->get('invoice_query', ''));
        $store = trim((string) $request->get('store', ''));
        $difference = $request->string('difference')->toString();
        $difference = in_array($difference, ['all', 'zero', 'non_zero', 'missing_side'], true) ? $difference : 'all';
        $perPage = 100;
        $page = max(1, (int) $request->get('page', 1));
        $sessionId = $reportReconSession->id;

        $comparisonSql = ReportConsolidationComparison::comparisonSql();
        [$filterClause, $filterParams] = $this->buildReportConstraints(
            filter: $filter,
            invoiceQuery: $invoiceQuery,
            difference: $difference,
            store: $store,
        );

        $mismatchStatuses = ReportConsolidationComparison::mismatchMatchStatusesSqlList();

        $summary = DB::selectOne(<<<SQL
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE match_status = 'matched') AS matched,
                COUNT(*) FILTER (WHERE match_status = 'amount_mismatch') AS amount_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'invoice_only') AS invoice_only,
                COUNT(*) FILTER (WHERE match_status = 'mop_only') AS mop_only,
                COUNT(*) FILTER (WHERE match_status IN ({$mismatchStatuses})) AS mismatch
            FROM ({$comparisonSql}) AS cmp
        SQL, [$sessionId]);

        $totalRows = DB::selectOne(
            "SELECT COUNT(*) AS total FROM ({$comparisonSql}) AS cmp {$filterClause}",
            array_merge([$sessionId], $filterParams),
        )->total;

        $rows = DB::select(
            "SELECT * FROM ({$comparisonSql}) AS cmp {$filterClause} ORDER BY invoice_no LIMIT ? OFFSET ?",
            array_merge([$sessionId], $filterParams, [$perPage, ($page - 1) * $perPage]),
        );

        return Inertia::render('report-consolidation/report', [
            'session' => $reportReconSession->only(['id', 'name', 'v_id', 'status']),
            'summary' => $summary,
            'rows' => $rows,
            'pagination' => [
                'total' => (int) $totalRows,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil((int) $totalRows / $perPage),
            ],
            'filter' => $filter,
            'filters' => [
                'invoice_query' => $invoiceQuery,
                'store' => $store,
                'difference' => $difference,
            ],
            'stores' => $this->sessionStoreNames($sessionId),
        ]);
    }

    public function exportReport(Request $request, ReportReconSession $reportReconSession): StreamedResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($reportReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $invoiceQuery = trim((string) $request->get('invoice_query', ''));
        $store = trim((string) $request->get('store', ''));
        $difference = $request->string('difference')->toString();
        $difference = in_array($difference, ['all', 'zero', 'non_zero', 'missing_side'], true) ? $difference : 'all';
        $sessionId = $reportReconSession->id;
        $comparisonSql = ReportConsolidationComparison::comparisonSql();
        [$filterClause, $filterParams] = $this->buildReportConstraints(
            filter: $filter,
            invoiceQuery: $invoiceQuery,
            difference: $difference,
            store: $store,
        );

        $rows = DB::select(
            "SELECT * FROM ({$comparisonSql}) AS cmp {$filterClause} ORDER BY invoice_no",
            array_merge([$sessionId], $filterParams),
        );

        $segment = $filter === 'all' ? 'all' : $filter;
        if ($invoiceQuery !== '' || $store !== '' || $difference !== 'all') {
            $segment .= '-filtered';
        }
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $reportReconSession->name);
        $filename = "{$slug}-{$segment}.csv";

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'invoice_no',
                'store_name',
                'invoice_date',
                'invoice_total',
                'mop_date',
                'mop_total',
                'amount_difference',
                'match_status',
            ]);

            foreach ($rows as $row) {
                $invoiceTotal = $row->invoice_total;
                $mopTotal = $row->mop_total;
                $diff = ($invoiceTotal !== null && $mopTotal !== null) ? $invoiceTotal - $mopTotal : '';

                fputcsv($handle, [
                    $row->invoice_no ?? '',
                    $row->store_name ?? '',
                    $row->invoice_date ?? '',
                    $invoiceTotal ?? '',
                    $row->mop_date ?? '',
                    $mopTotal ?? '',
                    $diff,
                    $row->match_status,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, ReportReconSession $reportReconSession): RedirectResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($reportReconSession->user_id !== $request->user()->id, 403);

        $reportReconSession->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Report consolidation session ":name" deleted.', ['name' => $reportReconSession->name]),
        ]);

        return redirect()->route('report-consolidation.index');
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildReportConstraints(
        string $filter,
        string $invoiceQuery,
        string $difference,
        string $store = '',
    ): array {
        $clauses = [];
        $params = [];

        if ($filter === 'mismatch') {
            $clauses[] = 'match_status IN ('.ReportConsolidationComparison::mismatchMatchStatusesSqlList().')';
        } elseif ($filter !== 'all') {
            $clauses[] = 'match_status = ?';
            $params[] = $filter;
        }

        if ($invoiceQuery !== '') {
            $clauses[] = '(LOWER(invoice_no) LIKE ? OR LOWER(invoice_invoice_no) LIKE ? OR LOWER(mop_invoice_no) LIKE ? OR LOWER(store_name) LIKE ?)';
            $like = '%'.mb_strtolower($invoiceQuery).'%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($store !== '') {
            $clauses[] = 'store_name = ?';
            $params[] = $store;
        }

        if ($difference === 'zero') {
            $clauses[] = 'invoice_total IS NOT NULL AND mop_total IS NOT NULL AND invoice_total = mop_total';
        } elseif ($difference === 'non_zero') {
            $clauses[] = 'invoice_total IS NOT NULL AND mop_total IS NOT NULL AND invoice_total <> mop_total';
        } elseif ($difference === 'missing_side') {
            $clauses[] = '(invoice_total IS NULL OR mop_total IS NULL)';
        }

        if ($clauses === []) {
            return ['', []];
        }

        return ['WHERE '.implode(' AND ', $clauses), $params];
    }

    /**
     * @return list<string>
     */
    private function sessionStoreNames(int $sessionId): array
    {
        $invoiceStores = DB::table('report_recon_invoices')
            ->where('session_id', $sessionId)
            ->whereNotNull('store_name')
            ->where('store_name', '!=', '')
            ->distinct()
            ->pluck('store_name');

        $mopStores = DB::table('report_recon_mops')
            ->where('session_id', $sessionId)
            ->whereNotNull('store_name')
            ->where('store_name', '!=', '')
            ->distinct()
            ->pluck('store_name');

        return $invoiceStores
            ->merge($mopStores)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
