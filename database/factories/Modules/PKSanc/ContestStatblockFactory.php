<?php

namespace Database\Factories\Modules\PKSanc;

use App\Models\Auth\User;
use App\Models\PKSanc\ContestStats;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Stats;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContestStatblockFactory extends Factory
{
    /** { @inheritdoc } */
    protected $model = ContestStats::class;

    public function configure(): static
    {
        return $this->afterMaking(function (ContestStats $stats) {
            $stats->save();
        });
    }

    /** { @inheritdoc } */
    public function definition(): array
    {
        $pokemon = $this->pokemon ?? StoredPokemon::inRandomOrder()->first();

        return [
            'pokemon_uuid' => $pokemon->uuid,
            'beauty' => fake()->numberBetween(0, 255),
            'cool' => fake()->numberBetween(0, 255),
            'cute' => fake()->numberBetween(0, 255),
            'smart' => fake()->numberBetween(0, 255),
            'tough' => fake()->numberBetween(0, 255),
            'sheen' => fake()->numberBetween(0, 255),
        ];
    }
}
