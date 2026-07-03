<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationThirdPartyApi;
use App\Models\ThirdPartyApi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationThirdPartyApi>
 */
class OrganizationThirdPartyApiFactory extends Factory
{
    protected $model = OrganizationThirdPartyApi::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'third_party_api_id' => ThirdPartyApi::factory(),
            'base_url' => 'https://api.example.com',
            'auth_token' => fake()->uuid(),
            'is_active' => true,
        ];
    }
}
