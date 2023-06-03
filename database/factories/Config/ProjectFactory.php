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
        $order = null;
        while ($order === null) {
            $int = $this->faker->randomNumber();
            if (Project::where('order', $int)->count() === 0) {
                $order = $int;
            }
        }

        return [
            'route' => 'home.show',
            'name' => $this->faker->regexify('[A-Za-z0-9]{26}'),
            'description' => $this->faker->regexify('[A-Za-z0-9]{48}'),
            'thumbnail' => 'test.png',
            'order' => $order,
            'in_overview' => true,
            'in_nav' => true,
        ];
    }
}
