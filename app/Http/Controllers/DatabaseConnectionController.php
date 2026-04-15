<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDatabaseConnectionRequest;
use App\Http\Requests\UpdateDatabaseConnectionRequest;
use App\Models\DatabaseConnection;
use App\Models\DatabaseConnectionLog;
use App\Services\DatabaseConnectionChangeLogger;
use App\Services\DatabaseConnectionRegistrar;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DatabaseConnectionController extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        $this->authorize('viewAny', DatabaseConnection::class);

        $connections = DatabaseConnection::query()
            ->latest('id')
            ->get()
            ->map(fn (DatabaseConnection $c) => [
                'id' => $c->id,
                'slug' => $c->slug,
                'connection_group' => $c->connection_group,
                'driver' => $c->driver->value,
                'access_mode' => $c->access_mode->value,
                'label' => $c->label,
                'is_active' => $c->is_active,
                'writes_enabled' => $c->writes_enabled,
                'enforce_read_only_sql_guard' => $c->enforce_read_only_sql_guard,
                'updated_at' => $c->updated_at?->toIso8601String(),
            ]);

        return Inertia::render('database-connections/index', [
            'connections' => $connections,
        ]);
    }

    public function activityLogs(): Response
    {
        $this->authorize('viewActivityLogs', DatabaseConnection::class);

        $logs = DatabaseConnectionLog::query()
            ->with(['user:id,name,email'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('database-connections/activity-logs', [
            'logs' => $logs,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', DatabaseConnection::class);

        return Inertia::render('database-connections/create');
    }

    public function store(StoreDatabaseConnectionRequest $request): RedirectResponse
    {
        $connection = DatabaseConnection::query()->create($request->validated());

        DatabaseConnectionChangeLogger::logCreated($request->user(), $request, $connection);

        DatabaseConnectionRegistrar::register();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection saved.')]);

        return to_route('database-connections.edit', $connection);
    }

    public function edit(DatabaseConnection $databaseConnection): Response
    {
        $this->authorize('update', $databaseConnection);

        return Inertia::render('database-connections/edit', [
            'connection' => $this->connectionPayload($databaseConnection),
        ]);
    }

    public function update(UpdateDatabaseConnectionRequest $request, DatabaseConnection $databaseConnection): RedirectResponse
    {
        $before = DatabaseConnectionChangeLogger::snapshot($databaseConnection);

        $validated = $request->validated();
        $password = $validated['password'] ?? null;
        unset($validated['password']);

        $databaseConnection->fill($validated);

        if (is_string($password) && $password !== '') {
            $databaseConnection->password = $password;
        }

        $databaseConnection->save();

        DatabaseConnectionChangeLogger::logUpdated($request->user(), $request, $databaseConnection->fresh(), $before);

        DatabaseConnectionRegistrar::register();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Connection updated.')]);

        return to_route('database-connections.edit', $databaseConnection);
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionPayload(DatabaseConnection $databaseConnection): array
    {
        return [
            'id' => $databaseConnection->id,
            'slug' => $databaseConnection->slug,
            'connection_group' => $databaseConnection->connection_group,
            'driver' => $databaseConnection->driver->value,
            'access_mode' => $databaseConnection->access_mode->value,
            'label' => $databaseConnection->label,
            'is_active' => $databaseConnection->is_active,
            'writes_enabled' => $databaseConnection->writes_enabled,
            'enforce_read_only_sql_guard' => $databaseConnection->enforce_read_only_sql_guard,
            'url' => $databaseConnection->url,
            'host' => $databaseConnection->host,
            'port' => $databaseConnection->port,
            'database' => $databaseConnection->database,
            'username' => $databaseConnection->username,
            'unix_socket' => $databaseConnection->unix_socket,
            'charset' => $databaseConnection->charset,
            'collation' => $databaseConnection->collation,
            'search_path' => $databaseConnection->search_path,
            'sslmode' => $databaseConnection->sslmode,
            'ssl_ca_path' => $databaseConnection->ssl_ca_path,
            'mongodb_dsn' => $databaseConnection->mongodb_dsn,
            'mongodb_authentication_database' => $databaseConnection->mongodb_authentication_database,
            'mongodb_read_preference' => $databaseConnection->mongodb_read_preference,
            'ssh_tunnel' => $databaseConnection->ssh_tunnel
                ? (string) json_encode($databaseConnection->ssh_tunnel, JSON_PRETTY_PRINT)
                : '',
            'extra_options' => $databaseConnection->extra_options
                ? (string) json_encode($databaseConnection->extra_options, JSON_PRETTY_PRINT)
                : '',
        ];
    }
}
