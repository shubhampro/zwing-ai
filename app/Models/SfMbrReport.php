<?php

namespace App\Models;

use App\Enums\SfMbrReportSection;
use App\Enums\SfMbrReportStatus;
use App\Services\Salesforce\SfMbrSummaryFilter;
use App\Services\Salesforce\SfMbrTicketQuery;
use App\Support\IndiaDateTime;
use Carbon\CarbonInterface;
use Database\Factories\SfMbrReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class SfMbrReport extends Model
{
    /** @use HasFactory<SfMbrReportFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'starts_on',
        'ends_on',
        'status',
        'current_section',
        'failed_reason',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => SfMbrReportStatus::Pending->value,
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => SfMbrReportStatus::class,
            'current_section' => SfMbrReportSection::class,
        ];
    }

    /**
     * @param  Collection<int, SfApplication>  $applications
     */
    public static function titleFor(CarbonInterface $startsOn, CarbonInterface $endsOn, Collection $applications): string
    {
        $range = $startsOn->timezone(IndiaDateTime::TIMEZONE)->format('j M Y')
            .' – '
            .$endsOn->timezone(IndiaDateTime::TIMEZONE)->format('j M Y');

        if ($applications->isEmpty()) {
            return "All applications · {$range}";
        }

        return $applications->pluck('name')->implode(', ').' · '.$range;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function applications(): BelongsToMany
    {
        return $this->belongsToMany(SfApplication::class, 'sf_mbr_report_application');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SfMbrAccount::class);
    }

    /**
     * @return Collection<int, SfApplication>
     */
    public function summaryApplications(SfMbrTicketQuery $tickets): Collection
    {
        return $tickets->applicationOptions($this);
    }

    /**
     * @param  list<int>|null  $applicationIds
     * @return Collection<int, SfModule>
     */
    public function summaryModules(SfMbrTicketQuery $tickets, ?array $applicationIds = null): Collection
    {
        return $tickets->moduleOptions($this, new SfMbrSummaryFilter($applicationIds, null, true));
    }

    /**
     * @param  list<int>|null  $applicationIds
     * @param  list<int>|null  $moduleIds
     */
    public function summaryFilter(
        SfMbrTicketQuery $tickets,
        ?array $applicationIds,
        ?array $moduleIds,
        ?bool $includeMissingModule,
    ): SfMbrSummaryFilter {
        $availableApps = $this->summaryApplications($tickets)
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $selectedApps = $applicationIds === null
            ? $availableApps
            : $availableApps->intersect($applicationIds)->values();

        $allApps = $applicationIds === null || $selectedApps->count() === $availableApps->count();

        $availableModules = $this->summaryModules($tickets, $allApps ? null : $selectedApps->all())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        $includeMissing = $includeMissingModule ?? true;
        $selectedModules = $moduleIds === null
            ? $availableModules
            : $availableModules->intersect($moduleIds)->values();

        $allModules = ($moduleIds === null || $selectedModules->count() === $availableModules->count())
            && $includeMissing;

        return new SfMbrSummaryFilter(
            $allApps ? null : $selectedApps->all(),
            $allModules ? null : $selectedModules->all(),
            $allModules ? true : $includeMissing,
        );
    }
}
