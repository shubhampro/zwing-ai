<?php

namespace App\Services\Salesforce;

use App\Models\SfCase;
use App\Models\SfCaseActivity;
use App\Models\SfCaseGroupHold;
use App\Support\IndiaDateTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SalesforceCaseSummaryComputer
{
    public const CASE_CHUNK_SIZE = 200;

    /**
     * @var list<string>
     */
    private const NOISY_FEED_TYPES = [
        'EmailMessageEvent',
        'ChangeStatusPost',
        'CreateRecordEvent',
        'CaseCommentPost',
    ];

    /**
     * @param  Collection<int, int|string>  $caseIds
     * @return list<array{id: int, activity_summary: string, activity_summarized_at: string}>
     */
    public function computeChunk(Collection $caseIds): array
    {
        if ($caseIds->isEmpty()) {
            return [];
        }

        $cases = SfCase::query()
            ->whereIn('id', $caseIds)
            ->orderBy('id')
            ->get();

        $activitiesByCaseId = SfCaseActivity::query()
            ->whereIn('sf_case_id', $caseIds)
            ->where(function ($query): void {
                $query->where('source', '!=', SfCaseActivity::SOURCE_FEED)
                    ->orWhereNotIn('type', self::NOISY_FEED_TYPES);
            })
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->groupBy('sf_case_id');

        $holdsByCaseId = SfCaseGroupHold::query()
            ->whereIn('sf_case_id', $caseIds)
            ->orderBy('started_at')
            ->orderBy('id')
            ->get()
            ->groupBy('sf_case_id');

        $summarizedAt = now()->utc()->toDateTimeString();

        return $cases
            ->map(fn (SfCase $case): array => [
                'id' => $case->id,
                'activity_summary' => $this->summarize(
                    $case,
                    $activitiesByCaseId->get($case->id, collect()),
                    $holdsByCaseId->get($case->id, collect()),
                ),
                'activity_summarized_at' => $summarizedAt,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{id: int, activity_summary: string, activity_summarized_at: string}>  $chunk
     */
    public function persistChunk(array $chunk): void
    {
        foreach ($chunk as $row) {
            SfCase::query()->whereKey($row['id'])->update([
                'activity_summary' => $row['activity_summary'],
                'activity_summarized_at' => $row['activity_summarized_at'],
            ]);
        }
    }

    /**
     * @param  Collection<int, SfCaseActivity>  $activities
     * @param  Collection<int, SfCaseGroupHold>  $holds
     */
    public function summarize(SfCase $case, Collection $activities, Collection $holds): string
    {
        $incoming = $activities->filter(fn (SfCaseActivity $activity): bool => $this->isCustomerIncoming($activity));
        $notes = $activities->filter(fn (SfCaseActivity $activity): bool => $this->isUsefulNote($activity));

        $ask = $this->cleanText($case->description);
        $customer = $this->cleanText($incoming->last()?->body);
        $note = $this->cleanText($notes->last()?->body);

        $lines = array_values(array_filter([
            $this->headerLine($case),
            $this->pathLine($case, $holds),
            $this->prefixedLine('Ask', $ask),
            $this->prefixedLine('Customer', $this->distinctText($customer, $ask)),
            $this->prefixedLine('Note', $this->distinctText($note, $ask, $customer)),
            $this->countsLine($activities, $incoming, $notes),
        ]));

        return implode("\n", $lines);
    }

    /**
     * @return array{status: ?string, path: list<string>, ask: ?string, customer: ?string, note: ?string, mail: ?string}
     */
    public function parse(?string $summary): array
    {
        $brief = [
            'status' => null,
            'path' => [],
            'ask' => null,
            'customer' => null,
            'note' => null,
            'mail' => null,
        ];

        if (! filled($summary)) {
            return $brief;
        }

        $status = [];

        foreach (preg_split("/\R/u", $summary) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, 'Path:')) {
                $brief['path'] = array_values(array_filter(array_map(
                    trim(...),
                    explode('→', trim(substr($line, strlen('Path:')))),
                )));

                continue;
            }

            foreach (['Ask', 'Customer', 'Note', 'Mail'] as $label) {
                $prefix = $label.':';

                if (str_starts_with($line, $prefix)) {
                    $brief[strtolower($label)] = trim(substr($line, strlen($prefix))) ?: null;

                    continue 2;
                }
            }

            $status[] = $line;
        }

        $brief['status'] = $status === [] ? null : implode(' ', $status);

        return $brief;
    }

    /**
     * @param  Collection<int, SfCaseActivity>  $activities
     * @param  Collection<int, SfCaseGroupHold>  $holds
     * @return list<array{key: string, kind: string, at: ?string, title: string, body: ?string, meta: ?string}>
     */
    public function timeline(SfCase $case, Collection $activities, Collection $holds): array
    {
        $incoming = $activities->filter(fn (SfCaseActivity $activity): bool => $this->isCustomerIncoming($activity));
        $notes = $activities->filter(fn (SfCaseActivity $activity): bool => $this->isUsefulNote($activity));
        $ask = $this->cleanText($case->description, 280);
        $customer = $incoming->last();
        $customerText = $this->distinctText($this->cleanText($customer?->body, 280), $ask);
        $note = $notes->last();
        $noteText = $this->distinctText($this->cleanText($note?->body, 280), $ask, $customerText);

        $events = collect();

        if ($case->created_at_sf instanceof CarbonInterface) {
            $events->push([
                'key' => 'opened',
                'kind' => 'opened',
                'rank' => 0,
                'at' => $case->created_at_sf->toIso8601String(),
                'title' => 'Opened',
                'body' => $ask,
                'meta' => $case->first_assigned_group,
            ]);
        }

        foreach ($holds as $hold) {
            $events->push([
                'key' => 'hold-'.$hold->id,
                'kind' => 'path',
                'rank' => 1,
                'at' => $hold->started_at?->toIso8601String(),
                'title' => $hold->group_name,
                'body' => $hold->is_open ? 'Still with this group' : null,
                'meta' => $this->formatDays((int) $hold->held_minutes).'d',
            ]);
        }

        if ($customer instanceof SfCaseActivity && filled($customerText)) {
            $events->push([
                'key' => 'customer',
                'kind' => 'customer',
                'rank' => 2,
                'at' => $customer->occurred_at?->toIso8601String(),
                'title' => 'Customer',
                'body' => $customerText,
                'meta' => $customer->author_name,
            ]);
        }

        if ($note instanceof SfCaseActivity && filled($noteText)) {
            $events->push([
                'key' => 'note',
                'kind' => 'note',
                'rank' => 3,
                'at' => $note->occurred_at?->toIso8601String(),
                'title' => 'Note',
                'body' => $noteText,
                'meta' => $note->author_name,
            ]);
        }

        $endedAt = $case->resolved_at_sf ?? $case->closed_at_sf;

        if ($endedAt instanceof CarbonInterface) {
            $metrics = array_values(array_filter([
                $case->resolution_minutes === null ? null : $this->formatDays((int) $case->resolution_minutes).'d total',
                $case->zwing_resolution_minutes === null ? null : $this->formatDays((int) $case->zwing_resolution_minutes).'d Zwing',
            ]));

            $events->push([
                'key' => 'closed',
                'kind' => 'closed',
                'rank' => 4,
                'at' => $endedAt->toIso8601String(),
                'title' => 'Closed',
                'body' => $metrics === [] ? null : implode(' · ', $metrics),
                'meta' => $case->status,
            ]);
        } else {
            $events->push([
                'key' => 'open',
                'kind' => 'open',
                'rank' => 5,
                'at' => $this->latestTimestamp($activities, $holds, $case),
                'title' => 'Still open',
                'body' => filled($case->group_name) ? 'With '.$case->group_name : null,
                'meta' => $case->status,
            ]);
        }

        return $events
            ->sortBy([
                fn (array $left, array $right): int => ($left['at'] ?? '') <=> ($right['at'] ?? ''),
                fn (array $left, array $right): int => $left['rank'] <=> $right['rank'],
            ])
            ->map(fn (array $event): array => [
                'key' => $event['key'],
                'kind' => $event['kind'],
                'at' => $event['at'],
                'title' => $event['title'],
                'body' => $event['body'],
                'meta' => $event['meta'],
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SfCaseActivity>  $activities
     * @param  Collection<int, SfCaseGroupHold>  $holds
     */
    private function latestTimestamp(Collection $activities, Collection $holds, SfCase $case): ?string
    {
        $latest = $activities
            ->pluck('occurred_at')
            ->concat($holds->pluck('started_at'))
            ->push($case->created_at_sf)
            ->filter()
            ->sort()
            ->last();

        return $latest instanceof CarbonInterface ? $latest->toIso8601String() : null;
    }

    private function headerLine(SfCase $case): string
    {
        $endedAt = $case->resolved_at_sf ?? $case->closed_at_sf;
        $parts = [
            $endedAt instanceof CarbonInterface ? 'Closed' : 'Open',
        ];

        if ($case->created_at_sf instanceof CarbonInterface) {
            $parts[] = 'Opened '.$this->formatDate($case->created_at_sf);
        }

        if ($endedAt instanceof CarbonInterface) {
            $parts[] = 'Closed '.$this->formatDate($endedAt);
        }

        $metrics = [];

        if ($case->resolution_minutes !== null) {
            $metrics[] = $this->formatDays((int) $case->resolution_minutes).'d total';
        }

        if ($case->zwing_resolution_minutes !== null) {
            $metrics[] = $this->formatDays((int) $case->zwing_resolution_minutes).'d Zwing';
        }

        $line = implode('. ', $parts).'.';

        if ($metrics !== []) {
            $line .= ' '.implode(' / ', $metrics).'.';
        }

        return $line;
    }

    /**
     * @param  Collection<int, SfCaseGroupHold>  $holds
     */
    private function pathLine(SfCase $case, Collection $holds): ?string
    {
        if ($holds->isNotEmpty()) {
            $steps = $holds
                ->map(fn (SfCaseGroupHold $hold): string => $hold->group_name.' ('.$this->formatDays((int) $hold->held_minutes).'d)')
                ->all();

            return 'Path: '.implode(' → ', $steps);
        }

        $groups = collect([$case->first_assigned_group, $case->group_name])
            ->filter()
            ->unique()
            ->values();

        if ($groups->isEmpty()) {
            return null;
        }

        return 'Path: '.$groups->implode(' → ');
    }

    /**
     * @param  Collection<int, SfCaseActivity>  $activities
     * @param  Collection<int, SfCaseActivity>  $incoming
     * @param  Collection<int, SfCaseActivity>  $notes
     */
    private function countsLine(Collection $activities, Collection $incoming, Collection $notes): ?string
    {
        $outgoing = $activities
            ->where('source', SfCaseActivity::SOURCE_EMAIL)
            ->where('is_incoming', false)
            ->filter(fn (SfCaseActivity $activity): bool => $this->cleanText($activity->body) !== null)
            ->count();

        if ($incoming->isEmpty() && $outgoing === 0 && $notes->isEmpty()) {
            return null;
        }

        return 'Mail: '.$incoming->count().' in / '.$outgoing.' out. Notes: '.$notes->count().'.';
    }

    private function prefixedLine(string $label, ?string $text): ?string
    {
        if (! filled($text)) {
            return null;
        }

        return $label.': '.$text;
    }

    private function isCustomerIncoming(SfCaseActivity $activity): bool
    {
        if ($activity->source !== SfCaseActivity::SOURCE_EMAIL || ! $activity->is_incoming) {
            return false;
        }

        if ($this->isGinesysAuthor($activity->author_name)) {
            return false;
        }

        return $this->cleanText($activity->body) !== null;
    }

    private function isUsefulNote(SfCaseActivity $activity): bool
    {
        if ($activity->source === SfCaseActivity::SOURCE_COMMENT) {
            return $this->cleanText($activity->body) !== null;
        }

        if ($activity->source === SfCaseActivity::SOURCE_FEED && in_array($activity->type, ['TextPost', 'ContentPost', 'LinkPost'], true)) {
            return $this->cleanText($activity->body) !== null;
        }

        if ($activity->source === SfCaseActivity::SOURCE_EMAIL && ! $activity->is_incoming) {
            return $this->cleanText($activity->body) !== null;
        }

        return false;
    }

    private function isGinesysAuthor(?string $author): bool
    {
        $author = strtolower((string) $author);

        return str_ends_with($author, '@ginesys.in')
            || str_contains($author, 'ginesys care');
    }

    private function distinctText(?string $text, ?string ...$alreadyUsed): ?string
    {
        if (! filled($text)) {
            return null;
        }

        foreach ($alreadyUsed as $used) {
            if (filled($used) && strcasecmp($text, $used) === 0) {
                return null;
            }
        }

        return $text;
    }

    private function cleanText(?string $value, int $limit = 220): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $value = preg_replace('/<(script|style|iframe|object|embed|link|meta|form)[^>]*>.*?<\/\1>/is', '', $value) ?? $value;
        $text = preg_replace('/<(?:br|\/p|\/div|\/li|\/h[1-6])\s*\/?>/i', "\n", $value) ?? $value;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\[cid:[^\]]+\]/i', ' ', $text) ?? $text;
        $text = preg_replace('/\ROn .+wrote:.*/is', ' ', $text) ?? $text;
        $text = preg_replace('/\RFrom:\s.+/s', ' ', $text) ?? $text;
        $text = preg_replace('/\R-{2,}\s*Original Message.*/is', ' ', $text) ?? $text;
        $text = (string) Str::of($text)->squish();

        if ($text === '' || $this->isNoise($text)) {
            return null;
        }

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $limit - 1)).'…';
    }

    private function isNoise(string $text): bool
    {
        return str_starts_with($text, 'Track:')
            || str_contains($text, 'Greetings from Ginesys Care')
            || str_contains($text, 'Dear Valued Customer');
    }

    private function formatDate(CarbonInterface $date): string
    {
        return IndiaDateTime::date($date) ?? $date->utc()->format('j M Y');
    }

    private function formatDays(int $minutes): string
    {
        return number_format($minutes / 1440, 1, '.', '');
    }
}
