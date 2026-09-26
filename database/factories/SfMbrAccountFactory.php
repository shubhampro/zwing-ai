<?php

namespace Database\Factories;

use App\Models\SfAccount;
use App\Models\SfApplication;
use App\Models\SfMbrAccount;
use App\Models\SfMbrReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SfMbrAccount>
 */
class SfMbrAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sf_mbr_report_id' => SfMbrReport::factory(),
            'sf_account_id' => SfAccount::factory(),
            'sf_application_id' => SfApplication::factory(),
            'backlog_ticket_count' => 0,
            'created_ticket_count' => 0,
            'resolved_or_closed_count' => 0,
            'open_ticket_count' => 0,
        ];
    }
}
