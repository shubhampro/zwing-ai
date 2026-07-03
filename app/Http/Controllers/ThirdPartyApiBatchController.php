<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreThirdPartyApiBatchCsvRequest;
use App\Jobs\ParseThirdPartyApiBatchCsv;
use App\Models\Organization;
use App\Models\OrganizationThirdPartyApi;
use App\Models\ThirdPartyApiBatch;
use App\Models\ThirdPartyApiBatchItem;
use App\Models\User;
use App\Services\ThirdParty\ProcessThirdPartyApiBatchItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ThirdPartyApiBatchController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $batches = ThirdPartyApiBatch::query()
            ->with([
                'organizationThirdPartyApi:id,organization_id,third_party_api_id,base_url',
                'organizationThirdPartyApi.thirdPartyApi:id,name,method',
                'organizationThirdPartyApi.organization:id,name,ba_code',
            ])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get([
                'id',
                'organization_third_party_api_id',
                'name',
                'file_name',
                'row_count',
                'processed_count',
                'success_count',
                'failed_count',
                'skipped_count',
                'status',
                'completed_at',
                'created_at',
            ]);

        return Inertia::render('third-party-api-batches/index', [
            'batches' => $batches,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $organizations = Organization::query()
            ->orderBy('name')
            ->get(['id', 'name', 'ba_code']);

        $connections = OrganizationThirdPartyApi::query()
            ->with(['organization:id,name,ba_code', 'thirdPartyApi:id,name,method,params'])
            ->where('is_active', true)
            ->whereHas('thirdPartyApi', fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->get();

        return Inertia::render('third-party-api-batches/create', [
            'organizations' => $organizations,
            'connections' => $connections->map(fn (OrganizationThirdPartyApi $connection) => [
                'id' => $connection->id,
                'organization_id' => $connection->organization_id,
                'api_name' => $connection->thirdPartyApi->name,
                'method' => $connection->thirdPartyApi->method->value,
                'params' => collect($connection->thirdPartyApi->params ?? [])->map(fn (array $param) => [
                    'key' => $param['key'] ?? '',
                    'csv_column' => $param['csv_column'] ?? $param['key'] ?? '',
                    'required' => (bool) ($param['required'] ?? false),
                ])->values(),
            ]),
        ]);
    }

    public function uploadCsv(StoreThirdPartyApiBatchCsvRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $csv = $request->file('csv');
        $path = $csv->store('third-party-api-batches/csv', 'local');

        $batch = ThirdPartyApiBatch::create([
            'user_id' => $user->id,
            'organization_third_party_api_id' => $request->integer('organization_third_party_api_id'),
            'name' => $request->string('name')->toString(),
            'file_name' => $csv->getClientOriginalName(),
            'defaults' => $request->input('defaults', []),
            'status' => 'pending',
        ]);

        ParseThirdPartyApiBatchCsv::dispatch(
            batchId: $batch->id,
            csvPath: storage_path("app/private/{$path}"),
        );

        return redirect()->route('third-party-api-batches.show', $batch);
    }

    public function show(Request $request, ThirdPartyApiBatch $thirdPartyApiBatch): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($thirdPartyApiBatch->user_id !== $request->user()->id, 403);

        $thirdPartyApiBatch->load([
            'organizationThirdPartyApi.organization:id,name,ba_code',
            'organizationThirdPartyApi.thirdPartyApi:id,name,path,method,params',
        ]);

        $filter = $request->string('filter')->toString();
        $filter = in_array($filter, ['all', 'success', 'failed', 'skipped', 'pending'], true) ? $filter : 'all';
        $search = trim((string) $request->get('search', ''));
        $perPage = 100;
        $page = max(1, (int) $request->get('page', 1));

        $paramKeys = collect($thirdPartyApiBatch->organizationThirdPartyApi?->thirdPartyApi?->params ?? [])
            ->pluck('key')
            ->filter()
            ->values()
            ->all();

        $itemsQuery = ThirdPartyApiBatchItem::query()
            ->where('third_party_api_batch_id', $thirdPartyApiBatch->id);

        $summary = [
            'total' => (clone $itemsQuery)->count(),
            'success' => (clone $itemsQuery)->where('status', 'success')->count(),
            'failed' => (clone $itemsQuery)->where('status', 'failed')->count(),
            'skipped' => (clone $itemsQuery)->where('status', 'skipped')->count(),
            'pending' => (clone $itemsQuery)->where('status', 'pending')->count(),
        ];

        $query = ThirdPartyApiBatchItem::query()
            ->where('third_party_api_batch_id', $thirdPartyApiBatch->id);

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        if ($search !== '' && $paramKeys !== []) {
            $query->where(function ($builder) use ($search, $paramKeys): void {
                foreach ($paramKeys as $key) {
                    $builder->orWhereRaw('payload->>? ilike ?', [$key, "%{$search}%"]);
                }
            });
        }

        $totalRows = (clone $query)->count();

        $rows = $query->with(['attempts' => fn ($attemptQuery) => $attemptQuery->orderByDesc('attempt_number')])
            ->orderBy('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get([
                'id',
                'payload',
                'status',
                'http_status',
                'response_body',
                'error_message',
                'processed_at',
            ]);

        return Inertia::render('third-party-api-batches/show', [
            'batch' => $thirdPartyApiBatch->only([
                'id',
                'name',
                'file_name',
                'row_count',
                'processed_count',
                'success_count',
                'failed_count',
                'skipped_count',
                'defaults',
                'status',
                'completed_at',
                'created_at',
                'organizationThirdPartyApi',
            ]),
            'paramKeys' => $paramKeys,
            'summary' => $summary,
            'rows' => $rows,
            'pagination' => [
                'total' => $totalRows,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) max(1, ceil($totalRows / $perPage)),
            ],
            'filter' => $filter,
            'search' => $search,
        ]);
    }

    public function report(Request $request, ThirdPartyApiBatch $thirdPartyApiBatch): RedirectResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($thirdPartyApiBatch->user_id !== $request->user()->id, 403);

        return redirect()->route('third-party-api-batches.show', [
            'thirdPartyApiBatch' => $thirdPartyApiBatch,
            ...$request->query(),
        ]);
    }

    public function retryItem(
        Request $request,
        ThirdPartyApiBatch $thirdPartyApiBatch,
        ThirdPartyApiBatchItem $thirdPartyApiBatchItem,
        ProcessThirdPartyApiBatchItem $processor,
    ): RedirectResponse {
        abort_if($request->user() === null, 403);
        abort_if($thirdPartyApiBatch->user_id !== $request->user()->id, 403);
        abort_if($thirdPartyApiBatchItem->third_party_api_batch_id !== $thirdPartyApiBatch->id, 404);

        $thirdPartyApiBatch->load('organizationThirdPartyApi.thirdPartyApi');
        $processor->process($thirdPartyApiBatchItem, $thirdPartyApiBatch->organizationThirdPartyApi);
        $thirdPartyApiBatch->refreshCounts();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Item retried.'),
        ]);

        return back();
    }

    public function retryFailed(
        Request $request,
        ThirdPartyApiBatch $thirdPartyApiBatch,
        ProcessThirdPartyApiBatchItem $processor,
    ): RedirectResponse {
        abort_if($request->user() === null, 403);
        abort_if($thirdPartyApiBatch->user_id !== $request->user()->id, 403);

        $thirdPartyApiBatch->load('organizationThirdPartyApi.thirdPartyApi');
        $connection = $thirdPartyApiBatch->organizationThirdPartyApi;
        $delayMs = max(0, (int) config('third_party.request_delay_ms'));

        ThirdPartyApiBatchItem::query()
            ->where('third_party_api_batch_id', $thirdPartyApiBatch->id)
            ->where('status', 'failed')
            ->orderBy('id')
            ->each(function (ThirdPartyApiBatchItem $item) use ($processor, $connection, $delayMs): void {
                $processor->process($item, $connection);

                if ($delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            });

        $thirdPartyApiBatch->refreshCounts();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Failed items retried.'),
        ]);

        return back();
    }

    public function exportReport(Request $request, ThirdPartyApiBatch $thirdPartyApiBatch): StreamedResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($thirdPartyApiBatch->user_id !== $request->user()->id, 403);

        $thirdPartyApiBatch->load('organizationThirdPartyApi.thirdPartyApi:id,params');

        $filter = $request->string('filter')->toString();
        $filter = in_array($filter, ['all', 'success', 'failed', 'skipped', 'pending'], true) ? $filter : 'all';
        $search = trim((string) $request->get('search', ''));

        $paramKeys = collect($thirdPartyApiBatch->organizationThirdPartyApi?->thirdPartyApi?->params ?? [])
            ->pluck('key')
            ->filter()
            ->values()
            ->all();

        $query = ThirdPartyApiBatchItem::query()
            ->where('third_party_api_batch_id', $thirdPartyApiBatch->id);

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        if ($search !== '' && $paramKeys !== []) {
            $query->where(function ($builder) use ($search, $paramKeys): void {
                foreach ($paramKeys as $key) {
                    $builder->orWhereRaw('payload->>? ilike ?', [$key, "%{$search}%"]);
                }
            });
        }

        $rows = $query->with('attempts')->orderBy('id')->get();

        $slug = preg_replace('/[^a-z0-9]+/i', '-', $thirdPartyApiBatch->name);
        $filename = "{$slug}-batch-report.csv";

        return response()->streamDownload(function () use ($rows, $paramKeys): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, [
                ...$paramKeys,
                'status',
                'http_status',
                'error_message',
                'request_url',
                'response_body',
                'processed_at',
            ]);

            foreach ($rows as $row) {
                $payload = $row->payload ?? [];
                $latestAttempt = $row->attempts()->orderByDesc('attempt_number')->first();

                fputcsv($handle, [
                    ...array_map(fn (string $key) => $payload[$key] ?? '', $paramKeys),
                    $row->status,
                    $row->http_status,
                    $row->error_message,
                    $latestAttempt?->request_url,
                    $row->response_body,
                    $row->processed_at?->toIso8601String(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, ThirdPartyApiBatch $thirdPartyApiBatch): RedirectResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($thirdPartyApiBatch->user_id !== $request->user()->id, 403);

        $thirdPartyApiBatch->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Batch deleted.'),
        ]);

        return redirect()->route('third-party-api-batches.index');
    }
}
