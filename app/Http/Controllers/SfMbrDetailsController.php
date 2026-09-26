<?php

namespace App\Http\Controllers;

use App\Models\SfApplication;
use App\Models\SfMbrAccount;
use App\Models\SfMbrReport;
use Inertia\Inertia;
use Inertia\Response;

class SfMbrDetailsController extends Controller
{
    public function show(SfMbrReport $sfMbrReport): Response
    {
        $sfMbrReport->load([
            'applications:id,name',
            'accounts' => fn ($query) => $query
                ->with(['account:id,name', 'application:id,name'])
                ->orderBy('id'),
        ]);

        $accounts = $sfMbrReport->accounts
            ->sortBy(fn (SfMbrAccount $row): string => mb_strtolower($row->account?->name ?? '').'|'.mb_strtolower($row->application?->name ?? ''))
            ->values();

        return Inertia::render('sf-mbr/details', [
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
            'accounts' => $accounts
                ->map(fn (SfMbrAccount $row): array => [
                    'id' => $row->id,
                    'account' => $row->account?->name,
                    'application' => $row->application?->name,
                    'backlog_ticket_count' => $row->backlog_ticket_count,
                    'created_ticket_count' => $row->created_ticket_count,
                    'resolved_or_closed_count' => $row->resolved_or_closed_count,
                    'open_ticket_count' => $row->open_ticket_count,
                ])
                ->all(),
        ]);
    }
}
