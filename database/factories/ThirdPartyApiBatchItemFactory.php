<?php

namespace Database\Factories;

use App\Models\ThirdPartyApiBatch;
use App\Models\ThirdPartyApiBatchItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ThirdPartyApiBatchItem>
 */
class ThirdPartyApiBatchItemFactory extends Factory
{
    protected $model = ThirdPartyApiBatchItem::class;

    public function definition(): array
    {
        return [
            'third_party_api_batch_id' => ThirdPartyApiBatch::factory(),
            'payload' => [
                'id' => fake()->numerify('######'),
                'code' => fake()->numerify('###'),
            ],
            'status' => 'pending',
        ];
    }
}
