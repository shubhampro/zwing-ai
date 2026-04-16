<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseDriver;
use App\Http\Requests\StoreDatabaseConnectionRequest;
use App\Http\Requests\UpdateDatabaseConnectionRequest;
use App\Models\DatabaseConnection;
use App\Models\DatabaseConnectionLog;
use App\Services\DatabaseConnectionChangeLogger;
use App\Services\DatabaseConnectionRegistrar;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DatabaseConnectionController extends Controller
{
    use AuthorizesRequests;

    public function testConnection(Request $request): JsonResponse
    {
        $this->authorize('create', DatabaseConnection::class);

        $validated = $request->validate([
            'driver' => ['required', new Enum(DatabaseDriver::class)],
            'access_mode' => ['required', 'string'],
            'url' => ['nullable', 'string', 'max:2048'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'database' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:5000'],
            'unix_socket' => ['nullable', 'string', 'max:255'],
            'charset' => ['nullable', 'string', 'max:64'],
            'collation' => ['nullable', 'string', 'max:64'],
            'search_path' => ['nullable', 'string', 'max:255'],
            'sslmode' => ['nullable', 'string', 'max:32'],
            'ssl_ca_path' => ['nullable', 'string', 'max:2048'],
            'mongodb_dsn' => ['nullable', 'string', 'max:2048'],
            'mongodb_authentication_database' => ['nullable', 'string', 'max:255'],
            'mongodb_read_preference' => ['nullable', 'string', 'max:64'],
            'connection_id' => ['nullable', 'integer', 'exists:database_connections,id'],
        ]);

        // Build a transient model — never saved.
        $tempModel = new DatabaseConnection;
        $tempModel->fill($validated);

        // When testing an existing connection, restore stored password if left blank.
        if (! empty($validated['connection_id'])) {
            $existing = DatabaseConnection::find($validated['connection_id']);

            if ($existing instanceof DatabaseConnection && empty($validated['password'])) {
                $tempModel->password = $existing->password;
            }
        }

        $config = DatabaseConnectionRegistrar::toLaravelConnection($tempModel);
        $tempKey = 'db_test_'.uniqid();
        Config::set("database.connections.{$tempKey}", $config);

        try {
            $connection = DB::connection($tempKey);

            match ($tempModel->driver) {
                DatabaseDriver::Mysql, DatabaseDriver::Pgsql => $connection->getPdo(),
                DatabaseDriver::Mongodb => iterator_to_array(
                    $connection->getMongoDB()->listCollections(['nameOnly' => true])
                ),
            };

            return response()->json(['success' => true, 'message' => 'Connection successful.']);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        } finally {
            DB::purge($tempKey);
        }
    }

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

        // Password: blank means keep existing.
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
            'extra_options' => $databaseConnection->extra_options
                ? (string) json_encode($databaseConnection->extra_options, JSON_PRETTY_PRINT)
                : '',
        ];
    }
}
