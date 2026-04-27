<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSavedQueryRequest;
use App\Http\Requests\UpdateSavedQueryRequest;
use App\Models\SavedQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class SavedQueryController extends Controller
{
    public function store(StoreSavedQueryRequest $request): JsonResponse
    {
        Gate::authorize('create', SavedQuery::class);

        $savedQuery = $request->user()->savedQueries()->create($request->validatedPayload());

        return response()->json([
            'data' => $this->toResource($savedQuery),
        ], 201);
    }

    public function update(UpdateSavedQueryRequest $request, SavedQuery $savedQuery): JsonResponse
    {
        Gate::authorize('update', $savedQuery);

        $savedQuery->update($request->validatedPayload());

        return response()->json([
            'data' => $this->toResource($savedQuery->fresh()),
        ]);
    }

    public function destroy(SavedQuery $savedQuery): Response
    {
        Gate::authorize('delete', $savedQuery);

        $savedQuery->delete();

        return response()->noContent();
    }

    /**
     * @return array{id: int, name: string, sql: string, bindings: array<string, mixed>, updated_at: string|null}
     */
    private function toResource(SavedQuery $savedQuery): array
    {
        return [
            'id' => $savedQuery->id,
            'name' => $savedQuery->name,
            'sql' => $savedQuery->sql,
            'bindings' => $savedQuery->bindings ?? [],
            'updated_at' => $savedQuery->updated_at?->toIso8601String(),
        ];
    }
}
