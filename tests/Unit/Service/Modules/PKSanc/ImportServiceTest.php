<?php

namespace Tests\Unit\Service\Modules\PKSanc;

use App\Models\PKSanc\Ability;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\Move;
use App\Models\PKSanc\Nature;
use App\Models\PKSanc\Pokeball;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Type;
use App\Service\PKSanc\ImportService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Unit\Service\AbstractServiceTester;

class ImportServiceTest extends AbstractServiceTester
{
    private const string spriteUrl = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/';

    private ImportService $importer;
    private string $png;

    public function setUp(): void
    {
        parent::setUp();
        Storage::fake();

        // 1x1 transparent png
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        if (!Type::where('type', 'grass')->exists()) {
            $type = new Type();
            $type->type = 'grass';
            $type->name = 'Grass';
            $type->save();
        }

        $this->importer = new ImportService();
    }

    /**
     * Only lets the given urls return a valid image
     * @param string[] $validUrls The urls that return an image
     */
    private function fakeSprites(array $validUrls): void
    {
        $responses = [];
        foreach ($validUrls as $url) {
            $responses[$url] = Http::response($this->png);
        }

        Http::fake([...$responses, '*' => Http::response('Not Found', 404)]);
    }

    private function pokemonData(array $overrides = []): array
    {
        return array_replace_recursive([
            'pokemon' => 'import-testmon',
            'form_index' => 1,
            'form_name' => '',
            'pokedex_id' => 99001,
            'details' => [
                'species_id' => 99001,
                'species' => ['species' => 'import-testmon', 'name' => [['name' => 'Testmon']]],
                'types' => [['type' => ['type' => 'grass']]],
                'stats' => array_map(fn (int $value) => ['value' => $value], [1, 2, 3, 4, 5, 6]),
            ],
            'generation' => [['generation' => 1]],
            'generation_backup' => ['generation' => 1],
            'sprites' => [['sprites' => [
                'front_default' => null,
                'front_shiny' => null,
                'front_female' => null,
                'front_shiny_female' => null,
            ]]],
        ], $overrides);
    }

    private function moveData(array $overrides = []): array
    {
        return array_replace([
            'move' => 'import-test-move',
            'name' => [['name' => 'Import Test Move']],
            'power' => 40,
            'accuracy' => 100,
            'priority' => 0,
            'type' => ['type' => 'grass'],
            'move_type' => ['move_type' => 'physical'],
            'description' => [['description' => 'A move used for testing.']],
        ], $overrides);
    }

    //| moves

    public function testImportMoveSavesDescription(): void
    {
        $this->assertTrue($this->importer->importMove($this->moveData()));
        $this->assertEquals('A move used for testing.', Move::where('move', 'importtestmove')->value('description'));
    }

    public function testImportMoveWithoutDescription(): void
    {
        $this->importer->importMove($this->moveData(['description' => []]));
        $this->assertEquals('', Move::where('move', 'importtestmove')->value('description'));
    }

    public function testImportMoveUnchanged(): void
    {
        $this->importer->importMove($this->moveData());
        $this->assertFalse((new ImportService())->importMove($this->moveData()));
    }

    //| pokemon sprites

    public function testPokemonSpriteVariantsUseDefaultFileName(): void
    {
        // PokeAPI points to a shiny sprite that does not exist, while the repository stores it under the pokemon id
        $this->fakeSprites([self::spriteUrl . '99002.png', self::spriteUrl . 'shiny/99002.png']);

        $this->importer->importPokemon($this->pokemonData([
            'form_name' => 'cap',
            'pokedex_id' => 99002,
            'sprites' => [['sprites' => [
                'front_default' => self::spriteUrl . '99002.png',
                'front_shiny' => self::spriteUrl . 'shiny/99001-cap.png',
            ]]],
        ]));

        $pokemon = Pokemon::where('pokemon', 'importtestmon')->first();
        $this->assertEquals('importtestmon_default.png', $pokemon->sprite);
        $this->assertEquals('importtestmon_shiny.png', $pokemon->sprite_shiny);
        $this->assertNull($pokemon->sprite_female);
        Storage::assertExists('modules/pksanc/pokemon/importtestmon_shiny.png');
    }

