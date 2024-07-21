<?php

namespace Database\Factories\Config;

use App\Models\Config\Module;
use App\Models\Config\Submodule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Config\Module>
 */
class SubmenuFactory extends Factory
{
    protected $model = Submodule::class;

    public function definition(): array
    {
        return [
            'project_id' => Module::factory(),
            'route' => 'home.show',
            'name' => fake()->regexify('[A-Za-z0-9]{26}'),
            'order' => fake()->unique()->randomNumber(),
        ];
    }
}
