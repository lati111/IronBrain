<?php

namespace Database\Factories\Modules\PKSanc;

use App\Models\Auth\User;
use App\Models\PKSanc\ContestStats;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Stats;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatblockFactory extends Factory
{
    /** { @inheritdoc } */
    protected $model = Stats::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Stats $stats) {
            $stats->save();
        });
    }

    /** { @inheritdoc } */
    public function definition(): array
    {
        $pokemon = StoredPokemon::inRandomOrder()->first();

        return [
            'pokemon_uuid' => $pokemon->uuid,
            'hp_iv' => fake()->numberBetween(0, 255),
            'hp_ev' => fake()->numberBetween(0, 255),
            'atk_iv' => fake()->numberBetween(0, 255),
            'atk_ev' => fake()->numberBetween(0, 255),
            'def_iv' => fake()->numberBetween(0, 255),
            'def_ev' => fake()->numberBetween(0, 255),
            'spa_iv' => fake()->numberBetween(0, 255),
            'spa_ev' => fake()->numberBetween(0, 255),
            'spd_iv' => fake()->numberBetween(0, 255),
            'spd_ev' => fake()->numberBetween(0, 255),
            'spe_iv' => fake()->numberBetween(0, 255),
            'spe_ev' => fake()->numberBetween(0, 255),
        ];
    }
}
