<?php

namespace App\Models;

use Database\Factories\SfModuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SfModule extends Model
{
    /** @use HasFactory<SfModuleFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
        'synced_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }
}
