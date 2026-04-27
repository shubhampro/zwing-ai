<?php

namespace App\Http\Controllers;

use App\Exceptions\NoActiveRemoteDatabaseContextException;
use App\Http\Requests\RunRemoteQueryRequest;
use App\Models\SavedQuery;
use App\Services\RunRemoteSelectQuery;
use App\Support\Database\RemoteQueryUserMessage;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class QueryTableController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $savedQueries = SavedQuery::query()
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'sql', 'bindings', 'updated_at'])
            ->map(fn (SavedQuery $q): array => [
                'id' => $q->id,
                'name' => $q->name,
                'sql' => $q->sql,
                'bindings' => $q->bindings ?? [],
                'updated_at' => $q->updated_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('query-table/index', [
            'savedQueries' => $savedQueries,
        ]);
    }

    public function run(RunRemoteQueryRequest $request, RunRemoteSelectQuery $runRemoteSelectQuery): JsonResponse
    {
        $payload = $request->validatedPayload();

        try {
            $result = $runRemoteSelectQuery($payload['query'], $payload['bindings']);

            return response()->json($result);
        } catch (NoActiveRemoteDatabaseContextException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (QueryException $e) {
            report($e);

            return response()->json([
                'message' => RemoteQueryUserMessage::fromQueryException($e),
            ], 422);
        }
    }
}
