<?php

namespace Database\Factories;

use App\Enums\ExternalQueryJobType;
use App\Enums\ExternalQueryStatus;
use App\Models\ExternalQueryLog;
use App\Models\StockReconSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalQueryLog>
 */
class ExternalQueryLogFactory extends Factory
{
    protected $model = ExternalQueryLog::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'stock_recon_session_id' => null,
            'job_type' => ExternalQueryJobType::PullStock,
            'status' => ExternalQueryStatus::Pending,
            'context' => [],
            'result' => null,
            'zwing_query_ms' => null,
            'erp_query_ms' => null,
            'failure_reason' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function forSession(StockReconSession $session): static
    {
        return $this->state(fn (): array => [
            'user_id' => $session->user_id,
            'stock_recon_session_id' => $session->id,
        ]);
    }

    public function syncRow(): static
    {
        return $this->state(fn (): array => [
            'job_type' => ExternalQueryJobType::SyncRow,
        ]);
    }

    public function logDetails(): static
    {
        return $this->state(fn (): array => [
            'job_type' => ExternalQueryJobType::LogDetails,
        ]);
    }

    public function qtySums(): static
    {
        return $this->state(fn (): array => [
            'job_type' => ExternalQueryJobType::QtySums,
        ]);
    }
}
