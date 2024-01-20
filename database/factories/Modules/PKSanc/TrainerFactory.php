<?php

namespace Database\Factories\Modules\PKSanc;

use App\Models\Auth\User;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Moveset;
use App\Models\PKSanc\Stats;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Trainer;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrainerFactory extends Factory
{
    /** { @inheritdoc } */
    protected $model = Trainer::class;

    public function configure(): static
    {
        return $this->afterMaking(function (Trainer $trainer) {
            $trainer->save();
        });
    }

    /** { @inheritdoc } */
    public function definition(): array
    {
        $user = User::inRandomOrder()->first();
        $game = Game::inRandomOrder()->first();

        return [
            'trainer_id' => fake()->regexify('[0-9]{5}'),
            'secret_id' => fake()->regexify('[0-9]{5}'),
            'name' => fake()->firstName,
            'gender' => fake()->randomElement(['M', 'F']),
            'game' => $game->game,
            'owner_uuid' => $user->uuid,
        ];
    }
}
