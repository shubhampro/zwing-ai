<?php

namespace Database\Factories;

use App\Models\SfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SfAccount>
 */
class SfAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sf_id' => fake()->unique()->regexify('001[A-Za-z0-9]{15}'),
            'name' => fake()->company(),
            'last_modified_at_sf' => now()->subDay(),
            'synced_at' => now()->subHour(),
        ];
    }
}
