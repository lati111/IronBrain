<?php

namespace Tests\Traits\PKSanc;

use App\Enum\PKSanc\PokemonTypes;
use App\Models\PKSanc\Ability;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Move;
use App\Models\PKSanc\Nature;
use App\Models\PKSanc\Pokeball;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\Type;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait PKSancTestHelper
{
    protected function createTestGame(?string $gameCode = null): Game
    {
        $code = $gameCode ?? 'test_' . Str::random(6);

        $existing = Game::where('game', $code)->first();
        if ($existing !== null) {
            return $existing;
        }

        $game = new Game();
        $game->game = $code;
        $game->name = 'Test Game';
        $game->is_romhack = false;
        $game->save();
        return $game;
    }

    protected function createTestType(string $code): Type
    {
        return $this->createReference(Type::class, ['type' => $code], ['name' => ucfirst($code)]);
    }

    protected function createTestNature(string $code): Nature
    {
        return $this->createReference(Nature::class, ['nature' => $code], ['name' => ucfirst($code)]);
    }

    protected function createTestAbility(string $code): Ability
    {
        return $this->createReference(Ability::class, ['ability' => $code], ['name' => ucfirst($code), 'description' => '']);
    }

    protected function createTestPokeball(string $code): Pokeball
    {
        return $this->createReference(Pokeball::class, ['pokeball' => $code], ['name' => ucfirst($code), 'sprite' => $code . '.png']);
    }

    protected function createTestMove(string $code, string $type = 'normal'): Move
    {
        $this->createTestType($type);
        return $this->createReference(Move::class, ['move' => $code], [
            'name'        => ucfirst($code),
            'description' => '',
            'type'        => $type,
            'move_type'   => 'physical',
        ]);
    }

    protected function createTestPokemon(string $code, string $type = 'normal', int $formIndex = 0): Pokemon
    {
        $this->createTestType($type);
        return $this->createReference(Pokemon::class, ['pokemon' => $code], [
            'species'        => $code,
            'species_name'   => ucfirst($code),
            'form_index'     => $formIndex,
            'pokedex_id'     => 1,
            'primary_type'   => $type,
            'secondary_type' => $type,
            'base_hp'        => 50,
            'base_atk'       => 50,
            'base_def'       => 50,
            'base_spa'       => 50,
            'base_spd'       => 50,
            'base_spe'       => 50,
            'generation'     => 1,
            'pokemon_type'   => PokemonTypes::FORM,
        ]);
    }

    protected function createTestImportCsv(string $ownerUuid, string $gameCode): ImportCsv
    {
        $csv = new ImportCsv();
        $csv->csv = 'test.csv';
        $csv->game = $gameCode;
        $csv->name = 'Test Import';
        $csv->version = 1.2;
        $csv->validated = 0;
        $csv->uploader_uuid = $ownerUuid;
        $csv->save();
        return $csv;
    }

    /**
     * Creates a reference model if it does not exist yet
     * @param class-string<Model> $model The model class
     * @param array $key The primary key column and value
     * @param array $attributes The other attributes to fill
     * @return Model
     */
    private function createReference(string $model, array $key, array $attributes): Model
    {
        $existing = $model::where($key)->first();
        if ($existing !== null) {
            return $existing;
        }

        $entity = new $model();
        $entity->forceFill(array_merge($key, $attributes));
        $entity->save();
        return $entity;
    }
}
