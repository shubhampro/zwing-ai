<?php

namespace App\Http\Controllers;

use App\Enums\DatabaseDriver;
use App\Http\Requests\ListMysqlDatabasesRequest;
use App\Http\Requests\StoreDatabaseSessionContextRequest;
use App\Models\DatabaseConnection;
use App\Services\ListRemoteMysqlDatabases;
use App\Support\Database\ActiveRemoteDatabaseContext;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Throwable;

class DatabaseSessionContextController extends Controller
{
    use AuthorizesRequests;

    public function databases(
        ListMysqlDatabasesRequest $request,
        ListRemoteMysqlDatabases $listRemoteMysqlDatabases,
    ): JsonResponse {
        /** @var string $slug */
        $slug = $request->validated('connection_slug');

        $connection = DatabaseConnection::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        Gate::authorize('view', $connection);

        if ($connection->driver !== DatabaseDriver::Mysql) {
            return response()->json([
                'message' => 'Database listing is only available for MySQL connections.',
            ], 422);
        }

        try {
            $data = $listRemoteMysqlDatabases($connection->slug);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Could not list databases for this connection.',
            ], 503);
        }

        return response()->json(['data' => $data]);
    }

    public function update(StoreDatabaseSessionContextRequest $request): JsonResponse
    {
        /** @var string $connectionSlug */
        $connectionSlug = $request->validated('connection_slug');

        $model = DatabaseConnection::query()
            ->where('slug', $connectionSlug)
            ->where('is_active', true)
            ->firstOrFail();

        Gate::authorize('view', $model);

        /** @var string|null $database */
        $database = $request->validated('database');

        ActiveRemoteDatabaseContext::store($connectionSlug, $database);

        return response()->json(['message' => 'Context updated.']);
    }
}
