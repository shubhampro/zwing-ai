<?php

namespace App\Services\Salesforce;

use App\Enums\SfMbrReportStatus;
use App\Models\SfCase;
use App\Models\SfMbrAccount;
use App\Models\SfMbrReport;
use App\Support\IndiaDateTime;
use Illuminate\Support\Collection;

class SfMbrOverallSummary
{
    public function __construct(private SfMbrTicketQuery $ticketQuery) {}

    /**
     * @param  Collection<int, SfMbrAccount>  $accounts
     * @return array{title: string, period_label: string, new_tickets: int, workable_pool: int, closed_or_resolved: int, resolved_percent: float|null, carry_forward: int, help: array{new_tickets: string, workable_pool: string, closed_or_resolved: string, resolved_percent: string, carry_forward: string}}|null
     */
    public function for(SfMbrReport $report, Collection $accounts, ?SfMbrSummaryFilter $filter = null): ?array
    {
        if ($report->status !== SfMbrReportStatus::Ready) {
            return null;
        }

        $filter ??= SfMbrSummaryFilter::none();

        [$newTickets, $backlog, $closedOrResolved, $carryForward] = $filter->isNarrowed()
            ? $this->countsFromCases($report, $filter)
            : $this->countsFromAccounts($accounts);

        $workablePool = $backlog + $newTickets;

        return [
            'title' => 'Ticket Volume, Closure and Carry-forward',
            'period_label' => $this->periodLabel($report),
            'new_tickets' => $newTickets,
            'workable_pool' => $workablePool,
            'closed_or_resolved' => $closedOrResolved,
            'resolved_percent' => $workablePool > 0
                ? round($closedOrResolved / $workablePool * 100, 2)
                : null,
            'carry_forward' => $carryForward,
            'help' => [
                'new_tickets' => 'Tickets created during this report IST range. Spam and tickets without an account or application are excluded.',
                'workable_pool' => 'Opening backlog (still open at range start) plus new tickets. This is the full set the team could work, including carry-forward.',
                'closed_or_resolved' => 'Distinct tickets resolved or closed during the range, including backlog that closed in this period.',
                'resolved_percent' => 'Closed/Resolved divided by Workable pool. Empty when the pool is zero.',
                'carry_forward' => 'Tickets still open at range end. These become next period backlog.',
            ],
        ];
    }

    /**
     * @param  Collection<int, SfMbrAccount>  $accounts
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function countsFromAccounts(Collection $accounts): array
    {
        return [
            (int) $accounts->sum('created_ticket_count'),
            (int) $accounts->sum('backlog_ticket_count'),
            (int) $accounts->sum('resolved_or_closed_count'),
            (int) $accounts->sum('open_ticket_count'),
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function countsFromCases(SfMbrReport $report, SfMbrSummaryFilter $filter): array
    {
        [$start, $end] = $this->ticketQuery->rangeBounds($report);

        $row = SfCase::query()
            ->tap(fn ($query) => $this->ticketQuery->constrain($query, $report, $filter))
            ->tap(fn ($query) => $this->ticketQuery->constrainReportInclusion($query, $report))
            ->toBase()
            ->selectRaw(
                'SUM(CASE WHEN created_at_sf < ? AND (resolved_at_sf IS NULL OR resolved_at_sf >= ?) AND (closed_at_sf IS NULL OR closed_at_sf >= ?) THEN 1 ELSE 0 END) as backlog_ticket_count,
                SUM(CASE WHEN created_at_sf >= ? AND created_at_sf < ? THEN 1 ELSE 0 END) as created_ticket_count,
                SUM(CASE WHEN (resolved_at_sf >= ? AND resolved_at_sf < ?) OR (closed_at_sf >= ? AND closed_at_sf < ?) THEN 1 ELSE 0 END) as resolved_or_closed_count,
                SUM(CASE WHEN created_at_sf < ? AND (resolved_at_sf IS NULL OR resolved_at_sf >= ?) AND (closed_at_sf IS NULL OR closed_at_sf >= ?) THEN 1 ELSE 0 END) as open_ticket_count',
                [$start, $start, $start, $start, $end, $start, $end, $start, $end, $end, $end, $end],
            )
            ->first();

        return [
            (int) ($row->created_ticket_count ?? 0),
            (int) ($row->backlog_ticket_count ?? 0),
            (int) ($row->resolved_or_closed_count ?? 0),
            (int) ($row->open_ticket_count ?? 0),
        ];
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
