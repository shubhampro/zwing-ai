<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\OrganizationThirdPartyApi;
use App\Models\ThirdPartyApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $organizations = Organization::query()
            ->withCount('organizationConnections')
            ->latest()
            ->get(['id', 'name', 'ba_code', 'vendor_id', 'created_at']);

        return Inertia::render('organizations/index', [
            'organizations' => $organizations,
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('organizations/create');
    }

    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        Organization::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization created successfully.'),
        ]);

        return redirect()->route('organizations.index');
    }

    public function show(Request $request, Organization $organization): Response
    {
        abort_if($request->user() === null, 403);

        $connections = OrganizationThirdPartyApi::query()
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy('third_party_api_id');

        $apiApps = ThirdPartyApi::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'path', 'method', 'auth_header_name', 'params'])
            ->map(fn (ThirdPartyApi $api) => [
                'id' => $api->id,
                'name' => $api->name,
                'path' => $api->path,
                'method' => $api->method->value,
                'auth_header_name' => $api->auth_header_name,
                'param_count' => count($api->params ?? []),
                'connection' => $connections->get($api->id) ? [
                    'id' => $connections->get($api->id)->id,
                    'base_url' => $connections->get($api->id)->base_url,
                    'is_active' => $connections->get($api->id)->is_active,
                ] : null,
            ]);

        return Inertia::render('organizations/show', [
            'organization' => $organization->only(['id', 'name', 'ba_code', 'vendor_id']),
            'apiApps' => $apiApps,
        ]);
    }

    public function edit(Request $request, Organization $organization): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('organizations/edit', [
            'organization' => $organization->only(['id', 'name', 'ba_code', 'vendor_id']),
        ]);
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization updated successfully.'),
        ]);

        return redirect()->route('organizations.index');
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        abort_if($request->user() === null, 403);

        // $organization->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization deleted successfully.'),
        ]);

        return redirect()->route('organizations.index');
    }
}
