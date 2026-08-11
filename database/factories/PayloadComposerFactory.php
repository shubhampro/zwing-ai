<?php

namespace Database\Factories;

use App\Models\PayloadComposer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayloadComposer>
 */
class PayloadComposerFactory extends Factory
{
    protected $model = PayloadComposer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'scalars' => [
                [
                    'key' => 'referenceNo',
                    'required' => true,
                    'default' => null,
                ],
                [
                    'key' => 'storeId',
                    'required' => true,
                    'default' => null,
                ],
            ],
        ];
    }
}
