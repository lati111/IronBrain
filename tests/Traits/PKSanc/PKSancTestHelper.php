<?php

namespace Tests\Traits\PKSanc;

use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use Illuminate\Support\Facades\DB;
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

    /**
     * Ensures the reference data used by the pokemon in the given list exists, so imports validate on a freshly migrated database.
     * Existing rows are left untouched.
     * @param array $pokemon Species and form index pairs, e.g. [['bramblin', 0]]
     * @param array $references Reference values per type, using the keys abilities, natures, pokeballs, types, moves and games
     */
    protected function createTestReferenceData(array $pokemon, array $references): void
    {
        $now = now();

        // the filler type is used for the types of the pokemon and moves, which reference the type table
        $references['types'] = array_merge(['normal'], $references['types'] ?? []);

        $rows = [
            'pksanc__type' => fn ($value) => ['type' => $value, 'name' => Str::headline($value)],
            'pksanc__ability' => fn ($value) => ['ability' => $value, 'name' => Str::headline($value), 'description' => ''],
            'pksanc__nature' => fn ($value) => ['nature' => $value, 'name' => Str::headline($value)],
            'pksanc__pokeball' => fn ($value) => ['pokeball' => $value, 'name' => Str::headline($value), 'sprite' => $value.'.png'],
            'pksanc__move' => fn ($value) => ['move' => $value, 'name' => Str::headline($value), 'description' => '', 'type' => 'normal', 'move_type' => 'physical'],
            'pksanc__game' => fn ($value) => ['game' => $value, 'name' => Str::headline($value), 'is_romhack' => false],
        ];

        // types go first, as the moves reference them
        $keys = ['types' => 'pksanc__type', 'abilities' => 'pksanc__ability', 'natures' => 'pksanc__nature',
            'pokeballs' => 'pksanc__pokeball', 'moves' => 'pksanc__move', 'games' => 'pksanc__game'];

        foreach ($keys as $key => $table) {
            foreach ($references[$key] ?? [] as $value) {
                DB::table($table)->insertOrIgnore($rows[$table]($value) + ['created_at' => $now, 'updated_at' => $now]);
            }
        }

        foreach ($pokemon as $i => [$species, $formIndex]) {
            DB::table('pksanc__pokemon')->insertOrIgnore([
                'species' => $species, 'species_name' => Str::headline($species), 'pokemon' => $species,
                'form_index' => $formIndex, 'pokedex_id' => 9000 + $i, 'internal_pokedex_id' => 9000 + $i,
                'primary_type' => 'normal', 'secondary_type' => 'normal', 'generation' => 1, 'pokemon_type' => 'normal',
                'base_hp' => 1, 'base_atk' => 1, 'base_def' => 1, 'base_spa' => 1, 'base_spd' => 1, 'base_spe' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
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
}
