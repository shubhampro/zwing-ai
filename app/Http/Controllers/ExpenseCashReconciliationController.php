<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseCashReconciliationCsvRequest;
use App\Jobs\ParseExpenseCashReconciliationCsv;
use App\Models\ExpenseCashReconSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseCashReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $sessions = ExpenseCashReconSession::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get([
                'id',
                'name',
                'v_id',
                'zwing_file_name',
                'erp_file_name',
                'zwing_row_count',
                'erp_row_count',
                'status',
                'reconciled_at',
                'created_at',
            ]);

        return Inertia::render('expense-cash-reconciliation/index', [
            'sessions' => $sessions,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('expense-cash-reconciliation/create');
    }

    public function show(Request $request, ExpenseCashReconSession $expenseCashReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($expenseCashReconSession->user_id !== $request->user()->id, 403);

        return Inertia::render('expense-cash-reconciliation/show', [
            'session' => $expenseCashReconSession->only([
                'id',
                'name',
                'v_id',
                'zwing_file_name',
                'erp_file_name',
                'zwing_row_count',
                'erp_row_count',
                'zwing_processed_rows',
                'erp_processed_rows',
                'zwing_skipped_rows',
                'erp_skipped_rows',
                'status',
                'reconciled_at',
                'created_at',
            ]),
        ]);
    }

    public function report(Request $request, ExpenseCashReconSession $expenseCashReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($expenseCashReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $docQuery = trim((string) $request->get('doc_query', ''));
        $siteQuery = trim((string) $request->get('site_query', ''));
        $zwingStatus = trim((string) $request->get('zwing_status', ''));
        $erpStatus = trim((string) $request->get('erp_status', ''));
        $difference = $request->string('difference')->toString();
        $difference = in_array($difference, ['all', 'zero', 'non_zero', 'missing_side'], true) ? $difference : 'all';
        $perPage = 100;
        $page = max(1, (int) $request->get('page', 1));
        $sessionId = $expenseCashReconSession->id;

        $comparisonSql = $this->comparisonSql();
        [$filterClause, $filterParams] = $this->buildReportConstraints(
            filter: $filter,
            docQuery: $docQuery,
            siteQuery: $siteQuery,
            zwingStatus: $zwingStatus,
            erpStatus: $erpStatus,
            difference: $difference,
        );

        $summary = DB::selectOne(<<<SQL
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE match_status = 'matched')           AS matched,
                COUNT(*) FILTER (WHERE match_status = 'amount_mismatch')   AS amount_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'date_mismatch')     AS date_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'status_mismatch')   AS status_mismatch,
                COUNT(*) FILTER (WHERE match_status IN ('amount_mismatch', 'date_mismatch', 'status_mismatch')) AS mismatch,
                COUNT(*) FILTER (WHERE match_status = 'zwing_only')        AS zwing_only,
                COUNT(*) FILTER (WHERE match_status = 'erp_only')          AS erp_only
            FROM ({$comparisonSql}) AS cmp
        SQL, [$sessionId]);

        $totalRows = DB::selectOne(
            "SELECT COUNT(*) AS total FROM ({$comparisonSql}) AS cmp {$filterClause}",
            array_merge([$sessionId], $filterParams),
        )->total;

        $rows = DB::select(
            "SELECT * FROM ({$comparisonSql}) AS cmp {$filterClause} ORDER BY site_id, doc_no LIMIT ? OFFSET ?",
            array_merge([$sessionId], $filterParams, [$perPage, ($page - 1) * $perPage]),
        );

        return Inertia::render('expense-cash-reconciliation/report', [
            'session' => $expenseCashReconSession->only(['id', 'name', 'v_id', 'status']),
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
                'doc_query' => $docQuery,
                'site_query' => $siteQuery,
                'zwing_status' => $zwingStatus,
                'erp_status' => $erpStatus,
                'difference' => $difference,
            ],
            'statusOptions' => $this->distinctStatusOptions($sessionId),
        ]);
    }

    public function exportReport(Request $request, ExpenseCashReconSession $expenseCashReconSession): StreamedResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($expenseCashReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $docQuery = trim((string) $request->get('doc_query', ''));
        $siteQuery = trim((string) $request->get('site_query', ''));
        $zwingStatus = trim((string) $request->get('zwing_status', ''));
        $erpStatus = trim((string) $request->get('erp_status', ''));
        $difference = $request->string('difference')->toString();
        $difference = in_array($difference, ['all', 'zero', 'non_zero', 'missing_side'], true) ? $difference : 'all';
        $sessionId = $expenseCashReconSession->id;
        $comparisonSql = $this->comparisonSql();
        [$filterClause, $filterParams] = $this->buildReportConstraints(
            filter: $filter,
            docQuery: $docQuery,
            siteQuery: $siteQuery,
            zwingStatus: $zwingStatus,
            erpStatus: $erpStatus,
            difference: $difference,
        );

        $rows = DB::select(
            "SELECT * FROM ({$comparisonSql}) AS cmp {$filterClause} ORDER BY site_id, doc_no",
            array_merge([$sessionId], $filterParams),
        );

        $segment = $filter === 'all' ? 'all' : $filter;
        if ($docQuery !== '' || $siteQuery !== '' || $zwingStatus !== '' || $erpStatus !== '' || $difference !== 'all') {
            $segment .= '-filtered';
        }
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $expenseCashReconSession->name);
        $filename = "{$slug}-{$segment}.csv";

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'site_id',
                'doc_no',
                'zwing_date',
                'erp_date',
                'zwing_amount',
                'erp_amount',
                'amount_difference',
                'zwing_status',
                'erp_status',
                'match_status',
            ]);

            foreach ($rows as $row) {
                $zwingAmount = $row->zwing_amount;
                $erpAmount = $row->erp_amount;
                $diff = ($zwingAmount !== null && $erpAmount !== null) ? $zwingAmount - $erpAmount : '';

                fputcsv($handle, [
                    $row->site_id,
                    $row->doc_no,
                    $row->zwing_date ?? '',
                    $row->erp_date ?? '',
                    $zwingAmount ?? '',
                    $erpAmount ?? '',
                    $diff,
                    $row->zwing_status ?? '',
                    $row->erp_status ?? '',
                    $row->match_status,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function uploadCsv(StoreExpenseCashReconciliationCsvRequest $request): RedirectResponse
    {
        /** @var UploadedFile|null $zwing */
        $zwing = $request->file('zwing_csv');

        /** @var UploadedFile|null $erp */
        $erp = $request->file('erp_csv');

        $zwingPath = '';
        $erpPath = '';
        $zwingRowCount = null;
        $erpRowCount = null;

        if ($zwing !== null) {
            $zwingPath = $zwing->store('reconciliation/expense-cash/zwing', 'local');
            $zwingRowCount = $this->countLines($zwing->getRealPath());
        }

        if ($erp !== null) {
            $erpPath = $erp->store('reconciliation/expense-cash/erp', 'local');
            $erpRowCount = $this->countLines($erp->getRealPath());
        }

        /** @var User $user */
        $user = $request->user();

        $session = ExpenseCashReconSession::create([
            'user_id' => $user->id,
            'name' => $request->string('name')->toString(),
            'v_id' => $request->integer('v_id'),
            'zwing_file_name' => $zwing?->getClientOriginalName(),
            'erp_file_name' => $erp?->getClientOriginalName(),
            'zwing_row_count' => $zwingRowCount,
            'erp_row_count' => $erpRowCount,
            'status' => 'pending',
        ]);

        ParseExpenseCashReconciliationCsv::dispatch(
            sessionId: $session->id,
            zwingPath: $zwingPath !== '' ? storage_path("app/private/{$zwingPath}") : '',
            erpPath: $erpPath !== '' ? storage_path("app/private/{$erpPath}") : '',
        );

        return redirect()->route('expense-cash-reconciliation.show', $session);
    }

    public function destroy(Request $request, ExpenseCashReconSession $expenseCashReconSession): RedirectResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($expenseCashReconSession->user_id !== $request->user()->id, 403);

        $expenseCashReconSession->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Reconciliation session ":name" deleted.', ['name' => $expenseCashReconSession->name]),
        ]);

        return redirect()->route('expense-cash-reconciliation.index');
    }

    private function comparisonSql(): string
    {
        return <<<'SQL'
            SELECT
                COALESCE(z.site_id, e.site_id) AS site_id,
                COALESCE(z.doc_no, e.doc_no)   AS doc_no,
                z.txn_date                        AS zwing_date,
                e.txn_date                        AS erp_date,
                z.amount                        AS zwing_amount,
                e.amount                        AS erp_amount,
                z.status                        AS zwing_status,
                e.status                        AS erp_status,
                CASE
                    WHEN z.id IS NULL THEN 'erp_only'
                    WHEN e.id IS NULL THEN 'zwing_only'
                    WHEN z.amount = e.amount AND z.status = e.status AND z.txn_date = e.txn_date THEN 'matched'
                    WHEN z.amount != e.amount THEN 'amount_mismatch'
                    WHEN z.txn_date != e.txn_date THEN 'date_mismatch'
                    ELSE 'status_mismatch'
                END AS match_status
            FROM zwing_expense_cash_reconsile z
            FULL OUTER JOIN erp_expense_cash_reconsile e
                ON  z.session_id = e.session_id
                AND z.site_id = e.site_id
                AND z.doc_no = e.doc_no
            WHERE COALESCE(z.session_id, e.session_id) = ?
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

        return max(0, $count - 1);
    }

    /**
     * @return array{zwing: array<int, string>, erp: array<int, string>}
     */
    private function distinctStatusOptions(int $sessionId): array
    {
        $zwing = DB::table('zwing_expense_cash_reconsile')
            ->where('session_id', $sessionId)
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->all();

        $erp = DB::table('erp_expense_cash_reconsile')
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
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildReportConstraints(
        string $filter,
        string $docQuery,
        string $siteQuery,
        string $zwingStatus,
        string $erpStatus,
        string $difference,
    ): array {
        $clauses = [];
        $params = [];

        if ($filter === 'mismatch') {
            $clauses[] = "match_status IN ('amount_mismatch', 'date_mismatch', 'status_mismatch')";
        } elseif ($filter !== 'all') {
            $clauses[] = 'match_status = ?';
            $params[] = $filter;
        }

        if ($docQuery !== '') {
            $clauses[] = 'doc_no ILIKE ?';
            $params[] = "%{$docQuery}%";
        }

        if ($siteQuery !== '') {
            $clauses[] = 'site_id ILIKE ?';
            $params[] = "%{$siteQuery}%";
        }

        if ($zwingStatus !== '') {
            $clauses[] = 'zwing_status = ?';
            $params[] = $zwingStatus;
        }

        if ($erpStatus !== '') {
            $clauses[] = 'erp_status = ?';
            $params[] = $erpStatus;
        }

        if ($difference === 'zero') {
            $clauses[] = 'zwing_amount IS NOT NULL AND erp_amount IS NOT NULL AND zwing_amount = erp_amount';
        } elseif ($difference === 'non_zero') {
            $clauses[] = 'zwing_amount IS NOT NULL AND erp_amount IS NOT NULL AND zwing_amount <> erp_amount';
        } elseif ($difference === 'missing_side') {
            $clauses[] = '(zwing_amount IS NULL OR erp_amount IS NULL)';
        }

        if ($clauses === []) {
            return ['', []];
        }

        return ['WHERE '.implode(' AND ', $clauses), $params];
    }
}
