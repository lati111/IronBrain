<?php

namespace Database\Factories\Auth;

use App\Models\Auth\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->regexify('[A-Za-z0-9]{26}'),
            'description' => $this->faker->regexify('[A-Za-z0-9]{48}'),
        ];
    }
}
