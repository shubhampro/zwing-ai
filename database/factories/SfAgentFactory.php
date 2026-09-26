<?php

namespace Database\Factories;

use App\Models\SfAgent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SfAgent>
 */
class SfAgentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sf_id' => fake()->unique()->regexify('005[A-Za-z0-9]{15}'),
            'name' => fake()->name(),
            'is_active' => true,
            'synced_at' => now()->subHour(),
        ];
    }
}
