<?php

namespace App\Services\Salesforce;

use App\Models\SfCase;
use App\Models\SfCaseActivity;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SalesforceMonthlySupportReport
{
    public const PRODUCT = 'Zwing';

    public const SERIES_START = '2026-04-01';

    /**
     * @var array<string, int>
     */
    public const SLA_DAYS = [
        'Urgent' => 1,
        'High' => 2,
        'Medium' => 5,
    ];

    /**
     * @var list<string>
     */
    public const TYPE_BUCKETS = [
        'Incident',
        'Question',
        'Service request',
        'Others',
    ];

    /**
     * @var list<string>
     */
    public const PRIORITY_ORDER = [
        'Urgent',
        'High',
        'Medium',
    ];

    /**
     * @return list<string>
     */
    public function availableMonths(): array
    {
        $dates = SfCase::query()
            ->where('product', self::PRODUCT)
            ->where('is_spam', false)
            ->whereNotNull('created_at_sf')
            ->orderBy('created_at_sf')
            ->pluck('created_at_sf');

        return $dates
            ->map(fn (CarbonInterface $date): string => $date->copy()->startOfMonth()->format('Y-m'))
            ->unique()
            ->values()
            ->all();
    }

    public function resolveMonth(?string $month): CarbonImmutable
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        }

        $available = $this->availableMonths();

        if ($available !== []) {
            return CarbonImmutable::createFromFormat('Y-m-d', end($available).'-01')->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * @return array{product: string, title: string, months: list<array<string, mixed>>}
     */
    public function monthIndex(): array
    {
        $cases = $this->cases();
        $latest = $this->resolveMonth(null);
        $cursor = CarbonImmutable::parse(self::SERIES_START)->startOfMonth();
        $months = [];

        while ($cursor->lte($latest)) {
            $months[] = $this->volumeForMonth($cases, $cursor);
            $cursor = $cursor->addMonth();
        }

        return [
            'product' => self::PRODUCT,
            'title' => 'Zwing CST — Monthly Business Review',
            'months' => array_reverse($months),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonImmutable $monthStart): array
    {
        $monthEnd = $monthStart->addMonth();
        $seriesStart = CarbonImmutable::parse(self::SERIES_START)->startOfMonth();
        $cases = $this->cases();

        $months = [];
        $cursor = $seriesStart;

        while ($cursor->lte($monthStart)) {
            $months[] = $this->volumeForMonth($cases, $cursor);
            $cursor = $cursor->addMonth();
        }

        $selected = collect($months)->firstWhere('key', $monthStart->format('Y-m'))
            ?? $this->volumeForMonth($cases, $monthStart);

        $previous = collect($months)->firstWhere('key', $monthStart->subMonth()->format('Y-m'));
        $pool = $this->poolCases($cases, $monthStart, $monthEnd);
        $newCases = $cases->filter(fn (SfCase $case): bool => $this->inMonth($case->created_at_sf, $monthStart, $monthEnd));
        $resolved = $this->resolvedInMonth($cases, $monthStart, $monthEnd);
        $customerLoad = $this->customerLoad($newCases);
        $waterfall = $this->waterfall($newCases);
        $frtHours = $this->firstResponseHours($newCases);
        $speed = $this->speed($pool, $resolved, $newCases, $frtHours, $monthEnd);
        $quality = $this->quality($newCases, $waterfall);
        $highlights = $this->highlights(
            $cases,
            $selected,
            $previous,
            $newCases,
            $monthStart,
            $monthEnd,
            $waterfall,
            $speed,
            $quality,
        );
        $briefing = $this->briefing($highlights, $selected, $previous, $waterfall, $customerLoad);

        return [
            'product' => self::PRODUCT,
            'title' => 'Zwing CST — Monthly Business Review',
            'owner' => 'Customer Support Team',
            'source' => 'Local Salesforce dump · Product Zwing · spam excluded',
            'month' => $monthStart->format('Y-m'),
            'month_label' => $monthStart->format('M Y'),
            'as_of' => $monthEnd->subSecond()->toDateString(),
            'available_months' => $this->availableMonths(),
            'highlights' => $highlights,
            'risks' => $briefing['risks'],
            'actions' => $briefing['actions'],
            'volume' => $months,
            'demand' => [
                'net_backlog' => $selected['carry_forward'] - $selected['brought_forward'],
                'type_mix' => $this->mix($newCases, fn (SfCase $case): string => $this->typeBucket($case)),
                'channel_mix' => $this->mix($newCases, fn (SfCase $case): string => trim((string) $case->origin) ?: '(Unknown)'),
            ],
            'speed' => $speed,
            'quality' => $quality,
            'customer_load' => $customerLoad,
            'concentration' => $this->concentration($customerLoad['customers']),
            'top_customers' => array_slice($customerLoad['customers'], 0, 10),
            'at_risk' => $this->atRisk($customerLoad['customers']),
            'waterfall' => $waterfall,
            'holds' => $this->holds($resolved),
            'people' => $this->people($newCases),
            'product_mix' => $this->mix(
                $newCases,
                fn (SfCase $case): string => trim((string) $case->module) ?: '(No module)',
            ),
        ];
    }

    /**
     * @return Collection<int, SfCase>
     */
    private function cases(): Collection
    {
        return SfCase::query()
            ->with([
                'account:id,name',
                'groupHolds:id,sf_case_id,group_name,held_minutes',
            ])
            ->where('product', self::PRODUCT)
            ->where('is_spam', false)
            ->whereNotNull('created_at_sf')
            ->orderBy('created_at_sf')
            ->get([
                'id',
                'case_number',
                'priority',
                'type',
                'status',
                'group_name',
                'first_assigned_group',
                'origin',
                'module',
                'owner_name',
                'agent_name',
                'sf_account_id',
                'created_at_sf',
                'resolved_at_sf',
                'closed_at_sf',
                'resolution_minutes',
                'zwing_resolution_minutes',
            ]);
    }

    /**
     * @param  Collection<int, SfCase>  $cases
     * @return array{
     *     key: string,
     *     label: string,
     *     brought_forward: int,
     *     new_tickets: int,
     *     pool: int,
     *     resolved: int,
     *     carry_forward: int,
     *     resolved_pct: float
     * }
     */
    private function volumeForMonth(Collection $cases, CarbonImmutable $monthStart): array
    {
        $monthEnd = $monthStart->addMonth();
        $broughtForward = $cases->filter(fn (SfCase $case): bool => $this->openAt($case, $monthStart))->count();
        $newTickets = $cases->filter(fn (SfCase $case): bool => $this->inMonth($case->created_at_sf, $monthStart, $monthEnd))->count();
        $carryForward = $cases->filter(fn (SfCase $case): bool => $this->openAt($case, $monthEnd))->count();
        $pool = $broughtForward + $newTickets;
        $resolved = $pool - $carryForward;

        return [
            'key' => $monthStart->format('Y-m'),
            'label' => $monthStart->format('M Y'),
            'brought_forward' => $broughtForward,
            'new_tickets' => $newTickets,
            'pool' => $pool,
            'resolved' => $resolved,
            'carry_forward' => $carryForward,
            'resolved_pct' => $pool > 0 ? round($resolved / $pool, 4) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, SfCase>  $cases
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>|null  $previous
     * @param  Collection<int, SfCase>  $newCases
     * @param  array<string, mixed>  $waterfall
     * @param  array<string, mixed>  $speed
     * @param  array<string, mixed>  $quality
     * @return array<string, mixed>
     */
    private function highlights(
        Collection $cases,
        array $selected,
        ?array $previous,
        Collection $newCases,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        array $waterfall,
        array $speed,
        array $quality,
    ): array {
        $seriesStart = CarbonImmutable::parse(self::SERIES_START)->startOfMonth();
        $totalTickets = $cases
            ->filter(fn (SfCase $case): bool => $case->created_at_sf?->gte($seriesStart) && $case->created_at_sf?->lt($monthEnd))
            ->count();

        $prevNew = (int) ($previous['new_tickets'] ?? 0);
        $mom = $prevNew > 0
            ? round(($selected['new_tickets'] - $prevNew) / $prevNew, 4)
            : null;

        $urgentPool = $this->poolCases($cases, $monthStart, $monthEnd)
            ->filter(fn (SfCase $case): bool => $this->priorityBucket($case) === 'Urgent');
        $urgentBreach = $this->slaBreachPct($urgentPool, $monthEnd);
        $zwingUrgentBreach = $this->zwingSlaBreachPct($urgentPool);

        $customers = $this->customerLoad($newCases);

        return [
            'total_tickets' => $totalTickets,
            'new_tickets' => $selected['new_tickets'],
            'resolved_pct' => $selected['resolved_pct'],
            'carry_forward' => $selected['carry_forward'],
            'net_backlog' => $selected['carry_forward'] - $selected['brought_forward'],
            'mom_new' => $mom,
            'active_customers' => $customers['active_customers'],
            'avg_tickets_per_customer' => $customers['avg_tickets_per_customer'],
            'urgent_sla_breach_pct' => $urgentBreach,
            'urgent_sla_hit_pct' => $urgentPool->isEmpty() ? null : round(1 - $urgentBreach, 4),
            'zwing_urgent_sla_breach_pct' => $zwingUrgentBreach,
            'zwing_urgent_sla_hit_pct' => $urgentPool->isEmpty() ? null : round(1 - $zwingUrgentBreach, 4),
            'frt_median_hours' => $speed['frt_median_hours'],
            'ttr_median_days' => $speed['ttr_median_days'],
            'ttr_p90_days' => $speed['ttr_p90_days'],
            'fcr_pct' => $quality['fcr_pct'],
            'l3_pct' => $waterfall['received'] > 0
                ? round($waterfall['to_l3'] / $waterfall['received'], 4)
                : 0.0,
            'integration' => $waterfall['to_integration'],
        ];
    }

    /**
     * @param  Collection<int, SfCase>  $cases
     * @return array{new_tickets: int, active_customers: int, avg_tickets_per_customer: float|null, customers: list<array{account_name: string, tickets: int, share: float}>}
     */
    private function customerLoad(Collection $cases): array
    {
        $counted = $cases->reject(fn (SfCase $case): bool => $this->isInternalAccount($case));
        $grouped = $counted
            ->groupBy(fn (SfCase $case): string => $case->account?->name ?: '(No account)')
            ->map(fn (Collection $rows, string $name): array => [
                'account_name' => $name,
                'tickets' => $rows->count(),
            ])
            ->sortByDesc('tickets')
            ->values();

        $total = $counted->count();
        $customers = $grouped
            ->map(fn (array $row): array => [
                ...$row,
                'share' => $total > 0 ? round($row['tickets'] / $total, 4) : 0.0,
            ])
            ->all();

        $active = count($customers);

        return [
            'new_tickets' => $total,
            'active_customers' => $active,
            'avg_tickets_per_customer' => $active > 0 ? round($total / $active, 4) : null,
            'customers' => $customers,
        ];
    }

    /**
     * @param  list<array{account_name: string, tickets: int, share: float}>  $customers
     * @return list<array{label: string, tickets: int, share: float}>
     */
    private function concentration(array $customers): array
    {
        $total = array_sum(array_column($customers, 'tickets'));
        $top5 = array_sum(array_column(array_slice($customers, 0, 5), 'tickets'));
        $next5 = array_sum(array_column(array_slice($customers, 5, 5), 'tickets'));
        $rest = max($total - $top5 - $next5, 0);

        return [
            $this->bracket('Top 5', $top5, $total),
            $this->bracket('6-10', $next5, $total),
            $this->bracket('11+ (Others)', $rest, $total),
        ];
    }

    /**
     * @return array{label: string, tickets: int, share: float}
     */
    private function bracket(string $label, int $tickets, int $total): array
    {
        return [
            'label' => $label,
            'tickets' => $tickets,
            'share' => $total > 0 ? round($tickets / $total, 4) : 0.0,
        ];
    }

    /**
     * @param  Collection<int, SfCase>  $newCases
     * @return array<string, int>
     */
    private function waterfall(Collection $newCases): array
    {
        $received = $newCases->count();
        $toBa = 0;
        $toOther = 0;
        $toL1 = 0;
        $toL2 = 0;
        $toIntegration = 0;
        $toL3 = 0;

        foreach ($newCases as $case) {
            $stages = $this->stages($case);

            if ($stages['ba'] && ! $stages['l2'] && ! $stages['l3'] && ! $stages['integration']) {
                $toBa++;

                continue;
            }

            if ($stages['other'] && ! $stages['l2'] && ! $stages['l3'] && ! $stages['integration']) {
                $toOther++;

                continue;
            }

            if (! $stages['l2'] && ! $stages['l3'] && ! $stages['integration']) {
                $toL1++;

                continue;
            }

            if ($stages['l3']) {
                $toL3++;

                continue;
            }

            if ($stages['integration']) {
                $toIntegration++;

                continue;
            }

            $toL2++;
        }

        $inLadder = $received - $toBa - $toOther;

        return [
            'received' => $received,
            'to_ba' => $toBa,
            'to_other' => $toOther,
            'in_ladder' => $inLadder,
            'resolved_l1' => $toL1,
            'to_l2' => $toL2 + $toIntegration + $toL3,
            'resolved_l2' => $toL2,
            'to_integration' => $toIntegration,
            'to_l3' => $toL3,
        ];
    }

    /**
     * @param  Collection<int, SfCase>  $resolved
     * @return list<array{group_name: string, held_minutes: int, held_days: float}>
     */
    private function holds(Collection $resolved): array
    {
        return $resolved
            ->flatMap(fn (SfCase $case): Collection => $case->groupHolds)
            ->groupBy(fn ($hold): string => (string) ($hold->group_name ?: '(Unknown)'))
            ->map(function (Collection $rows, string $group): array {
                $minutes = (int) $rows->sum('held_minutes');

                return [
                    'group_name' => $group,
                    'held_minutes' => $minutes,
                    'held_days' => round($minutes / 1440, 2),
                ];
            })
            ->sortByDesc('held_minutes')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SfCase>  $cases
     * @return Collection<int, SfCase>
     */
    private function poolCases(Collection $cases, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): Collection
    {
        return $cases->filter(function (SfCase $case) use ($monthStart, $monthEnd): bool {
            return $this->openAt($case, $monthStart)
                || $this->inMonth($case->created_at_sf, $monthStart, $monthEnd);
        });
    }

    /**
     * @param  Collection<int, SfCase>  $cases
     * @return Collection<int, SfCase>
     */
    private function resolvedInMonth(Collection $cases, CarbonImmutable $monthStart, CarbonImmutable $monthEnd): Collection
    {
        return $cases->filter(fn (SfCase $case): bool => $this->inMonth($this->finishedAt($case), $monthStart, $monthEnd));
    }

    /**
     * @param  Collection<int, SfCase>  $cases
     * @param  callable(SfCase): int  $days
     * @return array{d0_1: float, d1_2: float, d2_5: float, d5_plus: float}
     */
    private function ageingShare(Collection $cases, callable $days): array
    {
        $count = $cases->count();

        if ($count === 0) {
            return [
                'd0_1' => 0.0,
                'd1_2' => 0.0,
                'd2_5' => 0.0,
                'd5_plus' => 0.0,
            ];
        }

        $buckets = [
            'd0_1' => 0,
            'd1_2' => 0,
            'd2_5' => 0,
            'd5_plus' => 0,
        ];

        foreach ($cases as $case) {
            $value = $days($case);

            if ($value <= 1) {
                $buckets['d0_1']++;
            } elseif ($value <= 2) {
                $buckets['d1_2']++;
            } elseif ($value <= 5) {
                $buckets['d2_5']++;
            } else {
                $buckets['d5_plus']++;
            }
        }

        return [
            'd0_1' => round($buckets['d0_1'] / $count, 4),
            'd1_2' => round($buckets['d1_2'] / $count, 4),
            'd2_5' => round($buckets['d2_5'] / $count, 4),
            'd5_plus' => round($buckets['d5_plus'] / $count, 4),
        ];
    }

    /**
     * @param  Collection<int, SfCase>  $cases
     * @param  callable(SfCase): string  $label
     * @return list<array{label: string, tickets: int, share: float}>
     */
    private function mix(Collection $cases, callable $label): array
    {
        $total = $cases->count();

        return $cases
            ->groupBy($label)
            ->map(fn (Collection $rows, string $name): array => [
                'label' => $name,
                'tickets' => $rows->count(),
                'share' => $total > 0 ? round($rows->count() / $total, 4) : 0.0,
            ])
            ->sortByDesc('tickets')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SfCase>  $pool
     * @param  Collection<int, SfCase>  $resolved
     * @param  Collection<int, SfCase>  $newCases
     * @param  Collection<int, float>  $frtHours
     * @return array<string, mixed>
     */
    private function speed(
        Collection $pool,
        Collection $resolved,
        Collection $newCases,
        Collection $frtHours,
        CarbonImmutable $monthEnd,
    ): array {
        $open = $pool->filter(fn (SfCase $case): bool => $this->openAt($case, $monthEnd));
        $ttrDays = $resolved
            ->map(fn (SfCase $case): int => $this->ttrDays($case, $this->finishedAt($case)))
            ->values();
        $breachedOpen = $open->filter(function (SfCase $case) use ($monthEnd): bool {
            return $this->ttrDays($case, $monthEnd) > $this->slaDays($case);
        })->count();

        return [
            'frt_median_hours' => $this->median($frtHours),
            'frt_coverage_pct' => $newCases->isEmpty()
                ? 0.0
                : round($frtHours->count() / $newCases->count(), 4),
            'ttr_median_days' => $this->median($ttrDays),
            'ttr_p90_days' => $this->percentile($ttrDays, 90),
            'breached_open' => $breachedOpen,
            'resolved_ageing' => $this->ageingShare($resolved, fn (SfCase $case): int => $this->ttrDays($case, $this->finishedAt($case))),
            'open_ageing' => $this->ageingShare($open, fn (SfCase $case): int => $this->ttrDays($case, $monthEnd)),
            'sla_by_priority' => $this->slaByPriority($pool, $monthEnd),
        ];
    }

    /**
     * @param  Collection<int, SfCase>  $pool
     * @return list<array<string, mixed>>
     */
    private function slaByPriority(Collection $pool, CarbonImmutable $monthEnd): array
    {
        $rows = [];

        foreach (self::PRIORITY_ORDER as $priority) {
            $cases = $pool->filter(fn (SfCase $case): bool => $this->priorityBucket($case) === $priority);
            $resolved = $cases->filter(fn (SfCase $case): bool => $this->finishedAt($case)?->lt($monthEnd) ?? false);
            $open = $cases->filter(fn (SfCase $case): bool => $this->openAt($case, $monthEnd));
            $ttrDays = $resolved
                ->map(fn (SfCase $case): int => $this->ttrDays($case, $this->finishedAt($case)))
                ->values();
            $breach = $this->slaBreachPct($cases, $monthEnd);
            $breachedOpen = $open->filter(function (SfCase $case) use ($monthEnd): bool {
                return $this->ttrDays($case, $monthEnd) > $this->slaDays($case);
            })->count();

            $rows[] = [
                'priority' => $priority,
                'sla_days' => self::SLA_DAYS[$priority],
                'pool' => $cases->count(),
                'resolved' => $resolved->count(),
                'open' => $open->count(),
                'breached_open' => $breachedOpen,
                'sla_breach_pct' => $breach,
                'sla_hit_pct' => $cases->isEmpty() ? null : round(1 - $breach, 4),
                'zwing_sla_breach_pct' => $this->zwingSlaBreachPct($cases),
                'median_ttr_days' => $this->median($ttrDays),
                'p90_ttr_days' => $this->percentile($ttrDays, 90),
            ];
        }

        return $rows;
    }

    /**
     * @param  Collection<int, SfCase>  $newCases
     * @param  array<string, mixed>  $waterfall
     * @return array{csat_available: false, fcr_pct: float|null, repeat_pct: float|null, repeat_customers: int}
     */
    private function quality(Collection $newCases, array $waterfall): array
    {
        $customers = $newCases
            ->reject(fn (SfCase $case): bool => $this->isInternalAccount($case))
            ->groupBy(fn (SfCase $case): string => $case->account?->name ?: '(No account)');
        $repeatCustomers = $customers->filter(fn (Collection $rows): bool => $rows->count() >= 2)->count();
        $active = $customers->count();

        return [
            'csat_available' => false,
            'fcr_pct' => $waterfall['received'] > 0
                ? round($waterfall['resolved_l1'] / $waterfall['received'], 4)
                : null,
            'repeat_pct' => $active > 0 ? round($repeatCustomers / $active, 4) : null,
            'repeat_customers' => $repeatCustomers,
        ];
    }

    /**
     * @param  list<array{account_name: string, tickets: int, share: float}>  $customers
     * @return list<array{account_name: string, tickets: int, share: float}>
     */
    private function atRisk(array $customers): array
    {
        return collect($customers)
            ->filter(fn (array $row): bool => $row['tickets'] >= 2 || $row['share'] >= 0.2)
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SfCase>  $newCases
     * @return list<array{name: string, tickets: int, share: float}>
     */
    private function people(Collection $newCases): array
    {
        $total = $newCases->count();

        return $newCases
            ->groupBy(fn (SfCase $case): string => trim((string) ($case->agent_name ?: $case->owner_name)) ?: '(Unassigned)')
            ->map(fn (Collection $rows, string $name): array => [
                'name' => $name,
                'tickets' => $rows->count(),
                'share' => $total > 0 ? round($rows->count() / $total, 4) : 0.0,
            ])
            ->sortByDesc('tickets')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SfCase>  $newCases
     * @return Collection<int, float>
     */
    private function firstResponseHours(Collection $newCases): Collection
    {
        if ($newCases->isEmpty()) {
            return collect();
        }

        $firsts = SfCaseActivity::query()
            ->selectRaw('sf_case_id, MIN(occurred_at) as first_response_at')
            ->where('is_incoming', false)
            ->whereIn('sf_case_id', $newCases->pluck('id'))
            ->groupBy('sf_case_id')
            ->get();

        $casesById = $newCases->keyBy('id');

        return $firsts
            ->map(function (SfCaseActivity $row) use ($casesById): ?float {
                $case = $casesById->get($row->sf_case_id);

                if (! $case instanceof SfCase || $case->created_at_sf === null || $row->first_response_at === null) {
                    return null;
                }

                $firstResponse = CarbonImmutable::parse($row->first_response_at);

                if ($firstResponse->lt($case->created_at_sf)) {
                    return 0.0;
                }

                return round($case->created_at_sf->diffInMinutes($firstResponse) / 60, 2);
            })
            ->filter(fn (?float $hours): bool => $hours !== null)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $highlights
     * @param  array<string, mixed>  $selected
     * @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>  $waterfall
     * @param  array<string, mixed>  $customerLoad
     * @return array{risks: list<string>, actions: list<string>}
     */
    private function briefing(
        array $highlights,
        array $selected,
        ?array $previous,
        array $waterfall,
        array $customerLoad,
    ): array {
        $risks = [];
        $actions = [];

        if (($highlights['urgent_sla_breach_pct'] ?? 0) >= 0.4) {
            $risks[] = 'Urgent overall SLA miss is high.';
            $actions[] = 'Daily Urgent queue review. Cap age at 1 day.';
        }

        if (($highlights['zwing_urgent_sla_breach_pct'] ?? 0) >= 0.4) {
            $risks[] = 'Zwing-Tech hold on Urgent tickets is over 1 day.';
            $actions[] = 'Triage Zwing-Tech Urgent stints. Move how-to off L3.';
        }

        if (is_array($previous) && $selected['carry_forward'] > $previous['carry_forward']) {
            $risks[] = 'Carry-forward grew versus last month.';
            $actions[] = 'Clear oldest open tickets first.';
        }

        if ($waterfall['received'] > 0 && ($waterfall['to_l3'] / $waterfall['received']) >= 0.25) {
            $risks[] = 'L3 escalation share is high.';
            $actions[] = 'Shift-left playbook for top L3 modules.';
        }

        $top = $customerLoad['customers'][0] ?? null;

        if (is_array($top) && ($top['share'] ?? 0) >= 0.2) {
            $risks[] = $top['account_name'].' is over 20% of new volume.';
            $actions[] = 'Named account review with '.$top['account_name'].'.';
        }

        if (($highlights['fcr_pct'] ?? 1) !== null && ($highlights['fcr_pct'] ?? 1) < 0.5 && $waterfall['received'] > 0) {
            $risks[] = 'L1 resolution (FCR proxy) is under 50%.';
            $actions[] = 'L1 macros and known-error articles for top modules.';
        }

        return [
            'risks' => array_slice($risks, 0, 3),
            'actions' => array_slice($actions, 0, 3),
        ];
    }

    /**
     * @param  Collection<int, SfCase>  $cases
     */
    private function slaBreachPct(Collection $cases, CarbonImmutable $asOf): float
    {
        if ($cases->isEmpty()) {
            return 0.0;
        }

        $breached = $cases->filter(function (SfCase $case) use ($asOf): bool {
            $end = $this->finishedAt($case)?->min($asOf) ?? $asOf;

            return $this->ttrDays($case, $end) > $this->slaDays($case);
        })->count();

        return round($breached / $cases->count(), 4);
    }

    /**
     * @param  Collection<int, SfCase>  $cases
     */
    private function zwingSlaBreachPct(Collection $cases): float
    {
        if ($cases->isEmpty()) {
            return 0.0;
        }

        $breached = $cases->filter(function (SfCase $case): bool {
            return $this->zwingMinutes($case) > $this->slaDays($case) * 1440;
        })->count();

        return round($breached / $cases->count(), 4);
    }

    private function zwingMinutes(SfCase $case): int
    {
        if ($case->zwing_resolution_minutes !== null) {
            return (int) $case->zwing_resolution_minutes;
        }

        return (int) $case->groupHolds
            ->where('group_name', SalesforceCaseHoldComputer::ZWING_GROUP)
            ->sum('held_minutes');
    }

    /**
     * @return array{l1: bool, l2: bool, l3: bool, ba: bool, integration: bool, other: bool}
     */
    private function stages(SfCase $case): array
    {
        $names = collect([$case->first_assigned_group, $case->group_name])
            ->concat($case->groupHolds->pluck('group_name'))
            ->filter()
            ->map(fn (string $name): string => trim($name));

        $flags = [
            'l1' => false,
            'l2' => false,
            'l3' => false,
            'ba' => false,
            'integration' => false,
            'other' => false,
        ];

        foreach ($names as $name) {
            $flags[$this->stageForGroup($name)] = true;
        }

        return $flags;
    }

    private function stageForGroup(string $name): string
    {
        if (Str::contains($name, ['BA/Product', 'BA / Product'], ignoreCase: true)) {
            return 'ba';
        }

        if (Str::contains($name, 'Integration', ignoreCase: true)) {
            return 'integration';
        }

        if (Str::contains($name, ['Zwing-Tech', 'ZPOS Tech'], ignoreCase: true)) {
            return 'l3';
        }

        if (Str::contains($name, 'L2', ignoreCase: true)) {
            return 'l2';
        }

        if (Str::contains($name, 'L1', ignoreCase: true)) {
            return 'l1';
        }

        if (Str::contains($name, ['Project', 'Implementation', 'Infra', 'Compliance', 'Reports', 'Viewer', 'EMG'], ignoreCase: true)) {
            return 'other';
        }

        return 'l1';
    }

    private function isInternalAccount(SfCase $case): bool
    {
        $name = $case->account?->name;

        return is_string($name) && Str::contains($name, 'test', ignoreCase: true);
    }

    private function priorityBucket(SfCase $case): string
    {
        $priority = trim((string) $case->priority);

        return in_array($priority, self::PRIORITY_ORDER, true) ? $priority : 'Medium';
    }

    private function typeBucket(SfCase $case): string
    {
        $type = trim((string) $case->type);

        return in_array($type, ['Incident', 'Question', 'Service request'], true)
            ? $type
            : 'Others';
    }

    private function slaDays(SfCase $case): int
    {
        return self::SLA_DAYS[$this->priorityBucket($case)];
    }

    private function ttrDays(SfCase $case, ?CarbonInterface $end): int
    {
        if ($case->created_at_sf === null || $end === null) {
            return 0;
        }

        return (int) $case->created_at_sf->copy()->startOfDay()->diffInDays($end->copy()->startOfDay());
    }

    private function finishedAt(SfCase $case): ?CarbonInterface
    {
        return $case->resolved_at_sf ?? $case->closed_at_sf;
    }

    private function openAt(SfCase $case, CarbonImmutable $at): bool
    {
        if ($case->created_at_sf === null || $case->created_at_sf->gte($at)) {
            return false;
        }

        $finished = $this->finishedAt($case);

        return $finished === null || $finished->gte($at);
    }

    private function inMonth(?CarbonInterface $date, CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return $date !== null && $date->gte($start) && $date->lt($end);
    }

    /**
     * @param  Collection<int, int|float>  $values
     */
    private function median(Collection $values): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $sorted = $values->sort()->values();
        $count = $sorted->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $sorted[$middle];
        }

        return round(($sorted[$middle - 1] + $sorted[$middle]) / 2, 2);
    }

    /**
     * @param  Collection<int, int|float>  $values
     */
    private function percentile(Collection $values, int $percentile): ?float
    {
        if ($values->isEmpty()) {
            return null;
        }

        $sorted = $values->sort()->values();
        $index = (int) ceil(($percentile / 100) * $sorted->count()) - 1;

        return round((float) $sorted[max(0, $index)], 2);
    }
}
