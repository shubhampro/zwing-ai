<?php

namespace Database\Factories;

use App\Models\SfCase;
use App\Models\SfCaseGroupHold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SfCaseGroupHold>
 */
class SfCaseGroupHoldFactory extends Factory
{
    public function definition(): array
    {
        $startedAt = now()->subDays(2);
        $endedAt = now()->subDay();

        return [
            'sf_case_id' => SfCase::factory(),
            'group_name' => 'Zwing-Tech',
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'held_minutes' => (int) round($startedAt->diffInMinutes($endedAt, absolute: true)),
            'is_open' => false,
            'computed_at' => now()->subHour(),
        ];
    }
}
