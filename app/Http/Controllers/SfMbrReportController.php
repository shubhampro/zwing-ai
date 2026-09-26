<?php

namespace App\Http\Controllers;

use App\Enums\SfMbrReportSection;
use App\Enums\SfMbrReportStatus;
use App\Http\Requests\StoreSfMbrReportRequest;
use App\Jobs\SegregateSfMbrAccountsJob;
use App\Models\SfApplication;
use App\Models\SfMbrReport;
use App\Services\Salesforce\SalesforceCasePuller;
use App\Support\IndiaDateTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class SfMbrReportController extends Controller
{
    public function index(): Response
    {
        $reports = SfMbrReport::query()
            ->with(['applications:id,name', 'user:id,name'])
            ->latest()
            ->get();

        return Inertia::render('sf-mbr/index', [
            'reports' => $reports->map($this->listRow(...))->values()->all(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('sf-mbr/create', [
            'applications' => SfApplication::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'defaults' => [
                'starts_on' => Carbon::parse(SalesforceCasePuller::CREATED_SINCE)
                    ->timezone(IndiaDateTime::TIMEZONE)
                    ->toDateString(),
                'ends_on' => Carbon::now(IndiaDateTime::TIMEZONE)->toDateString(),
            ],
        ]);
    }

    public function store(StoreSfMbrReportRequest $request): RedirectResponse
    {
        $startsOn = Carbon::parse($request->validated('starts_on'), IndiaDateTime::TIMEZONE)->startOfDay();
        $endsOn = Carbon::parse($request->validated('ends_on'), IndiaDateTime::TIMEZONE)->startOfDay();
        $applicationIds = $request->validated('application_ids') ?? [];

        $applications = $applicationIds === []
            ? collect()
            : SfApplication::query()
                ->whereIn('id', $applicationIds)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

        $report = SfMbrReport::query()->create([
            'user_id' => $request->user()?->id,
            'title' => SfMbrReport::titleFor($startsOn, $endsOn, $applications),
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'status' => SfMbrReportStatus::Generating,
            'current_section' => SfMbrReportSection::SegregateAccounts,
            'failed_reason' => null,
        ]);

        $report->applications()->sync($applications->pluck('id'));

        SegregateSfMbrAccountsJob::dispatch($report->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Preparing MBR report.'),
        ]);

        return to_route('sf-mbr.show', $report);
    }

    public function show(SfMbrReport $sfMbrReport): Response
    {
        $sfMbrReport->load(['applications:id,name']);

        return Inertia::render('sf-mbr/show', [
            'report' => [
                'id' => $sfMbrReport->id,
                'title' => $sfMbrReport->title,
                'starts_on' => $sfMbrReport->starts_on?->toDateString(),
                'ends_on' => $sfMbrReport->ends_on?->toDateString(),
                'status' => $sfMbrReport->status->value,
                'failed_reason' => $sfMbrReport->failed_reason,
                'applications' => $sfMbrReport->applications
                    ->map(fn (SfApplication $application): array => [
                        'id' => $application->id,
                        'name' => $application->name,
                    ])
                    ->values()
                    ->all(),
                'all_applications' => $sfMbrReport->applications->isEmpty(),
            ],
            'generation' => [
                'status' => $sfMbrReport->status->value,
                'failed_reason' => $sfMbrReport->failed_reason,
                'sections' => $this->generationSections($sfMbrReport),
            ],
        ]);
    }

    public function destroy(SfMbrReport $sfMbrReport): RedirectResponse
    {
        $title = $sfMbrReport->title;

        $sfMbrReport->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('MBR report ":title" deleted.', ['title' => $title]),
        ]);

        return to_route('sf-mbr.index');
    }

    /**
     * @return list<array{key: string, label: string, status: string}>
     */
    private function generationSections(SfMbrReport $report): array
    {
        return collect(SfMbrReportSection::cases())
            ->map(fn (SfMbrReportSection $section): array => [
                'key' => $section->value,
                'label' => $section->label(),
                'status' => $this->sectionStatus($report, $section),
            ])
            ->values()
            ->all();
    }

    private function sectionStatus(SfMbrReport $report, SfMbrReportSection $section): string
    {
        if ($report->status === SfMbrReportStatus::Ready) {
            return 'done';
        }

        $sections = SfMbrReportSection::cases();
        $current = $report->current_section;
        $sectionIndex = array_search($section, $sections, true);
        $currentIndex = $current instanceof SfMbrReportSection
            ? array_search($current, $sections, true)
            : false;

        if ($currentIndex === false) {
            return $report->status === SfMbrReportStatus::Failed ? 'failed' : 'pending';
        }

        if ($sectionIndex < $currentIndex) {
            return 'done';
        }

        if ($sectionIndex > $currentIndex) {
            return 'pending';
        }

        return $report->status === SfMbrReportStatus::Failed ? 'failed' : 'running';
    }

    /**
     * @return array{id: int, title: string, starts_on: string|null, ends_on: string|null, status: string, applications: list<string>, all_applications: bool, created_by: string|null, created_at: string|null}
     */
    private function listRow(SfMbrReport $report): array
    {
        return [
            'id' => $report->id,
            'title' => $report->title,
            'starts_on' => $report->starts_on?->toDateString(),
            'ends_on' => $report->ends_on?->toDateString(),
            'status' => $report->status->value,
            'applications' => $report->applications->pluck('name')->values()->all(),
            'all_applications' => $report->applications->isEmpty(),
            'created_by' => $report->user?->name,
            'created_at' => $report->created_at?->toIso8601String(),
        ];
    }
}
