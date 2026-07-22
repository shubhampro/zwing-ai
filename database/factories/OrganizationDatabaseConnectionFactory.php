<?php

namespace Database\Factories;

use App\Enums\DatabaseConnectionType;
use App\Models\Organization;
use App\Models\OrganizationDatabaseConnection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationDatabaseConnection>
 */
class OrganizationDatabaseConnectionFactory extends Factory
{
    protected $model = OrganizationDatabaseConnection::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'type' => DatabaseConnectionType::Pgsql,
            'database_name' => 'org_'.fake()->unique()->lexify('??????'),
            'username' => fake()->userName(),
            'password' => fake()->password(),
            'host' => 'db.example.com',
            'port' => 5432,
            'is_active' => true,
        ];
    }

    public function mysql(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DatabaseConnectionType::Mysql,
            'port' => 3306,
        ]);
    }

    public function pgsql(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DatabaseConnectionType::Pgsql,
            'port' => 5432,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
