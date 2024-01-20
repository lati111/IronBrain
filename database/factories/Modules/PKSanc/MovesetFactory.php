<?php

namespace Database\Factories\Modules\PKSanc;

use App\Models\Auth\User;
use App\Models\PKSanc\ContestStats;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Move;
use App\Models\PKSanc\Moveset;
use App\Models\PKSanc\Origin;
use App\Models\PKSanc\Stats;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Database\Eloquent\Factories\Factory;

class MovesetFactory extends Factory
{
    /** { @inheritdoc } */
    protected $model = Moveset::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Moveset $moveset) {
            $moveset->save();
        });
    }

    /** { @inheritdoc } */
    public function definition(): array
    {
        $pokemon = $this->pokemon ?? StoredPokemon::inRandomOrder()->first();
        $move1 = Move::inRandomOrder()->first();
        $move2 = Move::inRandomOrder()->first();
        $move3 = Move::inRandomOrder()->first();
        $move4 = Move::inRandomOrder()->first();

        return [
            'pokemon_uuid' => $pokemon->uuid,
            'move1' => $move1->move,
            'move1_pp_up' => fake()->numberBetween(0, 3),
            'move2' => $move2->move,
            'move2_pp_up' => fake()->numberBetween(0, 3),
            'move3' => $move3->move,
            'move3_pp_up' => fake()->numberBetween(0, 3),
            'move4' => $move4->move,
            'move4_pp_up' => fake()->numberBetween(0, 3),
        ];
    }
}
