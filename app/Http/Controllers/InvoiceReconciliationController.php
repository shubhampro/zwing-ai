<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInvoiceReconciliationCsvRequest;
use App\Jobs\ParseInvoiceReconciliationCsv;
use App\Models\InvoiceReconSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceReconciliationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $sessions = InvoiceReconSession::query()
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

        return Inertia::render('invoice-reconciliation/index', [
            'sessions' => $sessions,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('invoice-reconciliation/create');
    }

    public function show(Request $request, InvoiceReconSession $invoiceReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($invoiceReconSession->user_id !== $request->user()->id, 403);

        return Inertia::render('invoice-reconciliation/show', [
            'session' => $invoiceReconSession->only([
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

    public function report(Request $request, InvoiceReconSession $invoiceReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($invoiceReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $invoiceQuery = trim((string) $request->get('invoice_query', ''));
        $zwingStatus = trim((string) $request->get('zwing_status', ''));
        $erpStatus = trim((string) $request->get('erp_status', ''));
        $difference = $request->string('difference')->toString();
        $difference = in_array($difference, ['all', 'zero', 'non_zero', 'missing_side'], true) ? $difference : 'all';
        $perPage = 100;
        $page = max(1, (int) $request->get('page', 1));
        $sessionId = $invoiceReconSession->id;

        $comparisonSql = $this->comparisonSql();
        [$filterClause, $filterParams] = $this->buildReportConstraints(
            filter: $filter,
            invoiceQuery: $invoiceQuery,
            zwingStatus: $zwingStatus,
            erpStatus: $erpStatus,
            difference: $difference,
        );

        $summary = DB::selectOne(<<<SQL
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE match_status = 'matched')           AS matched,
                COUNT(*) FILTER (WHERE match_status = 'amount_mismatch')   AS amount_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'status_mismatch')   AS status_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'zwing_only')        AS zwing_only,
                COUNT(*) FILTER (WHERE match_status = 'erp_only')          AS erp_only
            FROM ({$comparisonSql}) AS cmp
        SQL, [$sessionId]);

        $totalRows = DB::selectOne(
            "SELECT COUNT(*) AS total FROM ({$comparisonSql}) AS cmp {$filterClause}",
            array_merge([$sessionId], $filterParams),
        )->total;

        $rows = DB::select(
            "SELECT * FROM ({$comparisonSql}) AS cmp {$filterClause} ORDER BY invoice_id LIMIT ? OFFSET ?",
            array_merge([$sessionId], $filterParams, [$perPage, ($page - 1) * $perPage]),
        );

        return Inertia::render('invoice-reconciliation/report', [
            'session' => $invoiceReconSession->only(['id', 'name', 'v_id', 'status']),
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
                'zwing_status' => $zwingStatus,
                'erp_status' => $erpStatus,
                'difference' => $difference,
            ],
            'statusOptions' => $this->distinctStatusOptions($sessionId),
        ]);
    }

    public function exportReport(Request $request, InvoiceReconSession $invoiceReconSession): StreamedResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($invoiceReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $invoiceQuery = trim((string) $request->get('invoice_query', ''));
        $zwingStatus = trim((string) $request->get('zwing_status', ''));
        $erpStatus = trim((string) $request->get('erp_status', ''));
        $difference = $request->string('difference')->toString();
        $difference = in_array($difference, ['all', 'zero', 'non_zero', 'missing_side'], true) ? $difference : 'all';
        $sessionId = $invoiceReconSession->id;
        $comparisonSql = $this->comparisonSql();
        [$filterClause, $filterParams] = $this->buildReportConstraints(
            filter: $filter,
            invoiceQuery: $invoiceQuery,
            zwingStatus: $zwingStatus,
            erpStatus: $erpStatus,
            difference: $difference,
        );

        $rows = DB::select(
            "SELECT * FROM ({$comparisonSql}) AS cmp {$filterClause} ORDER BY invoice_id",
            array_merge([$sessionId], $filterParams),
        );

        $segment = $filter === 'all' ? 'all' : $filter;
        if ($invoiceQuery !== '' || $zwingStatus !== '' || $erpStatus !== '' || $difference !== 'all') {
            $segment .= '-filtered';
        }
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $invoiceReconSession->name);
        $filename = "{$slug}-{$segment}.csv";

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                'invoice_id',
                'zwing_total_amount',
                'erp_total_amount',
                'amount_difference',
                'zwing_status',
                'erp_status',
                'match_status',
            ]);

            foreach ($rows as $row) {
                $zwingAmount = $row->zwing_total_amount;
                $erpAmount = $row->erp_total_amount;
                $diff = ($zwingAmount !== null && $erpAmount !== null) ? $zwingAmount - $erpAmount : '';

                fputcsv($handle, [
                    $row->invoice_id,
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

    public function uploadCsv(StoreInvoiceReconciliationCsvRequest $request): RedirectResponse
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
            $zwingPath = $zwing->store('reconciliation/invoice/zwing', 'local');
            $zwingRowCount = $this->countLines($zwing->getRealPath());
        }

        if ($erp !== null) {
            $erpPath = $erp->store('reconciliation/invoice/erp', 'local');
            $erpRowCount = $this->countLines($erp->getRealPath());
        }

        /** @var User $user */
        $user = $request->user();

        $session = InvoiceReconSession::create([
            'user_id' => $user->id,
            'name' => $request->string('name')->toString(),
            'v_id' => $request->integer('v_id'),
            'zwing_file_name' => $zwing?->getClientOriginalName(),
            'erp_file_name' => $erp?->getClientOriginalName(),
            'zwing_row_count' => $zwingRowCount,
            'erp_row_count' => $erpRowCount,
            'status' => 'pending',
        ]);

        ParseInvoiceReconciliationCsv::dispatch(
            sessionId: $session->id,
            zwingPath: $zwingPath !== '' ? storage_path("app/private/{$zwingPath}") : '',
            erpPath: $erpPath !== '' ? storage_path("app/private/{$erpPath}") : '',
        );

        return redirect()->route('invoice-reconciliation.show', $session);
    }

    public function destroy(Request $request, InvoiceReconSession $invoiceReconSession): RedirectResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($invoiceReconSession->user_id !== $request->user()->id, 403);

        $invoiceReconSession->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Reconciliation session ":name" deleted.', ['name' => $invoiceReconSession->name]),
        ]);

        return redirect()->route('invoice-reconciliation.index');
    }

    private function comparisonSql(): string
    {
        return <<<'SQL'
            SELECT
                COALESCE(z.invoice_id, e.invoice_id) AS invoice_id,
                z.total_amount                        AS zwing_total_amount,
                e.total_amount                        AS erp_total_amount,
                z.status                              AS zwing_status,
                e.status                              AS erp_status,
                CASE
                    WHEN z.id IS NULL THEN 'erp_only'
                    WHEN e.id IS NULL THEN 'zwing_only'
                    WHEN z.total_amount = e.total_amount AND z.status = e.status THEN 'matched'
                    WHEN z.total_amount != e.total_amount THEN 'amount_mismatch'
                    ELSE 'status_mismatch'
                END AS match_status
            FROM zwing_invoice_reconsile z
            FULL OUTER JOIN erp_invoice_reconsile e
                ON  z.session_id = e.session_id
                AND z.invoice_id = e.invoice_id
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
     * @return array{0: string, 1: array<int, mixed>}
     */
    /**
     * @return array{zwing: array<int, string>, erp: array<int, string>}
     */
    private function distinctStatusOptions(int $sessionId): array
    {
        $zwing = DB::table('zwing_invoice_reconsile')
            ->where('session_id', $sessionId)
            ->whereNotNull('status')
            ->where('status', '!=', '')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->all();

        $erp = DB::table('erp_invoice_reconsile')
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

    private function buildReportConstraints(
        string $filter,
        string $invoiceQuery,
        string $zwingStatus,
        string $erpStatus,
        string $difference,
    ): array {
        $clauses = [];
        $params = [];

        if ($filter !== 'all') {
            $clauses[] = 'match_status = ?';
            $params[] = $filter;
        }

        if ($invoiceQuery !== '') {
            $clauses[] = 'invoice_id ILIKE ?';
            $params[] = "%{$invoiceQuery}%";
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
            $clauses[] = 'zwing_total_amount IS NOT NULL AND erp_total_amount IS NOT NULL AND zwing_total_amount = erp_total_amount';
        } elseif ($difference === 'non_zero') {
            $clauses[] = 'zwing_total_amount IS NOT NULL AND erp_total_amount IS NOT NULL AND zwing_total_amount <> erp_total_amount';
        } elseif ($difference === 'missing_side') {
            $clauses[] = '(zwing_total_amount IS NULL OR erp_total_amount IS NULL)';
        }

        if ($clauses === []) {
            return ['', []];
        }

        return ['WHERE '.implode(' AND ', $clauses), $params];
    }
}
