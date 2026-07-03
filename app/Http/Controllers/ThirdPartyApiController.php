<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreThirdPartyApiRequest;
use App\Http\Requests\UpdateThirdPartyApiRequest;
use App\HttpMethod;
use App\Models\ThirdPartyApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ThirdPartyApiController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $apis = ThirdPartyApi::query()
            ->withCount('organizationConnections')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'path',
                'method',
                'params',
                'auth_header_name',
                'is_active',
                'created_at',
            ]);

        return Inertia::render('third-party-apis/index', [
            'apis' => $apis->map(fn (ThirdPartyApi $api) => [
                'id' => $api->id,
                'name' => $api->name,
                'path' => $api->path,
                'method' => $api->method->value,
                'param_count' => count($api->params ?? []),
                'auth_header_name' => $api->auth_header_name,
                'is_active' => $api->is_active,
                'connection_count' => $api->organization_connections_count,
                'created_at' => $api->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('third-party-apis/create', [
            'httpMethods' => HttpMethod::options(),
        ]);
    }

    public function store(StoreThirdPartyApiRequest $request): RedirectResponse
    {
        ThirdPartyApi::create([
            'name' => $request->string('name')->toString(),
            'path' => $request->string('path')->toString(),
            'method' => $request->string('method')->toString(),
            'params' => $this->normalizeParams($request->input('params', [])),
            'auth_header_name' => $request->string('auth_header_name')->toString(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Third party API template added.'),
        ]);

        return redirect()->route('third-party-apis.index');
    }

    public function edit(Request $request, ThirdPartyApi $thirdPartyApi): Response
    {
        abort_if($request->user() === null, 403);

        $thirdPartyApi->load([
            'organizationConnections.organization:id,name,ba_code',
        ]);

        return Inertia::render('third-party-apis/edit', [
            'api' => [
                'id' => $thirdPartyApi->id,
                'name' => $thirdPartyApi->name,
                'path' => $thirdPartyApi->path,
                'method' => $thirdPartyApi->method->value,
                'params' => $thirdPartyApi->params ?? [],
                'auth_header_name' => $thirdPartyApi->auth_header_name,
                'is_active' => $thirdPartyApi->is_active,
            ],
            'connections' => $thirdPartyApi->organizationConnections->map(fn ($connection) => [
                'id' => $connection->id,
                'organization_id' => $connection->organization_id,
                'base_url' => $connection->base_url,
                'is_active' => $connection->is_active,
                'organization' => $connection->organization?->only(['id', 'name', 'ba_code']),
            ]),
            'httpMethods' => HttpMethod::options(),
        ]);
    }

    public function update(UpdateThirdPartyApiRequest $request, ThirdPartyApi $thirdPartyApi): RedirectResponse
    {
        $thirdPartyApi->update([
            'name' => $request->string('name')->toString(),
            'path' => $request->string('path')->toString(),
            'method' => $request->string('method')->toString(),
            'params' => $this->normalizeParams($request->input('params', [])),
            'auth_header_name' => $request->string('auth_header_name')->toString(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Third party API template updated.'),
        ]);

        return redirect()->route('third-party-apis.edit', $thirdPartyApi);
    }

    public function destroy(Request $request, ThirdPartyApi $thirdPartyApi): RedirectResponse
    {
        abort_if($request->user() === null, 403);

        $thirdPartyApi->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Third party API template removed.'),
        ]);

        return redirect()->route('third-party-apis.index');
    }

    /**
     * @param  array<int, array<string, mixed>>  $params
     * @return array<int, array<string, mixed>>
     */
    private function normalizeParams(array $params): array
    {
        return collect($params)
            ->filter(fn (array $param) => filled($param['key'] ?? null))
            ->map(fn (array $param) => [
                'key' => trim((string) $param['key']),
                'csv_column' => filled($param['csv_column'] ?? null)
                    ? trim((string) $param['csv_column'])
                    : trim((string) $param['key']),
                'required' => (bool) ($param['required'] ?? false),
                'default' => filled($param['default'] ?? null) ? (string) $param['default'] : null,
            ])
            ->values()
            ->all();
    }
}
