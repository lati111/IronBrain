<?php

namespace Database\Factories\Config;

use App\Models\PKSanc\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Config\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'game' => 'home.show',
            'name' => fake()->regexify('[A-Za-z0-9]{26}'),
        ];
    }
}
