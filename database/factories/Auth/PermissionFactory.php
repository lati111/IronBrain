<?php

namespace Database\Factories\Auth;

use App\Models\Auth\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Auth\Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        return [
            'permission' => fake()->unique()->regexify('[A-Za-z0-9]{48}'),
            'name' => fake()->regexify('[A-Za-z0-9]{26}'),
            'description' => fake()->regexify('[A-Za-z0-9]{48}'),
            'group' => fake()->regexify('[A-Za-z0-9]{12}'),
        ];
    }
}
