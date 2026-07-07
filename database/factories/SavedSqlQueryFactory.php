<?php

namespace Database\Factories;

use App\Models\SavedSqlQuery;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SavedSqlQuery>
 */
class SavedSqlQueryFactory extends Factory
{
    protected $model = SavedSqlQuery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'sql' => 'SELECT * FROM grn WHERE id = 1;',
        ];
    }
}
