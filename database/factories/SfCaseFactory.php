<?php

namespace Database\Factories;

use App\Models\SfCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SfCase>
 */
class SfCaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sf_id' => fake()->unique()->regexify('500[A-Za-z0-9]{15}'),
            'case_number' => fake()->unique()->numerify('00######'),
            'subject' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'status' => 'Open',
            'priority' => 'Medium',
            'type' => 'Incident',
            'origin' => 'Portal',
            'is_closed' => false,
            'is_spam' => false,
            'product' => 'Zwing',
            'product_name' => 'Zwing (Cloud POS)',
            'application' => 'Zwing (Cloud POS)',
            'module' => 'Zwing Console',
            'sub_module' => null,
            'group_name' => 'Zwing-Tech',
            'first_assigned_group' => 'ERP Helpdesk-L1',
            'owner_name' => fake()->name(),
            'agent_name' => fake()->name(),
            'sf_account_id' => null,
            'requester_name' => fake()->name(),
            'tags' => null,
            'size' => 'XS',
            'jira_id' => null,
            'jira_status' => null,
            'created_at_sf' => now()->subDays(2),
            'resolved_at_sf' => null,
            'closed_at_sf' => null,
            'last_modified_at_sf' => now()->subDay(),
            'synced_at' => now()->subHour(),
            'resolution_minutes' => null,
            'zwing_resolution_minutes' => null,
            'activity_summary' => null,
            'activity_summarized_at' => null,
        ];
    }
}
