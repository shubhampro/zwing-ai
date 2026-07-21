<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbHealthCheck extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ran_at',
        'overall_status',
        'results',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
            'results' => 'array',
        ];
    }
}
