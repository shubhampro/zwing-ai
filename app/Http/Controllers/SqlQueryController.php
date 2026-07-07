<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedSqlQueryRequest;
use App\Http\Requests\UpdateSavedSqlQueryRequest;
use App\Models\SavedSqlQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SqlQueryController extends Controller
{
    /** @var list<string> */
    private const SCHEMA_TABLES = [
        'grn',
        'grn_list',
        'grt_headers',
        'grt_details',
        'stock_in',
        'stock_out',
        'stock_point_transfers',
        'stock_point_transfer_details',
        'zwing_stock_reconsile',
        'zwing_invoice_reconsile',
        'erp_invoice_reconsile',
        'zwing_expense_cash_reconsile',
        'zwing_parts',
        'erp_parts',
    ];

    public function index(Request $request): InertiaResponse
    {
        abort_if($request->user() === null, 403);

        $queries = SavedSqlQuery::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'description', 'sql', 'updated_at']);

        return Inertia::render('sql-queries/index', [
            'queries' => $queries->map(fn (SavedSqlQuery $query) => [
                'id' => $query->id,
                'title' => $query->title,
                'description' => $query->description,
                'sql' => $query->sql,
                'updated_at' => $query->updated_at?->toIso8601String(),
            ]),
            'schemaTables' => self::SCHEMA_TABLES,
        ]);
    }

    public function store(StoreSavedSqlQueryRequest $request): RedirectResponse
    {
        SavedSqlQuery::query()->create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Query saved.');
    }

    public function update(UpdateSavedSqlQueryRequest $request, SavedSqlQuery $savedSqlQuery): RedirectResponse
    {
        abort_if($savedSqlQuery->user_id !== $request->user()->id, 403);

        $savedSqlQuery->update($request->validated());

        return back()->with('success', 'Query updated.');
    }

    public function destroy(Request $request, SavedSqlQuery $savedSqlQuery): RedirectResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($savedSqlQuery->user_id !== $request->user()->id, 403);

        $savedSqlQuery->delete();

        return back()->with('success', 'Query deleted.');
    }

    public function export(Request $request, SavedSqlQuery $savedSqlQuery): StreamedResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($savedSqlQuery->user_id !== $request->user()->id, 403);

        $filename = Str::slug($savedSqlQuery->title).'.sql';

        return response()->streamDownload(function () use ($savedSqlQuery): void {
            if ($savedSqlQuery->description) {
                echo '-- '.$savedSqlQuery->description."\n\n";
            }

            echo $savedSqlQuery->sql;
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        abort_if($request->user() === null, 403);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:512', 'mimes:sql,txt'],
        ]);

        $contents = file_get_contents($validated['file']->getRealPath());

        if ($contents === false) {
            return response()->json([
                'success' => false,
                'message' => 'Could not read the uploaded file.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json([
            'success' => true,
            'sql' => trim($contents),
        ]);
    }
}
