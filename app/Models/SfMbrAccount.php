<?php

namespace App\Models;

use Database\Factories\SfMbrAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SfMbrAccount extends Model
{
    /** @use HasFactory<SfMbrAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'sf_mbr_report_id',
        'sf_account_id',
        'sf_application_id',
        'backlog_ticket_count',
        'created_ticket_count',
        'resolved_or_closed_count',
        'open_ticket_count',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'backlog_ticket_count' => 0,
        'created_ticket_count' => 0,
        'resolved_or_closed_count' => 0,
        'open_ticket_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'backlog_ticket_count' => 'integer',
            'created_ticket_count' => 'integer',
            'resolved_or_closed_count' => 'integer',
            'open_ticket_count' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(SfMbrReport::class, 'sf_mbr_report_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(SfAccount::class, 'sf_account_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SfApplication::class, 'sf_application_id');
    }
}
