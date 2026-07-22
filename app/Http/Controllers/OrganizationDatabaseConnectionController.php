<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseConnectionType;
use App\Enums\ExternalQueryJobType;
use App\Http\Requests\StoreOrganizationDatabaseConnectionRequest;
use App\Http\Requests\UpdateOrganizationDatabaseConnectionRequest;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use App\Services\ExternalQueryDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationDatabaseConnectionController extends Controller
{
    public function index(Request $request, Organization $organization): Response
    {
        $this->authorize('viewAny', OrganizationDatabaseConnection::class);

        $databaseConnections = $organization->databaseConnections()
            ->orderBy('type')
            ->get()
            ->map(fn (OrganizationDatabaseConnection $connection) => [
                'id' => $connection->id,
                'type' => $connection->type->value,
                'username' => $connection->username,
                'host' => $connection->host,
                'port' => $connection->port,
                'is_active' => $connection->is_active,
            ]);

        return Inertia::render('organizations/database-connections', [
            'organization' => $organization->only(['id', 'name', 'ba_code', 'vendor_id']),
            'databaseConnections' => $databaseConnections,
            'databaseConnectionTypes' => DatabaseConnectionType::values(),
        ]);
    }

    public function store(
        StoreOrganizationDatabaseConnectionRequest $request,
        Organization $organization,
    ): RedirectResponse {
        $validated = $request->validated();

        $organization->databaseConnections()->create([
            'type' => $validated['type'],
            'database_name' => $validated['database_name'],
            'username' => $validated['username'],
            'password' => $validated['password'],
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Database connection saved for organization.'),
        ]);

        return redirect()->route('organizations.database-connections.index', $organization);
    }

    public function update(
        UpdateOrganizationDatabaseConnectionRequest $request,
        Organization $organization,
        OrganizationDatabaseConnection $organizationDatabaseConnection,
    ): RedirectResponse {
        abort_if($organizationDatabaseConnection->organization_id !== $organization->id, 404);

        $validated = $request->validated();

        $data = [
            'type' => $validated['type'],
            'username' => $validated['username'],
            'host' => $validated['host'] ?? null,
            'port' => $validated['port'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->filled('database_name')) {
            $data['database_name'] = $validated['database_name'];
        }

        if ($request->filled('password')) {
            $data['password'] = $validated['password'];
        }

        $organizationDatabaseConnection->update($data);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Database connection updated.'),
        ]);

        return redirect()->route('organizations.database-connections.index', $organization);
    }

    public function destroy(
        Request $request,
        Organization $organization,
        OrganizationDatabaseConnection $organizationDatabaseConnection,
    ): RedirectResponse {
        abort_if($organizationDatabaseConnection->organization_id !== $organization->id, 404);

        $this->authorize('delete', $organizationDatabaseConnection);

        $organizationDatabaseConnection->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Database connection removed.'),
        ]);

        return redirect()->route('organizations.database-connections.index', $organization);
    }

    public function test(
        Request $request,
        Organization $organization,
        OrganizationDatabaseConnection $organizationDatabaseConnection,
        ExternalQueryDispatcher $dispatcher,
    ): JsonResponse {
        abort_if($organizationDatabaseConnection->organization_id !== $organization->id, 404);

        $this->authorize('view', $organizationDatabaseConnection);

        $log = $dispatcher->dispatch(
            jobType: ExternalQueryJobType::TestOrgDbConnection,
            user: $request->user(),
            context: [
                'organization_id' => $organization->id,
                'organization_database_connection_id' => $organizationDatabaseConnection->id,
            ],
        );

        return response()->json($log->toPollPayload(), 202);
    }
}
