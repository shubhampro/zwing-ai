<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesforceCaseIndexRequest;
use App\Models\SfCase;
use App\Models\SfCaseActivity;
use App\Models\SfCaseGroupHistory;
use App\Models\SfCaseGroupHold;
use App\Services\Salesforce\SalesforceCaseSummaryComputer;
use App\Support\SafeHtml;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalesforceCaseController extends Controller
{
    public function index(SalesforceCaseIndexRequest $request): Response
    {
        $q = $request->validated('q') ?: '';
        $status = $request->validated('status') ?: '';
        $priority = $request->validated('priority') ?: '';

        $paginator = SfCase::query()
            ->select([
                'id',
                'case_number',
                'subject',
                'status',
                'priority',
                'type',
                'sf_account_id',
                'group_name',
                'owner_name',
                'agent_name',
                'is_closed',
                'is_spam',
                'created_at_sf',
                'resolved_at_sf',
            ])
            ->with('account:id,name')
            ->when($q !== '', fn ($query) => $query->search($q))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($priority !== '', fn ($query) => $query->where('priority', $priority))
            ->latest('created_at_sf')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('sf-cases/index', [
            'cases' => $paginator->getCollection()->map($this->listRow(...))->values()->all(),
            'pagination' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'filters' => [
                'q' => $q,
                'status' => $status,
                'priority' => $priority,
            ],
            'statuses' => SfCase::query()
                ->whereNotNull('status')
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->values()
                ->all(),
            'priorities' => SfCase::query()
                ->whereNotNull('priority')
                ->distinct()
                ->orderBy('priority')
                ->pluck('priority')
                ->values()
                ->all(),
        ]);
    }

    public function show(Request $request, SfCase $sfCase, SalesforceCaseSummaryComputer $summaries): Response
    {
        abort_if($request->user() === null, 403);

        $sfCase->load([
            'account:id,name,sf_id',
            'groupHolds' => fn ($query) => $query->orderBy('started_at'),
            'groupHistories' => fn ($query) => $query->orderBy('changed_at'),
            'activities' => fn ($query) => $query->orderBy('occurred_at'),
        ]);

        return Inertia::render('sf-cases/show', [
            'ticket' => $this->detail($sfCase, $summaries),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(SfCase $case): array
    {
        return [
            'id' => $case->id,
            'case_number' => $case->case_number,
            'subject' => $case->subject,
            'status' => $case->status,
            'priority' => $case->priority,
            'type' => $case->type,
            'account_name' => $case->account?->name,
            'group_name' => $case->group_name,
            'owner_name' => $case->owner_name,
            'agent_name' => $case->agent_name,
            'is_closed' => $case->is_closed,
            'is_spam' => $case->is_spam,
            'created_at_sf' => $case->created_at_sf?->toIso8601String(),
            'resolved_at_sf' => $case->resolved_at_sf?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(SfCase $case, SalesforceCaseSummaryComputer $summaries): array
    {
        return [
            'id' => $case->id,
            'sf_id' => $case->sf_id,
            'case_number' => $case->case_number,
            'subject' => $case->subject,
            'description' => SafeHtml::from($case->description),
            'status' => $case->status,
            'priority' => $case->priority,
            'type' => $case->type,
            'origin' => $case->origin,
            'is_closed' => $case->is_closed,
            'is_spam' => $case->is_spam,
            'product' => $case->product,
            'product_name' => $case->product_name,
            'application' => $case->application,
            'module' => $case->module,
            'sub_module' => $case->sub_module,
            'group_name' => $case->group_name,
            'first_assigned_group' => $case->first_assigned_group,
            'owner_name' => $case->owner_name,
            'agent_name' => $case->agent_name,
            'requester_name' => $case->requester_name,
            'account' => $case->account === null ? null : [
                'id' => $case->account->id,
                'sf_id' => $case->account->sf_id,
                'name' => $case->account->name,
            ],
            'tags' => $case->tags,
            'size' => $case->size,
            'jira_id' => $case->jira_id,
            'jira_status' => $case->jira_status,
            'created_at_sf' => $case->created_at_sf?->toIso8601String(),
            'resolved_at_sf' => $case->resolved_at_sf?->toIso8601String(),
            'closed_at_sf' => $case->closed_at_sf?->toIso8601String(),
            'last_modified_at_sf' => $case->last_modified_at_sf?->toIso8601String(),
            'synced_at' => $case->synced_at?->toIso8601String(),
            'resolution_minutes' => $case->resolution_minutes,
            'zwing_resolution_minutes' => $case->zwing_resolution_minutes,
            'activity_summary' => $case->activity_summary,
            'activity_summarized_at' => $case->activity_summarized_at?->toIso8601String(),
            'brief' => $summaries->parse($case->activity_summary),
            'timeline' => $summaries->timeline($case, $case->activities, $case->groupHolds),
            'holds' => $case->groupHolds->map(fn (SfCaseGroupHold $hold): array => [
                'id' => $hold->id,
                'group_name' => $hold->group_name,
                'started_at' => $hold->started_at?->toIso8601String(),
                'ended_at' => $hold->ended_at?->toIso8601String(),
                'held_minutes' => $hold->held_minutes,
                'is_open' => $hold->is_open,
            ])->values()->all(),
            'histories' => $case->groupHistories->map(fn (SfCaseGroupHistory $history): array => [
                'id' => $history->id,
                'field' => $history->field,
                'old_value' => $history->old_value,
                'new_value' => $history->new_value,
                'changed_by' => $history->changed_by,
                'changed_at' => $history->changed_at?->toIso8601String(),
            ])->values()->all(),
            'activities' => $case->activities->map(fn (SfCaseActivity $activity): array => [
                'id' => $activity->id,
                'source' => $activity->source,
                'type' => $activity->type,
                'subject' => $activity->subject,
                'body' => SafeHtml::from($activity->body),
                'preview' => SafeHtml::plain($activity->subject ?? $activity->body),
                'author_name' => $activity->author_name,
                'is_incoming' => $activity->is_incoming,
                'occurred_at' => $activity->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
