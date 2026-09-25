<?php

namespace Database\Factories;

use App\Models\SfCase;
use App\Models\SfCaseGroupHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SfCaseGroupHistory>
 */
class SfCaseGroupHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sf_id' => fake()->unique()->regexify('017[A-Za-z0-9]{15}'),
            'sf_case_id' => SfCase::factory(),
            'field' => 'Group__c',
            'old_value' => 'ERP Helpdesk-L1',
            'new_value' => 'Zwing-Tech',
            'changed_by' => fake()->name(),
            'changed_at' => now()->subDay(),
            'synced_at' => now()->subHour(),
        ];
    }
}
