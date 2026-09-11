<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\ReportReconSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportReconSession>
 */
class ReportReconSessionFactory extends Factory
{
    protected $model = ReportReconSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true).' '.fake()->unique()->numerify('####'),
            'v_id' => fake()->numberBetween(1, 999),
            'organization_id' => Organization::factory(),
            'date_from' => '2026-08-01',
            'date_to' => '2026-08-31',
            'status' => 'pending',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'reconciled_at' => now(),
            'invoice_row_count' => 10,
            'mop_row_count' => 10,
            'invoice_processed_rows' => 10,
            'mop_processed_rows' => 10,
        ]);
    }
}
