<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationApiConnectionRequest;
use App\Http\Requests\StoreOrganizationThirdPartyApiRequest;
use App\Http\Requests\UpdateOrganizationApiConnectionRequest;
use App\Http\Requests\UpdateOrganizationThirdPartyApiRequest;
use App\Models\Organization;
use App\Models\OrganizationThirdPartyApi;
use App\Models\ThirdPartyApi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrganizationThirdPartyApiController extends Controller
{
    public function storeForOrganization(
        StoreOrganizationApiConnectionRequest $request,
        Organization $organization,
    ): RedirectResponse {
        $organization->organizationConnections()->create([
            'third_party_api_id' => $request->integer('third_party_api_id'),
            'base_url' => rtrim($request->string('base_url')->toString(), '/'),
            'auth_token' => $request->string('auth_token')->toString(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('API connection saved for organization.'),
        ]);

        return redirect()->route('organizations.show', $organization);
    }

    public function updateForOrganization(
        UpdateOrganizationApiConnectionRequest $request,
        Organization $organization,
        OrganizationThirdPartyApi $organizationThirdPartyApi,
    ): RedirectResponse {
        abort_if($organizationThirdPartyApi->organization_id !== $organization->id, 404);

        $data = [
            'base_url' => rtrim($request->string('base_url')->toString(), '/'),
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('auth_token')) {
            $data['auth_token'] = $request->string('auth_token')->toString();
        }

        $organizationThirdPartyApi->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('API connection updated.'),
        ]);

        return redirect()->route('organizations.show', $organization);
    }

    public function destroyForOrganization(
        Request $request,
        Organization $organization,
        OrganizationThirdPartyApi $organizationThirdPartyApi,
    ): RedirectResponse {
        abort_if($request->user() === null, 403);
        abort_if($organizationThirdPartyApi->organization_id !== $organization->id, 404);

        $organizationThirdPartyApi->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('API connection removed.'),
        ]);

        return redirect()->route('organizations.show', $organization);
    }

    public function store(StoreOrganizationThirdPartyApiRequest $request, ThirdPartyApi $thirdPartyApi): RedirectResponse
    {
        $thirdPartyApi->organizationConnections()->create([
            'organization_id' => $request->integer('organization_id'),
            'base_url' => rtrim($request->string('base_url')->toString(), '/'),
            'auth_token' => $request->string('auth_token')->toString(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization API connection added.'),
        ]);

        return redirect()->route('third-party-apis.edit', $thirdPartyApi);
    }

    public function update(
        UpdateOrganizationThirdPartyApiRequest $request,
        ThirdPartyApi $thirdPartyApi,
        OrganizationThirdPartyApi $organizationThirdPartyApi,
    ): RedirectResponse {
        abort_if($organizationThirdPartyApi->third_party_api_id !== $thirdPartyApi->id, 404);

        $data = [
            'organization_id' => $request->integer('organization_id'),
            'base_url' => rtrim($request->string('base_url')->toString(), '/'),
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('auth_token')) {
            $data['auth_token'] = $request->string('auth_token')->toString();
        }

        $organizationThirdPartyApi->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization API connection updated.'),
        ]);

        return redirect()->route('third-party-apis.edit', $thirdPartyApi);
    }

    public function destroy(
        Request $request,
        ThirdPartyApi $thirdPartyApi,
        OrganizationThirdPartyApi $organizationThirdPartyApi,
    ): RedirectResponse {
        abort_if($request->user() === null, 403);
        abort_if($organizationThirdPartyApi->third_party_api_id !== $thirdPartyApi->id, 404);

        $organizationThirdPartyApi->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Organization API connection removed.'),
        ]);

        return redirect()->route('third-party-apis.edit', $thirdPartyApi);
    }
}
