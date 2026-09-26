<?php

namespace App\Http\Controllers;

use App\Http\Requests\SfMbrOverallSummaryRequest;
use App\Models\SfApplication;
use App\Models\SfMbrReport;
use App\Models\SfModule;
use App\Services\Salesforce\SfMbrOverallSummary;
use App\Services\Salesforce\SfMbrSlaByPriorityProduct;
use App\Services\Salesforce\SfMbrSummaryFilter;
use App\Services\Salesforce\SfMbrTicketQuery;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SfMbrOverallSummaryController extends Controller
{
    public function __construct(
        private SfMbrOverallSummary $overallSummary,
        private SfMbrSlaByPriorityProduct $slaByPriorityProduct,
        private SfMbrTicketQuery $ticketQuery,
    ) {}

    public function show(SfMbrOverallSummaryRequest $request, SfMbrReport $sfMbrReport): Response
    {
        $sfMbrReport->load([
            'applications:id,name,sort_order',
            'accounts',
        ]);

        $filter = $sfMbrReport->summaryFilter(
            $this->ticketQuery,
            $request->applicationIds(),
            $request->moduleIds(),
            $request->includeNoModule(),
        );

        $applications = $sfMbrReport->summaryApplications($this->ticketQuery);
        $modules = $sfMbrReport->summaryModules($this->ticketQuery, $filter->applicationIds);

        return Inertia::render('sf-mbr/summary', [
            'report' => [
                'id' => $sfMbrReport->id,
                'title' => $sfMbrReport->title,
                'starts_on' => $sfMbrReport->starts_on?->toDateString(),
                'ends_on' => $sfMbrReport->ends_on?->toDateString(),
                'status' => $sfMbrReport->status->value,
                'applications' => $sfMbrReport->applications
                    ->map(fn (SfApplication $application): array => [
                        'id' => $application->id,
                        'name' => $application->name,
                    ])
                    ->values()
                    ->all(),
                'all_applications' => $sfMbrReport->applications->isEmpty(),
            ],
            'filters' => $this->filterState($filter, $applications, $modules),
            'filter_options' => [
                'applications' => $applications
                    ->map(fn (SfApplication $application): array => [
                        'id' => $application->id,
                        'name' => $application->name,
                    ])
                    ->values()
                    ->all(),
                'modules' => $modules
                    ->map(fn (SfModule $module): array => [
                        'id' => $module->id,
                        'name' => $module->name,
                    ])
                    ->values()
                    ->all(),
            ],
            'summary' => $this->overallSummary->for($sfMbrReport, $sfMbrReport->accounts, $filter),
            'sla' => $this->slaByPriorityProduct->for($sfMbrReport, $filter),
        ]);
    }

    /**
     * @param  Collection<int, SfApplication>  $applications
     * @param  Collection<int, SfModule>  $modules
     * @return array{application_ids: list<int>, module_ids: list<int>, include_no_module: bool, all_applications: bool, all_modules: bool}
     */
    private function filterState(SfMbrSummaryFilter $filter, $applications, $modules): array
    {
        $applicationIds = $filter->applicationIds
            ?? $applications->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
        $moduleIds = $filter->moduleIds
            ?? $modules->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();

        return [
            'application_ids' => $applicationIds,
            'module_ids' => $moduleIds,
            'include_no_module' => $filter->moduleIds === null ? true : $filter->includeMissingModule,
            'all_applications' => $filter->applicationIds === null,
            'all_modules' => $filter->moduleIds === null,
        ];
    }
}
