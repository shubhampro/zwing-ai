<?php

namespace App\Models;

use Database\Factories\SfAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SfAccount extends Model
{
    /** @use HasFactory<SfAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'sf_id',
        'name',
        'last_modified_at_sf',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_modified_at_sf' => 'datetime',
            'synced_at' => 'datetime',
        ];
    }

    public function cases(): HasMany
    {
        return $this->hasMany(SfCase::class);
    }
}
