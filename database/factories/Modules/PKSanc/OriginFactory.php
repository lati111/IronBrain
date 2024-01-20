<?php

namespace Database\Factories\Modules\PKSanc;

use App\Models\Auth\User;
use App\Models\PKSanc\ContestStats;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Origin;
use App\Models\PKSanc\Stats;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Trainer;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class OriginFactory extends Factory
{
    /** { @inheritdoc } */
    protected $model = Origin::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Origin $origin) {
            $origin->save();
        });
    }

    /** { @inheritdoc } */
    public function definition(): array
    {
        $pokemon = StoredPokemon::inRandomOrder()->first();
        $trainer = Trainer::inRandomOrder()->first();

        return [
            'pokemon_uuid' => $pokemon->uuid,
            'trainer_uuid' => $trainer->uuid,
            'game' => $trainer->game,
            'met_date' => Carbon::now(),
            'met_location' => fake()->regexify('[A-Za-z ]{24}'),
            'met_level' => fake()->numberBetween(1, 100),
            'was_egg' => fake()->boolean(25)
        ];
    }
}
