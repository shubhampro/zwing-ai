<?php

namespace App\Services\Salesforce;

use App\Enums\SfMbrReportStatus;
use App\Enums\SfMbrSlaPriority;
use App\Models\SfApplication;
use App\Models\SfCase;
use App\Models\SfMbrReport;
use App\Support\IndiaDateTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SfMbrSlaByPriorityProduct
{
    public function __construct(private SfMbrTicketQuery $ticketQuery) {}

    /**
     * @return array{title: string, period_label: string, help: array{pool: string, sla_breach: string, breach_percent: string}, groups: list<array{priority: string, sla_label: string, sla_hours: int, rows: list<array{application_id: int, application: string, pool: int, sla_breach: int, breach_percent: float|null}>, totals: array{pool: int, sla_breach: int, breach_percent: float|null}}>} | null
     */
    public function for(SfMbrReport $report, ?SfMbrSummaryFilter $filter = null): ?array
    {
        if ($report->status !== SfMbrReportStatus::Ready) {
            return null;
        }

        $filter ??= SfMbrSummaryFilter::none();
        $end = IndiaDateTime::utcExclusiveEnd($report->ends_on->toDateString());
        $rows = $this->poolTickets($report, $filter);

        return [
            'title' => 'SLA Breach, Priority Wise, Application Wise',
            'period_label' => $this->periodLabel($report),
            'help' => [
                'pool' => 'Workable pool for this priority and application: opening backlog plus tickets created in the report IST range. Spam, Low priority, and tickets without an account or application are excluded.',
                'sla_breach' => 'Pool tickets whose wall-clock time from created to resolve/close (or range end if still open) exceeds the priority SLA: Urgent 24h, High 48h, Medium 120h.',
                'breach_percent' => 'SLA Breach divided by Pool. Empty when the pool is zero.',
            ],
            'groups' => $this->groups($rows, $end),
        ];
    }

    /**
     * @return Collection<int, object{priority: string, sf_application_id: int, application_name: string, sort_order: int, created_at_sf: mixed, resolved_at_sf: mixed, closed_at_sf: mixed, resolution_minutes: mixed}>
     */
    private function poolTickets(SfMbrReport $report, SfMbrSummaryFilter $filter): Collection
    {
        $casesTable = (new SfCase)->getTable();
        $applicationsTable = (new SfApplication)->getTable();

        return SfCase::query()
            ->tap(fn ($query) => $this->ticketQuery->constrain($query, $report, $filter))
            ->tap(fn ($query) => $this->ticketQuery->constrainWorkablePool($query, $report))
            ->whereIn('priority', SfMbrSlaPriority::values())
            ->join($applicationsTable, "{$applicationsTable}.id", '=', "{$casesTable}.sf_application_id")
            ->toBase()
            ->select([
                "{$casesTable}.priority",
                "{$casesTable}.sf_application_id",
                "{$applicationsTable}.name as application_name",
                "{$applicationsTable}.sort_order",
                "{$casesTable}.created_at_sf",
                "{$casesTable}.resolved_at_sf",
                "{$casesTable}.closed_at_sf",
                "{$casesTable}.resolution_minutes",
            ])
            ->get();
    }

    /**
     * @param  Collection<int, object{priority: string, sf_application_id: int, application_name: string, sort_order: int, created_at_sf: mixed, resolved_at_sf: mixed, closed_at_sf: mixed, resolution_minutes: mixed}>  $rows
     * @return list<array{priority: string, sla_label: string, sla_hours: int, rows: list<array{application_id: int, application: string, pool: int, sla_breach: int, breach_percent: float|null}>, totals: array{pool: int, sla_breach: int, breach_percent: float|null}}>
     */
    private function groups(Collection $rows, CarbonInterface $rangeEnd): array
    {
        $counted = $rows
            ->map(function (object $row) use ($rangeEnd): object {
                $priority = SfMbrSlaPriority::from((string) $row->priority);

                return (object) [
                    'priority' => $priority,
                    'sf_application_id' => (int) $row->sf_application_id,
                    'application_name' => (string) $row->application_name,
                    'sort_order' => (int) $row->sort_order,
                    'breached' => $this->elapsedMinutes($row, $rangeEnd) > $priority->hours() * 60,
                ];
            })
            ->groupBy(fn (object $row): string => $row->priority->value);

        return collect(SfMbrSlaPriority::cases())
            ->map(function (SfMbrSlaPriority $priority) use ($counted): array {
                $appRows = ($counted->get($priority->value) ?? collect())
                    ->groupBy(fn (object $row): int => $row->sf_application_id)
                    ->map(function (Collection $tickets): array {
                        $first = $tickets->first();
                        $pool = $tickets->count();
                        $breach = $tickets->where('breached', true)->count();

                        return [
                            'application_id' => $first->sf_application_id,
                            'application' => $first->application_name,
                            'sort_order' => $first->sort_order,
                            'pool' => $pool,
                            'sla_breach' => $breach,
                            'breach_percent' => $this->percent($breach, $pool),
                        ];
                    })
                    ->sortBy([
                        ['sort_order', 'asc'],
                        ['application', 'asc'],
                    ])
                    ->map(fn (array $row): array => [
                        'application_id' => $row['application_id'],
                        'application' => $row['application'],
                        'pool' => $row['pool'],
                        'sla_breach' => $row['sla_breach'],
                        'breach_percent' => $row['breach_percent'],
                    ])
                    ->values();

                $pool = (int) $appRows->sum('pool');
                $breach = (int) $appRows->sum('sla_breach');

                return [
                    'priority' => $priority->value,
                    'sla_label' => $priority->label(),
                    'sla_hours' => $priority->hours(),
                    'rows' => $appRows->all(),
                    'totals' => [
                        'pool' => $pool,
                        'sla_breach' => $breach,
                        'breach_percent' => $this->percent($breach, $pool),
                    ],
                ];
            })
            ->all();
    }

    private function elapsedMinutes(object $row, CarbonInterface $rangeEnd): int
    {
        $created = Carbon::parse($row->created_at_sf);
        $finishedAt = $row->resolved_at_sf ?? $row->closed_at_sf;

        if ($finishedAt !== null) {
            $finished = Carbon::parse($finishedAt);

            if ($finished->lt($rangeEnd)) {
                if ($row->resolution_minutes !== null) {
                    return (int) $row->resolution_minutes;
                }

                return (int) round($created->diffInMinutes($finished, absolute: true));
            }
        }

        return (int) round($created->diffInMinutes($rangeEnd, absolute: true));
    }

    private function percent(int $part, int $whole): ?float
    {
        if ($whole === 0) {
            return null;
        }

        return round($part / $whole * 100, 2);
    }

    private function periodLabel(SfMbrReport $report): string
    {
        $start = $report->starts_on?->copy()->timezone(IndiaDateTime::TIMEZONE);
        $end = $report->ends_on?->copy()->timezone(IndiaDateTime::TIMEZONE);

        if ($start === null || $end === null) {
            return 'Period';
        }

        if ($start->isSameMonth($end)) {
            return $start->format('F Y');
        }

        return IndiaDateTime::date($start).' – '.IndiaDateTime::date($end);
    }
}
