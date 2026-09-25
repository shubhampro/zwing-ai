<?php

namespace Database\Factories;

use App\Models\SfCase;
use App\Models\SfCaseActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SfCaseActivity>
 */
class SfCaseActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sf_id' => fake()->unique()->regexify('00a[A-Za-z0-9]{15}'),
            'sf_case_id' => SfCase::factory(),
            'source' => SfCaseActivity::SOURCE_COMMENT,
            'type' => 'Public',
            'subject' => null,
            'body' => fake()->paragraph(),
            'author_name' => fake()->name(),
            'is_incoming' => false,
            'occurred_at' => now()->subDay(),
            'synced_at' => now()->subHour(),
        ];
    }
}
