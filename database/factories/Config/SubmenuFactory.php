<?php

namespace Database\Factories\Config;

use App\Models\Config\Project;
use App\Models\Config\Submenu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Config\Project>
 */
class SubmenuFactory extends Factory
{
    protected $model = Submenu::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'route' => 'home.show',
            'name' => fake()->regexify('[A-Za-z0-9]{26}'),
            'order' => fake()->unique()->randomNumber(),
        ];
    }
}
