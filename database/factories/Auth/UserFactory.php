<?php

namespace Database\Factories\Auth;

use App\Models\Auth\Role;
use App\Models\Auth\User;
use Database\Factories\AbstractFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends AbstractFactory
{
    public function definition(): array
    {
        $role = Role::inRandomOrder()->where('is_admin', '=', false)->first();

        return [
            'username' => fake()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make(fake()->password()),
            'role_id' => ($role !== null) ? $role->id : null
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
