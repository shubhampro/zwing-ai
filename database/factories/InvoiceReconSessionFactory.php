<?php

namespace Database\Factories;

use App\Models\InvoiceReconSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceReconSession>
 */
class InvoiceReconSessionFactory extends Factory
{
    protected $model = InvoiceReconSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true).' '.fake()->unique()->numerify('####'),
            'v_id' => fake()->numberBetween(1, 999),
            'source' => 'csv',
            'status' => 'pending',
        ];
    }

    public function fromConnection(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'connection',
            'zwing_file_name' => 'mysql_ssh',
            'erp_file_name' => 'pgsql connection',
            'date_from' => '2026-01-01',
            'date_to' => '2026-08-27',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'reconciled_at' => now(),
            'zwing_row_count' => 10,
            'erp_row_count' => 10,
            'zwing_processed_rows' => 10,
            'erp_processed_rows' => 10,
        ]);
    }
}
