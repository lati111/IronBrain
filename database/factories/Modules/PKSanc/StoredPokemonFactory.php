<?php

namespace Database\Factories\Modules\PKSanc;

use App\Models\Auth\User;
use App\Models\PKSanc\Ability;
use App\Models\PKSanc\ContestStats;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Moveset;
use App\Models\PKSanc\Nature;
use App\Models\PKSanc\Origin;
use App\Models\PKSanc\Pokeball;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\Stats;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Type;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoredPokemonFactory extends Factory
{
    /** { @inheritdoc } */
    protected $model = StoredPokemon::class;

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (StoredPokemon $pokemon) {
            $pokemon->save();
        });
    }

    /** { @inheritdoc } */
    public function definition(): array
    {
        $user = User::inRandomOrder()->first();
        $pokemon = Pokemon::inRandomOrder()->first();
        $nature = Nature::inRandomOrder()->first();
        $ability = Ability::inRandomOrder()->first();
        $pokeball = Pokeball::inRandomOrder()->first();
        $hiddenPower = Type::inRandomOrder()->first();
        $teraType = Type::inRandomOrder()->first();

        /** @var ImportCsvFactory $importCsvFactory */
        $importCsvFactory = ImportCsv::factory();
        $importCsv = $importCsvFactory->make();

        return [
            'PID' => fake()->regexify('[A-Z0-9]{8}'),
            'nickname' => fake()->firstName(),
            'pokemon' => $pokemon->pokemon,
            'gender' => fake()->randomElement(['M', 'F', '-']),
            'nature' => $nature->nature,
            'ability' => $ability->ability,
            'pokeball' => $pokeball->pokeball,
            'hidden_power_type' => $hiddenPower->type,
            'tera_type' => $teraType->type,
            'friendship' => fake()->numberBetween(0, 255),
            'level' => fake()->numberBetween(0, 100),
            'height' => fake()->numberBetween(10, 500),
            'weight' => fake()->numberBetween(10, 500),
            'is_shiny' => fake()->boolean(),
            'is_alpha' => fake()->boolean(),
            'has_n_sparkle' => fake()->boolean(),
            'can_gigantamax' => fake()->boolean(),
            'dynamax_level' => fake()->numberBetween(0, 10),
            'csv_uuid' => $importCsv->uuid,
            'csv_line' => fake()->numberBetween(0, 10),
            'owner_uuid' => $user->uuid,
            'validated_at' => Carbon::now(),
        ];
    }
}
