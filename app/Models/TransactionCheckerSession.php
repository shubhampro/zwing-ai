<?php

namespace App\Models;

use Database\Factories\TransactionCheckerSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionCheckerSession extends Model
{
    /** @use HasFactory<TransactionCheckerSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'org_id',
        'connection',
        'transaction_type',
        'database',
        'summary',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }
}
