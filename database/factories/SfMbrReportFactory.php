<?php

namespace Database\Factories;

use App\Enums\SfMbrReportStatus;
use App\Models\SfMbrReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SfMbrReport>
 */
class SfMbrReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'All applications · 1 Mar 2026 – 31 Mar 2026',
            'starts_on' => '2026-03-01',
            'ends_on' => '2026-03-31',
            'status' => SfMbrReportStatus::Ready,
            'current_section' => null,
            'failed_reason' => null,
        ];
    }
}
