<?php

namespace Database\Factories\Modules\PKSanc;

use App\Models\Auth\User;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use Illuminate\Database\Eloquent\Factories\Factory;

class ImportCsvFactory extends Factory
{
    /** { @inheritdoc } */
    protected $model = ImportCsv::class;

    public function configure(): static
    {
        return $this->afterMaking(function (ImportCsv $csv) {
            $csv->save();
        });
    }

    /** { @inheritdoc } */
    public function definition(): array
    {
        $user = User::inRandomOrder()->first();
        $game = Game::inRandomOrder()->first();

        return [
            'csv' => fake()->regexify('[a-zA-Z0-9]{8}'),
            'game' => $game->game,
            'name' => fake()->regexify('[a-zA-Z0-9]{16}'),
            'version' => 1,
            'validated' => '1',
            'uploader_uuid' => $user->uuid,
        ];
    }
}
