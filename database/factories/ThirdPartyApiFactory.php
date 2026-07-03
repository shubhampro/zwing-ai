<?php

namespace Database\Factories;

use App\HttpMethod;
use App\Models\ThirdPartyApi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThirdPartyApi>
 */
class ThirdPartyApiFactory extends Factory
{
    protected $model = ThirdPartyApi::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' API',
            'path' => '/api/v1/'.fake()->slug(),
            'method' => HttpMethod::Post,
            'params' => [
                ['key' => 'id', 'csv_column' => 'id', 'required' => true],
            ],
            'auth_header_name' => 'Authorization',
            'is_active' => true,
        ];
    }
}
