<?php

namespace App\Services\Salesforce;

use App\Enums\SfMbrReportSection;
use App\Enums\SfMbrReportStatus;
use App\Models\SfCase;
use App\Models\SfMbrAccount;
use App\Models\SfMbrReport;
use App\Support\IndiaDateTime;
use Illuminate\Support\Collection;

class SfMbrAccountSegregator
{
    private const CHUNK_SIZE = 500;

    public function segregate(SfMbrReport $report): void
    {
        $report->accounts()->delete();

        $rows = $this->aggregate($report);

        $now = now();

        foreach ($rows->chunk(self::CHUNK_SIZE) as $chunk) {
            SfMbrAccount::query()->insert(
                $chunk->map(fn (object $row): array => [
                    'sf_mbr_report_id' => $report->id,
                    'sf_account_id' => $row->sf_account_id,
                    'sf_application_id' => $row->sf_application_id,
                    'backlog_ticket_count' => $row->backlog_ticket_count,
                    'created_ticket_count' => $row->created_ticket_count,
                    'resolved_or_closed_count' => $row->resolved_or_closed_count,
                    'open_ticket_count' => $row->open_ticket_count,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        }

        $report->update([
            'status' => SfMbrReportStatus::Ready,
            'current_section' => SfMbrReportSection::SegregateAccounts,
            'failed_reason' => null,
        ]);
    }

    /**
     * @return Collection<int, object{sf_account_id: int, sf_application_id: int, backlog_ticket_count: int, created_ticket_count: int, resolved_or_closed_count: int, open_ticket_count: int}>
     */
    private function aggregate(SfMbrReport $report): Collection
    {
        $start = IndiaDateTime::utcInclusiveStart($report->starts_on->toDateString())->toDateTimeString();
        $end = IndiaDateTime::utcExclusiveEnd($report->ends_on->toDateString())->toDateTimeString();
        $applicationIds = $report->applications()->pluck('sf_applications.id');

        return SfCase::query()
            ->toBase()
            ->selectRaw(
                'sf_account_id,
                sf_application_id,
                SUM(CASE WHEN created_at_sf < ? AND (resolved_at_sf IS NULL OR resolved_at_sf >= ?) AND (closed_at_sf IS NULL OR closed_at_sf >= ?) THEN 1 ELSE 0 END) as backlog_ticket_count,
                SUM(CASE WHEN created_at_sf >= ? AND created_at_sf < ? THEN 1 ELSE 0 END) as created_ticket_count,
                SUM(CASE WHEN (resolved_at_sf >= ? AND resolved_at_sf < ?) OR (closed_at_sf >= ? AND closed_at_sf < ?) THEN 1 ELSE 0 END) as resolved_or_closed_count,
                SUM(CASE WHEN created_at_sf < ? AND (resolved_at_sf IS NULL OR resolved_at_sf >= ?) AND (closed_at_sf IS NULL OR closed_at_sf >= ?) THEN 1 ELSE 0 END) as open_ticket_count',
                [$start, $start, $start, $start, $end, $start, $end, $start, $end, $end, $end, $end],
            )
            ->where('is_spam', false)
            ->whereNotNull('sf_account_id')
            ->whereNotNull('sf_application_id')
            ->when(
                $applicationIds->isNotEmpty(),
                fn ($query) => $query->whereIn('sf_application_id', $applicationIds),
            )
            ->where(function ($scope) use ($start, $end): void {
                $scope->where(function ($created) use ($start, $end): void {
                    $created->where('created_at_sf', '>=', $start)
                        ->where('created_at_sf', '<', $end);
                })->orWhere(function ($resolved) use ($start, $end): void {
                    $resolved->where('resolved_at_sf', '>=', $start)
                        ->where('resolved_at_sf', '<', $end);
                })->orWhere(function ($closed) use ($start, $end): void {
                    $closed->where('closed_at_sf', '>=', $start)
                        ->where('closed_at_sf', '<', $end);
                })->orWhere(function ($open) use ($end): void {
                    $open->where('created_at_sf', '<', $end)
                        ->where(function ($notResolved) use ($end): void {
                            $notResolved->whereNull('resolved_at_sf')
                                ->orWhere('resolved_at_sf', '>=', $end);
                        })
                        ->where(function ($notClosed) use ($end): void {
                            $notClosed->whereNull('closed_at_sf')
                                ->orWhere('closed_at_sf', '>=', $end);
                        });
                });
            })
            ->groupBy('sf_account_id', 'sf_application_id')
            ->get()
            ->map(fn (object $row): object => (object) [
                'sf_account_id' => (int) $row->sf_account_id,
                'sf_application_id' => (int) $row->sf_application_id,
                'backlog_ticket_count' => (int) $row->backlog_ticket_count,
                'created_ticket_count' => (int) $row->created_ticket_count,
                'resolved_or_closed_count' => (int) $row->resolved_or_closed_count,
                'open_ticket_count' => (int) $row->open_ticket_count,
            ])
            ->filter(fn (object $row): bool => $row->backlog_ticket_count > 0
                || $row->created_ticket_count > 0
                || $row->resolved_or_closed_count > 0
                || $row->open_ticket_count > 0)
            ->values();
    }
}
