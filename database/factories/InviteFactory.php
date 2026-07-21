<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Invite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invite>
 */
class InviteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'token' => Invite::generateToken(),
            'email' => null,
            'role' => Role::Operator->value,
            'invited_by' => null,
            'used_by' => null,
            'used_at' => null,
            'expires_at' => null,
        ];
    }

    public function role(Role|string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => $role instanceof Role ? $role->value : $role,
        ]);
    }

    public function forEmail(string $email): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => $email,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subHour(),
        ]);
    }

    public function used(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'used_at' => now(),
            'used_by' => $user?->id,
        ]);
    }
}
