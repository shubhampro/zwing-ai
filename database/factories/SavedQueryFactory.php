<?php

namespace Database\Factories;

use App\Models\SavedQuery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedQuery>
 */
class SavedQueryFactory extends Factory
{
    protected $model = SavedQuery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'sql' => 'SELECT 1 AS n',
            'bindings' => ['status' => 'active'],
        ];
    }
}
