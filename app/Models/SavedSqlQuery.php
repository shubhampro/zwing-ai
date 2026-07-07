<?php

namespace App\Models;

use Database\Factories\SavedSqlQueryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSqlQuery extends Model
{
    /** @use HasFactory<SavedSqlQueryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'sql',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
