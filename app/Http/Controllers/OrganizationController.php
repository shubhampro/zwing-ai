<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
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
