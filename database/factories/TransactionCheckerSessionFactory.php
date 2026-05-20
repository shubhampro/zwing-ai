<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\TransactionCheckerSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionCheckerSession>
 */
class TransactionCheckerSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'org_id' => Organization::factory(),
            'connection' => 'mysql_ssh',
            'transaction_type' => fake()->randomElement(['grn', 'grt', 'sst']),
            'database' => 'zw_mn_'.fake()->numberBetween(100, 999).'_'.fake()->word(),
            'summary' => [
                'total' => fake()->numberBetween(0, 500),
                'matched' => fake()->numberBetween(0, 100),
                'mismatch' => fake()->numberBetween(0, 10),
                'missing_details' => fake()->numberBetween(0, 10),
            ],
        ];
    }
}
