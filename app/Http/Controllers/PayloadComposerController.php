<?php

namespace App\Http\Controllers;

use App\Enums\PayloadComposerSlotShape;
use App\Http\Requests\GeneratePayloadComposerRequest;
use App\Http\Requests\StorePayloadComposerRequest;
use App\Http\Requests\UpdatePayloadComposerRequest;
use App\Models\Organization;
use App\Models\PayloadComposer;
use App\Models\SavedSqlQuery;
use App\Services\PayloadComposerGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayloadComposerController extends Controller
{
    public function index(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        $composers = PayloadComposer::query()
            ->where('user_id', $request->user()->id)
            ->withCount('slots')
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'description', 'scalars', 'updated_at']);

        return Inertia::render('payload-composers/index', [
            'composers' => $composers->map(fn (PayloadComposer $composer) => [
                'id' => $composer->id,
                'name' => $composer->name,
                'description' => $composer->description,
                'scalar_count' => count($composer->scalars ?? []),
                'slot_count' => $composer->slots_count,
                'updated_at' => $composer->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function create(Request $request): Response
    {
        abort_if($request->user() === null, 403);

        return Inertia::render('payload-composers/create', [
            'savedQueries' => $this->savedQueriesFor($request->user()->id),
            'slotShapes' => PayloadComposerSlotShape::values(),
        ]);
    }

    public function store(StorePayloadComposerRequest $request): RedirectResponse
    {
        $composer = DB::transaction(function () use ($request): PayloadComposer {
            $composer = PayloadComposer::query()->create([
                'user_id' => $request->user()->id,
                'name' => $request->string('name')->toString(),
                'description' => $request->input('description'),
                'scalars' => $this->normalizeScalars($request->input('scalars', [])),
            ]);

            $this->syncSlots($composer, $request->input('slots', []));

            return $composer;
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payload composer created.'),
        ]);

        return redirect()->route('payload-composers.show', $composer);
    }

    public function show(Request $request, PayloadComposer $payloadComposer): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($payloadComposer->user_id !== $request->user()->id, 403);

        $payloadComposer->load(['slots.savedSqlQuery:id,title,sql']);

        $generator = app(PayloadComposerGenerator::class);

        $bindingNames = collect($payloadComposer->slots)
            ->flatMap(fn ($slot) => $generator->extractBindingNames((string) ($slot->savedSqlQuery?->sql ?? '')))
            ->unique()
            ->values()
            ->all();

        $organizations = Organization::query()
            ->orderBy('name')
            ->get(['id', 'name', 'ba_code', 'db_name'])
            ->filter(fn (Organization $organization) => filled($organization->db_name))
            ->values();

        return Inertia::render('payload-composers/show', [
            'composer' => $this->composerPayload($payloadComposer),
            'bindingNames' => $bindingNames,
            'organizations' => $organizations->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'ba_code' => $organization->ba_code,
                'db_name' => $organization->db_name,
                'label' => $organization->name.' ('.$organization->ba_code.') · mysql_ssh · '.$organization->db_name,
            ]),
        ]);
    }

    public function edit(Request $request, PayloadComposer $payloadComposer): Response
    {
        abort_if($request->user() === null, 403);
        abort_if($payloadComposer->user_id !== $request->user()->id, 403);

        $payloadComposer->load(['slots.savedSqlQuery:id,title']);

        return Inertia::render('payload-composers/edit', [
            'composer' => $this->composerPayload($payloadComposer),
            'savedQueries' => $this->savedQueriesFor($request->user()->id),
            'slotShapes' => PayloadComposerSlotShape::values(),
        ]);
    }

    public function update(UpdatePayloadComposerRequest $request, PayloadComposer $payloadComposer): RedirectResponse
    {
        abort_if($payloadComposer->user_id !== $request->user()->id, 403);

        DB::transaction(function () use ($request, $payloadComposer): void {
            $payloadComposer->update([
                'name' => $request->string('name')->toString(),
                'description' => $request->input('description'),
                'scalars' => $this->normalizeScalars($request->input('scalars', [])),
            ]);

            $payloadComposer->slots()->delete();
            $this->syncSlots($payloadComposer, $request->input('slots', []));
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payload composer updated.'),
        ]);

        return redirect()->route('payload-composers.show', $payloadComposer);
    }

    public function destroy(Request $request, PayloadComposer $payloadComposer): RedirectResponse
    {
        abort_if($request->user() === null, 403);
        abort_if($payloadComposer->user_id !== $request->user()->id, 403);

        $payloadComposer->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Payload composer deleted.'),
        ]);

        return redirect()->route('payload-composers.index');
    }

    public function generate(
        GeneratePayloadComposerRequest $request,
        PayloadComposer $payloadComposer,
        PayloadComposerGenerator $generator,
    ): JsonResponse {
        abort_if($request->user() === null, 403);
        abort_if($payloadComposer->user_id !== $request->user()->id, 403);

        $organization = Organization::query()->findOrFail(
            $request->integer('organization_id'),
        );

        $result = $generator->generate(
            $payloadComposer,
            $organization,
            $request->input('scalars', []) ?? [],
            $request->input('bindings', []) ?? [],
        );

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    /**
     * @return list<array{id: int, title: string}>
     */
    private function savedQueriesFor(int $userId): array
    {
        return SavedSqlQuery::query()
            ->where('user_id', $userId)
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (SavedSqlQuery $query) => [
                'id' => $query->id,
                'title' => $query->title,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function composerPayload(PayloadComposer $composer): array
    {
        return [
            'id' => $composer->id,
            'name' => $composer->name,
            'description' => $composer->description,
            'scalars' => collect($composer->scalars ?? [])
                ->map(fn (array $scalar) => [
                    'key' => (string) ($scalar['key'] ?? ''),
                    'required' => (bool) ($scalar['required'] ?? false),
                    'default' => $scalar['default'] ?? null,
                ])
                ->values()
                ->all(),
            'slots' => $composer->slots->map(fn ($slot) => [
                'id' => $slot->id,
                'key' => $slot->key ?? '',
                'saved_sql_query_id' => $slot->saved_sql_query_id,
                'saved_sql_query_title' => $slot->savedSqlQuery?->title,
                'shape' => $slot->shape->value,
                'sort_order' => $slot->sort_order,
                'merges_to_root' => $slot->shape === PayloadComposerSlotShape::Object
                    && trim((string) ($slot->key ?? '')) === '',
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $scalars
     * @return list<array{key: string, required: bool, default: string|null}>
     */
    private function normalizeScalars(array $scalars): array
    {
        return collect($scalars)
            ->filter(fn (array $scalar) => filled($scalar['key'] ?? null))
            ->map(fn (array $scalar) => [
                'key' => trim((string) $scalar['key']),
                'required' => (bool) ($scalar['required'] ?? false),
                'default' => filled($scalar['default'] ?? null) ? (string) $scalar['default'] : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $slots
     */
    private function syncSlots(PayloadComposer $composer, array $slots): void
    {
        foreach (array_values($slots) as $index => $slot) {
            $key = trim((string) ($slot['key'] ?? ''));

            $composer->slots()->create([
                'key' => $key === '' ? null : $key,
                'saved_sql_query_id' => (int) $slot['saved_sql_query_id'],
                'shape' => $slot['shape'] ?? PayloadComposerSlotShape::Array->value,
                'sort_order' => (int) ($slot['sort_order'] ?? $index),
            ]);
        }
    }
}
