<?php

namespace Database\Factories;

use App\Enums\TransactionReconType;
use App\Models\TransactionReconSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionReconSession>
 */
class TransactionReconSessionFactory extends Factory
{
    protected $model = TransactionReconSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'type' => TransactionReconType::Packet,
            'v_id' => fake()->numberBetween(1, 999),
            'source' => 'connection',
            'organization_id' => null,
            'pgsql_connection_id' => null,
            'zwing_file_name' => 'mysql_ssh',
            'erp_file_name' => 'pgsql connection',
            'status' => 'pending',
        ];
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
