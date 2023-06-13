<?php

namespace Database\Factories\Config;

use App\Models\Config\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Config\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

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
