<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttachZwingVendorRequest;
use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateFromZwingVendorRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Models\OrganizationThirdPartyApi;
use App\Models\ThirdPartyApi;
use App\Services\ZwingVendorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = Organization::query()
            ->withCount('organizationConnections')
            ->latest()
            ->get(['id', 'name', 'ba_code', 'vendor_id', 'created_at']);

        return Inertia::render('organizations/index', [
            'organizations' => $organizations,
        ]);
    }

    public function zwingVendors(Request $request, ZwingVendorService $zwingVendors): JsonResponse
    {
        $this->authorize('attachZwing', Organization::class);

        $attachedVendorIds = Organization::query()
            ->pluck('vendor_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        return response()->json([
            'vendors' => $zwingVendors->list(),
            'attached_vendor_ids' => $attachedVendorIds,
        ]);
    }

    public function attachZwingVendor(
        AttachZwingVendorRequest $request,
        ZwingVendorService $zwingVendors,
    ): RedirectResponse {
        $vendorId = $request->integer('vendor_id');
        $vendor = $zwingVendors->find($vendorId);

        if ($vendor === null) {
            throw ValidationException::withMessages([
                'vendor_id' => __('Vendor not found in Zwing Master.'),
            ]);
        }

        if (Organization::query()->where('ba_code', $vendor['ba_code'])->exists()) {
            throw ValidationException::withMessages([
                'vendor_id' => __('An organization with BA code :code already exists.', [
                    'code' => $vendor['ba_code'],
                ]),
            ]);
        }

        Organization::create([
            'name' => $vendor['name'],
            'ba_code' => $vendor['ba_code'],
            'vendor_id' => $vendor['id'],
            'db_name' => $vendor['db_name'] !== '' ? $vendor['db_name'] : null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization attached from Zwing Master successfully.'),
        ]);

        return redirect()->route('organizations.index');
    }

    public function updateFromZwingVendor(
        UpdateFromZwingVendorRequest $request,
        ZwingVendorService $zwingVendors,
    ): RedirectResponse {
        $vendorId = $request->integer('vendor_id');
        $vendor = $zwingVendors->find($vendorId);

        if ($vendor === null) {
            throw ValidationException::withMessages([
                'vendor_id' => __('Vendor not found in Zwing Master.'),
            ]);
        }

        $organization = Organization::query()
            ->where('vendor_id', $vendorId)
            ->firstOrFail();

        if (Organization::query()
            ->where('ba_code', $vendor['ba_code'])
            ->where('id', '!=', $organization->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'vendor_id' => __('An organization with BA code :code already exists.', [
                    'code' => $vendor['ba_code'],
                ]),
            ]);
        }

        $organization->update([
            'name' => $vendor['name'],
            'ba_code' => $vendor['ba_code'],
            'db_name' => $vendor['db_name'] !== '' ? $vendor['db_name'] : null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization updated from Zwing Master successfully.'),
        ]);

        return redirect()->route('organizations.index');
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Organization::class);

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
        $this->authorize('view', $organization);

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
            'canManageDatabaseConnections' => $request->user()?->can('viewAny', OrganizationDatabaseConnection::class) ?? false,
        ]);
    }

    public function edit(Request $request, Organization $organization): Response
    {
        $this->authorize('update', $organization);

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
        $this->authorize('delete', $organization);

        $organization->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization deleted successfully.'),
        ]);

        return redirect()->route('organizations.index');
    }
}
