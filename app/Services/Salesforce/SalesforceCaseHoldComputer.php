<?php

namespace App\Services\Salesforce;

use App\Models\SfCase;
use App\Models\SfCaseGroupHistory;
use App\Models\SfCaseGroupHold;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SalesforceCaseHoldComputer
{
    public const CASE_CHUNK_SIZE = 200;

    public const PERSIST_CHUNK_SIZE = 500;

    public const ZWING_GROUP = 'Zwing-Tech';

    /**
     * @param  Collection<int, int|string>  $caseIds
     * @return list<array<string, mixed>>
     */
    public function computeChunk(Collection $caseIds): array
    {
        if ($caseIds->isEmpty()) {
            return [];
        }

        $cases = SfCase::query()
            ->whereIn('id', $caseIds)
            ->orderBy('id')
            ->get(['id', 'first_assigned_group', 'group_name', 'created_at_sf', 'closed_at_sf', 'resolved_at_sf']);

        $historiesByCaseId = SfCaseGroupHistory::query()
            ->whereIn('sf_case_id', $caseIds)
            ->where('field', 'Group__c')
            ->orderBy('changed_at')
            ->orderBy('id')
            ->get()
            ->groupBy('sf_case_id');

        return $cases
            ->flatMap(fn (SfCase $case): array => $this->computeForCase(
                $case,
                $historiesByCaseId->get($case->id, collect()),
            ))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $chunk
     */
    public function persistChunk(array $chunk): void
    {
        if ($chunk === []) {
            return;
        }

        SfCaseGroupHold::query()->insert($chunk);
    }

    public function wipe(): void
    {
        SfCaseGroupHold::query()->delete();
    }

    /**
     * @param  Collection<int, int|string>  $caseIds
     * @param  list<array<string, mixed>>  $stints
     */
    public function persistResolutions(Collection $caseIds, array $stints): void
    {
        if ($caseIds->isEmpty()) {
            return;
        }

        $zwingMinutesByCaseId = collect($stints)
            ->where('group_name', self::ZWING_GROUP)
            ->groupBy(fn (array $stint): int => (int) $stint['sf_case_id'])
            ->map(fn (Collection $rows): int => (int) $rows->sum('held_minutes'));

        $cases = SfCase::query()
            ->whereIn('id', $caseIds)
            ->get(['id', 'created_at_sf', 'resolved_at_sf', 'closed_at_sf']);

        foreach ($cases as $case) {
            $case->forceFill([
                'resolution_minutes' => $this->resolutionMinutes($case),
                'zwing_resolution_minutes' => $zwingMinutesByCaseId->get($case->id, 0),
            ])->save();
        }
    }

    private function resolutionMinutes(SfCase $case): ?int
    {
        $endedAt = $case->resolved_at_sf ?? $case->closed_at_sf;

        if (! $case->created_at_sf instanceof CarbonInterface || ! $endedAt instanceof CarbonInterface) {
            return null;
        }

        return (int) round($case->created_at_sf->diffInMinutes($endedAt, absolute: true));
    }

    /**
     * @param  Collection<int, SfCaseGroupHistory>  $histories
     * @return list<array<string, mixed>>
     */
    private function computeForCase(SfCase $case, Collection $histories): array
    {
        $hops = $histories
            ->map(fn (SfCaseGroupHistory $history): array => [
                'at' => $history->changed_at,
                'old' => $this->groupName($history->old_value),
                'new' => $this->groupName($history->new_value),
            ])
            ->filter(fn (array $hop): bool => $hop['at'] instanceof CarbonInterface && $hop['new'] !== null)
            ->values();

        $firstHop = $hops->first();
        $currentGroup = $this->groupName($case->first_assigned_group)
            ?? $this->groupName(is_array($firstHop) ? $firstHop['old'] : null)
            ?? ($hops->isEmpty() ? $this->groupName($case->group_name) : null);
        $startedAt = $case->created_at_sf;

        if ($currentGroup === null && is_array($firstHop)) {
            $currentGroup = $firstHop['new'];
            $startedAt = $firstHop['at'];
        }

        if ($currentGroup === null || ! $startedAt instanceof CarbonInterface) {
            return [];
        }

        $stints = [];

        foreach ($hops as $hop) {
            if ($hop['new'] === $currentGroup) {
                continue;
            }

            if ($hop['at']->gt($startedAt)) {
                $stints[] = $this->stint($case, $currentGroup, $startedAt, $hop['at'], isOpen: false);
            }

            $currentGroup = $hop['new'];
            $startedAt = $hop['at'];
        }

        $closedAt = $case->closed_at_sf ?? $case->resolved_at_sf;
        $isOpen = $closedAt === null;
        $endedAt = $closedAt ?? now();

        if ($endedAt->lt($startedAt)) {
            $endedAt = $startedAt->copy();
        }

        $stints[] = $this->stint($case, $currentGroup, $startedAt, $endedAt, $isOpen);

        return $stints;
    }

    /**
     * @return array<string, mixed>
     */
    private function stint(SfCase $case, string $groupName, CarbonInterface $startedAt, CarbonInterface $endedAt, bool $isOpen): array
    {
        $now = now()->utc()->toDateTimeString();

        return [
            'sf_case_id' => $case->id,
            'group_name' => $groupName,
            'started_at' => $startedAt->utc()->toDateTimeString(),
            'ended_at' => $endedAt->utc()->toDateTimeString(),
            'held_minutes' => (int) round($startedAt->diffInMinutes($endedAt, absolute: true)),
            'is_open' => $isOpen,
            'computed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function groupName(?string $value): ?string
    {
        if (! filled($value) || $this->isSalesforceId($value)) {
            return null;
        }

        return $value;
    }

    private function isSalesforceId(string $value): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9]{15}(?:[a-zA-Z0-9]{3})?$/', $value);
    }
}
