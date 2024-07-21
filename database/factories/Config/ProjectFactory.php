<?php

namespace Database\Factories\Config;

use App\Models\Config\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Config\Module>
 */
class ProjectFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        return [
            'route' => 'home.show',
            'name' => fake()->regexify('[A-Za-z0-9]{26}'),
            'description' => fake()->regexify('[A-Za-z0-9]{48}'),
            'thumbnail' => 'test.png',
            'order' => fake()->unique()->randomNumber(),
            'in_overview' => true,
            'in_nav' => true,
        ];
    }
}
