<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'ba_code' => strtoupper(fake()->unique()->bothify('BA-####')),
            'vendor_id' => fake()->numberBetween(1000, 9999),
        ];
    }
}
