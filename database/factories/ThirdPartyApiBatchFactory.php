<?php

namespace Database\Factories;

use App\Models\OrganizationThirdPartyApi;
use App\Models\ThirdPartyApiBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThirdPartyApiBatch>
 */
class ThirdPartyApiBatchFactory extends Factory
{
    protected $model = ThirdPartyApiBatch::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_third_party_api_id' => OrganizationThirdPartyApi::factory(),
            'name' => fake()->unique()->slug(3),
            'file_name' => 'batch.csv',
            'row_count' => 1,
            'processed_count' => 0,
            'success_count' => 0,
            'failed_count' => 0,
            'skipped_count' => 0,
            'defaults' => [],
            'status' => 'pending',
        ];
    }
}
