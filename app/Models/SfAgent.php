<?php

namespace App\Models;

use Database\Factories\SfAgentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SfAgent extends Model
{
    /** @use HasFactory<SfAgentFactory> */
    use HasFactory;

    protected $fillable = [
        'sf_id',
        'name',
        'is_active',
        'synced_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
