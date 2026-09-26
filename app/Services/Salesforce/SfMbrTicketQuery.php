<?php

namespace App\Services\Salesforce;

use App\Models\SfApplication;
use App\Models\SfCase;
use App\Models\SfMbrReport;
use App\Models\SfModule;
use App\Support\IndiaDateTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SfMbrTicketQuery
{
    /**
     * @param  Builder<SfCase>  $query
     */
    public function constrain(Builder $query, SfMbrReport $report, SfMbrSummaryFilter $filter): void
    {
        $applicationIds = $report->applications()->pluck('sf_applications.id');

        $query->where('is_spam', false)
            ->whereNotNull('sf_account_id')
            ->whereNotNull('sf_application_id');

        if ($filter->applicationIds !== null) {
            if ($filter->applicationIds === []) {
                $query->whereRaw('0 = 1');
            } else {
                $query->whereIn('sf_application_id', $filter->applicationIds);
            }
        } elseif ($applicationIds->isNotEmpty()) {
            $query->whereIn('sf_application_id', $applicationIds);
        }

        if ($filter->moduleIds !== null) {
            $query->where(function ($scope) use ($filter): void {
                if ($filter->moduleIds !== []) {
                    $scope->whereIn('sf_module_id', $filter->moduleIds);
                }

                if ($filter->includeMissingModule) {
                    $scope->orWhereNull('sf_module_id');
                }

                if ($filter->moduleIds === [] && ! $filter->includeMissingModule) {
                    $scope->whereRaw('0 = 1');
                }
            });
        }
    }

    /**
     * @param  Builder<SfCase>  $query
     */
    public function constrainWorkablePool(Builder $query, SfMbrReport $report): void
    {
        [$startAt, $endAt] = $this->rangeBounds($report);

        $query->where(function ($scope) use ($startAt, $endAt): void {
            $scope->where(function ($created) use ($startAt, $endAt): void {
                $created->where('created_at_sf', '>=', $startAt)
                    ->where('created_at_sf', '<', $endAt);
            })->orWhere(function ($backlog) use ($startAt): void {
                $backlog->where('created_at_sf', '<', $startAt)
                    ->where(function ($notResolved) use ($startAt): void {
                        $notResolved->whereNull('resolved_at_sf')
                            ->orWhere('resolved_at_sf', '>=', $startAt);
                    })
                    ->where(function ($notClosed) use ($startAt): void {
                        $notClosed->whereNull('closed_at_sf')
                            ->orWhere('closed_at_sf', '>=', $startAt);
                    });
            });
        });
    }

    /**
     * @param  Builder<SfCase>  $query
     */
    public function constrainReportInclusion(Builder $query, SfMbrReport $report): void
    {
        [$startAt, $endAt] = $this->rangeBounds($report);

        $query->where(function ($scope) use ($startAt, $endAt): void {
            $scope->where(function ($created) use ($startAt, $endAt): void {
                $created->where('created_at_sf', '>=', $startAt)
                    ->where('created_at_sf', '<', $endAt);
            })->orWhere(function ($resolved) use ($startAt, $endAt): void {
                $resolved->where('resolved_at_sf', '>=', $startAt)
                    ->where('resolved_at_sf', '<', $endAt);
            })->orWhere(function ($closed) use ($startAt, $endAt): void {
                $closed->where('closed_at_sf', '>=', $startAt)
                    ->where('closed_at_sf', '<', $endAt);
            })->orWhere(function ($open) use ($endAt): void {
                $open->where('created_at_sf', '<', $endAt)
                    ->where(function ($notResolved) use ($endAt): void {
                        $notResolved->whereNull('resolved_at_sf')
                            ->orWhere('resolved_at_sf', '>=', $endAt);
                    })
                    ->where(function ($notClosed) use ($endAt): void {
                        $notClosed->whereNull('closed_at_sf')
                            ->orWhere('closed_at_sf', '>=', $endAt);
                    });
            });
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function rangeBounds(SfMbrReport $report): array
    {
        return [
            IndiaDateTime::utcInclusiveStart($report->starts_on->toDateString())->toDateTimeString(),
            IndiaDateTime::utcExclusiveEnd($report->ends_on->toDateString())->toDateTimeString(),
        ];
    }

    /**
     * @return Collection<int, SfApplication>
     */
    public function applicationOptions(SfMbrReport $report): Collection
    {
        if ($report->applications->isNotEmpty()) {
            return $report->applications->sortBy([
                ['sort_order', 'asc'],
                ['name', 'asc'],
            ])->values();
        }

        return SfApplication::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @return Collection<int, SfModule>
     */
    public function moduleOptions(SfMbrReport $report, SfMbrSummaryFilter $filter): Collection
    {
        $ids = SfCase::query()
            ->whereNotNull('sf_module_id')
            ->tap(fn (Builder $query) => $this->constrain(
                $query,
                $report,
                new SfMbrSummaryFilter($filter->applicationIds, null, true),
            ))
            ->tap(fn (Builder $query) => $this->constrainReportInclusion($query, $report))
            ->distinct()
            ->pluck('sf_module_id');

        return SfModule::query()
            ->whereIn('id', $ids)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
