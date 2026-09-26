<?php

namespace Database\Factories;

use App\Models\SfGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SfGroup>
 */
class SfGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 20),
            'is_active' => true,
            'synced_at' => now()->subHour(),
        ];
    }
}
