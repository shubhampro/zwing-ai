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
        'origin',
        'is_closed',
        'is_spam',
        'product',
        'product_name',
        'application',
        'module',
        'sub_module',
        'group_name',
        'first_assigned_group',
        'owner_name',
        'agent_name',
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
