<?php

namespace App\Models;

use Database\Factories\SfCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SfCase extends Model
{
    /** @use HasFactory<SfCaseFactory> */
    use HasFactory;

    protected $fillable = [
        'sf_id',
        'case_number',
        'subject',
        'description',
        'status',
        'priority',
        'type',
        'sf_type_id',
        'origin',
        'is_closed',
        'is_spam',
        'product',
        'sf_product_id',
        'product_name',
        'application',
        'sf_application_id',
        'module',
        'sf_module_id',
        'sub_module',
        'sf_sub_module_id',
        'group_name',
        'sf_group_id',
        'first_assigned_group',
        'sf_first_assigned_group_id',
        'owner_name',
        'agent_name',
        'sf_agent_id',
        'sf_account_id',
        'requester_name',
        'tags',
        'size',
        'jira_id',
        'jira_status',
        'created_at_sf',
        'resolved_at_sf',
        'closed_at_sf',
        'last_modified_at_sf',
        'synced_at',
        'resolution_minutes',
        'zwing_resolution_minutes',
        'activity_summary',
        'activity_summarized_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_closed' => false,
        'is_spam' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_closed' => 'boolean',
            'is_spam' => 'boolean',
            'created_at_sf' => 'datetime',
            'resolved_at_sf' => 'datetime',
            'closed_at_sf' => 'datetime',
            'last_modified_at_sf' => 'datetime',
            'synced_at' => 'datetime',
            'resolution_minutes' => 'integer',
            'zwing_resolution_minutes' => 'integer',
            'activity_summarized_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SfAccount::class, 'sf_account_id');
    }

    public function sfProduct(): BelongsTo
    {
        return $this->belongsTo(SfProduct::class, 'sf_product_id');
    }

    public function sfApplication(): BelongsTo
    {
        return $this->belongsTo(SfApplication::class, 'sf_application_id');
    }

    public function sfModule(): BelongsTo
    {
        return $this->belongsTo(SfModule::class, 'sf_module_id');
    }

    public function sfSubModule(): BelongsTo
    {
        return $this->belongsTo(SfSubModule::class, 'sf_sub_module_id');
    }

    public function sfType(): BelongsTo
    {
        return $this->belongsTo(SfType::class, 'sf_type_id');
    }

    public function sfGroup(): BelongsTo
    {
        return $this->belongsTo(SfGroup::class, 'sf_group_id');
    }

    public function firstAssignedGroup(): BelongsTo
    {
        return $this->belongsTo(SfGroup::class, 'sf_first_assigned_group_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(SfAgent::class, 'sf_agent_id');
    }

    public function groupHistories(): HasMany
    {
        return $this->hasMany(SfCaseGroupHistory::class);
    }

    public function groupHolds(): HasMany
    {
        return $this->hasMany(SfCaseGroupHold::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SfCaseActivity::class);
    }

    /**
     * @param  Builder<SfCase>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner->whereLike('case_number', $like)
                ->orWhereLike('subject', $like)
                ->orWhereLike('description', $like)
                ->orWhereLike('requester_name', $like)
                ->orWhereLike('owner_name', $like)
                ->orWhereLike('agent_name', $like)
                ->orWhereLike('jira_id', $like)
                ->orWhereLike('tags', $like)
                ->orWhereLike('activity_summary', $like)
                ->orWhereHas(
                    'account',
                    fn (Builder $account): Builder => $account->whereLike('name', $like),
                );
        });
    }
}
