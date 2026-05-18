<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStockReconciliationCsvRequest;
use App\Jobs\ParseStockReconciliationCsv;
use App\Models\StockReconSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function show(Request $request, StockReconSession $stockReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($stockReconSession->user_id !== $request->user()->id, 403);

        return Inertia::render('stock-transaction-reconciliation/show', [
            'session' => $stockReconSession->only([
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

    public function report(Request $request, StockReconSession $stockReconSession): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($stockReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $perPage = 100;
        $page = max(1, (int) $request->get('page', 1));
        $sessionId = $stockReconSession->id;

        $comparisonSql = $this->comparisonSql();

        $summary = DB::selectOne(<<<SQL
            SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE match_status = 'matched')      AS matched,
                COUNT(*) FILTER (WHERE match_status = 'qty_mismatch') AS qty_mismatch,
                COUNT(*) FILTER (WHERE match_status = 'zwing_only')   AS zwing_only,
                COUNT(*) FILTER (WHERE match_status = 'erp_only')     AS erp_only
            FROM ({$comparisonSql}) AS cmp
        SQL, [$sessionId]);

        $filterClause = $filter !== 'all' ? 'WHERE match_status = ?' : '';
        $filterParams = $filter !== 'all' ? [$sessionId, $filter] : [$sessionId];

        $totalRows = DB::selectOne(
            "SELECT COUNT(*) AS total FROM ({$comparisonSql}) AS cmp {$filterClause}",
            $filterParams,
        )->total;

        $rows = DB::select(
            "SELECT * FROM ({$comparisonSql}) AS cmp {$filterClause} ORDER BY site_code, icode LIMIT ? OFFSET ?",
            array_merge($filterParams, [$perPage, ($page - 1) * $perPage]),
        );

        return Inertia::render('stock-transaction-reconciliation/report', [
            'session' => $stockReconSession->only(['id', 'name', 'v_id', 'status']),
            'summary' => $summary,
            'rows' => $rows,
            'pagination' => [
                'total' => (int) $totalRows,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil((int) $totalRows / $perPage),
            ],
            'filter' => $filter,
        ]);
    }

    public function exportReport(Request $request, StockReconSession $stockReconSession): StreamedResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($stockReconSession->user_id !== $request->user()->id, 403);

        $filter = $request->get('filter', 'all');
        $sessionId = $stockReconSession->id;
        $comparisonSql = $this->comparisonSql();
        $filterClause = $filter !== 'all' ? 'WHERE match_status = ?' : '';
        $filterParams = $filter !== 'all' ? [$sessionId, $filter] : [$sessionId];

        $rows = DB::select(
            "SELECT * FROM ({$comparisonSql}) AS cmp {$filterClause} ORDER BY site_code, icode",
            $filterParams,
        );

        $segment = $filter === 'all' ? 'all' : $filter;
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $stockReconSession->name);
        $filename = "{$slug}-{$segment}.csv";

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['site_code', 'icode', 'batch_no', 'sprefcode', 'stock_point_name', 'zwing_qty', 'erp_qty', 'difference', 'status']);

            foreach ($rows as $row) {
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

        $zwingPath = '';
        $erpPath = '';
        $zwingRowCount = null;
        $erpRowCount = null;

        if ($zwing !== null) {
            $zwingPath = $zwing->store('reconciliation/zwing', 'local');
            $zwingRowCount = $this->countLines($zwing->getRealPath());
        }

        if ($erp !== null) {
            $erpPath = $erp->store('reconciliation/erp', 'local');
            $erpRowCount = $this->countLines($erp->getRealPath());
        }

        /** @var User $user */
        $user = $request->user();

        $session = StockReconSession::create([
            'user_id' => $user->id,
            'name' => $request->string('name')->toString(),
            'v_id' => $request->integer('v_id'),
            'zwing_file_name' => $zwing?->getClientOriginalName(),
            'erp_file_name' => $erp?->getClientOriginalName(),
            'zwing_row_count' => $zwingRowCount,
            'erp_row_count' => $erpRowCount,
            'status' => 'pending',
        ]);

        ParseStockReconciliationCsv::dispatch(
            sessionId: $session->id,
            zwingPath: $zwingPath !== '' ? storage_path("app/private/{$zwingPath}") : '',
            erpPath: $erpPath !== '' ? storage_path("app/private/{$erpPath}") : '',
        );

        return redirect()->route('stock-transaction-reconciliation.show', $session);
    }

    public function destroy(Request $request, StockReconSession $stockReconSession): RedirectResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($stockReconSession->user_id !== $request->user()->id, 403);

        $stockReconSession->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Reconciliation session ":name" deleted.', ['name' => $stockReconSession->name]),
        ]);

        return redirect()->route('stock-transaction-reconciliation.index');
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
            FROM zwing_stock_reconsile z
            FULL OUTER JOIN erp_stock_reconsile e
                ON  z.session_id = e.session_id
                AND z.site_code  = e.site_code
                AND z.icode      = e.icode
                AND z.batch_no   = e.batch_no
                AND z.sprefcode  = e.sprefcode
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

        /** Subtract 1 for the header row */
        return max(0, $count - 1);
    }
}