    public function testPokemonSpriteFallsBackToPokemonId(): void
    {
        $this->fakeSprites([self::spriteUrl . '99002.png', self::spriteUrl . 'shiny/99002.png']);
        $this->importer->onMissingImage(fn () => $this->fail('Should not prompt when a fallback sprite exists'));

        $this->importer->importPokemon($this->pokemonData(['form_name' => 'cap', 'pokedex_id' => 99002]));

        $this->assertEquals('importtestmon_default.png', Pokemon::where('pokemon', 'importtestmon')->value('sprite'));
    }

    public function testPokemonWithoutSpriteDoesNotPrompt(): void
    {
        $this->fakeSprites([]);
        $this->importer->onMissingImage(fn () => $this->fail('Should not prompt when PokeAPI has no sprite'));

        $this->importer->importPokemon($this->pokemonData(['form_name' => 'cap', 'pokedex_id' => 99002]));

        $pokemon = Pokemon::where('pokemon', 'importtestmon')->first();
        $this->assertNull($pokemon->sprite);
        $this->assertNull($pokemon->sprite_shiny);
    }

    public function testPokemonPromptsWhenApiSpriteIsMissing(): void
    {
        $this->fakeSprites([self::spriteUrl . 'replacement.png']);
        $this->importer->onMissingImage(fn () => self::spriteUrl . 'replacement.png');

        $this->importer->importPokemon($this->pokemonData([
            'sprites' => [['sprites' => ['front_default' => self::spriteUrl . '99001.png']]],
        ]));

        $this->assertEquals('importtestmon_default.png', Pokemon::where('pokemon', 'importtestmon')->value('sprite'));
    }

    public function testPrefetchDownloadsSpritesBeforeImport(): void
    {
        $this->fakeSprites([self::spriteUrl . '99001.png', self::spriteUrl . 'shiny/99001.png']);
        $data = $this->pokemonData(['sprites' => [['sprites' => ['front_default' => self::spriteUrl . '99001.png']]]]);

        $this->importer->prefetchPokemonSprites([$data]);

        Storage::assertExists('modules/pksanc/pokemon/importtestmon_default.png');
        Storage::assertExists('modules/pksanc/pokemon/importtestmon_shiny.png');
    }

    public function testRefreshImagesReplacesExistingSprite(): void
    {
        $this->fakeSprites([self::spriteUrl . '99001.png']);
        Storage::put('modules/pksanc/pokemon/importtestmon_default.png', 'outdated');

        $this->importer->setRefreshImages(true);
        $this->importer->importPokemon($this->pokemonData([
            'sprites' => [['sprites' => ['front_default' => self::spriteUrl . '99001.png']]],
        ]));

        $this->assertEquals($this->png, Storage::get('modules/pksanc/pokemon/importtestmon_default.png'));
    }

    //| pruning

    public function testPruneRemovesPokemonThatWereNotImported(): void
    {
        $this->fakeSprites([]);
        $this->importer->importPokemon($this->pokemonData(['pokemon' => 'import-testmon-old']));

        $importer = new ImportService();
        $importer->importPokemon($this->pokemonData());
        $importer->prunePokemon();

        $this->assertFalse(Pokemon::where('pokemon', 'importtestmonold')->exists());
        $this->assertTrue(Pokemon::where('pokemon', 'importtestmon')->exists());
    }

    public function testPruneKeepsStoredPokemon(): void
    {
        if (!Nature::exists() || !Ability::exists() || !Pokeball::exists()) {
            $this->markTestSkipped('The test database has no PKSanc data to create a stored pokemon with');
        }

        $this->fakeSprites([]);
        $this->importer->importPokemon($this->pokemonData(['pokemon' => 'import-testmon-stored']));
        StoredPokemon::factory()->make(['pokemon' => 'importtestmonstored']);

        $importer = new ImportService();
        $importer->importPokemon($this->pokemonData());
        $importer->prunePokemon();

        $this->assertTrue(Pokemon::where('pokemon', 'importtestmonstored')->exists());
    }

    public function testPruneWithoutImportDeletesNothing(): void
    {
        $this->fakeSprites([]);
        $this->importer->importPokemon($this->pokemonData());

        $this->assertEquals(0, (new ImportService())->prunePokemon());
        $this->assertTrue(Pokemon::where('pokemon', 'importtestmon')->exists());
    }

    //| games

    public function testImportGameUpdatesName(): void
    {
        $this->importer->importGame('Old Name', 'IMPORTTEST');
        $this->assertTrue((new ImportService())->importGame('New Name', 'IMPORTTEST'));

        $this->assertEquals('New Name', Game::where('game', 'importtest')->value('name'));
    }
}
