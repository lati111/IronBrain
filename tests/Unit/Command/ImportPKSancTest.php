<?php

namespace Tests\Unit\Command;

use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\Type;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Unit\AbstractUnitTester;

class ImportPKSancTest extends AbstractUnitTester
{
    private const string spriteUrl = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/';

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
    }

    /**
     * Fakes PokeAPI returning a single pokemon, and only lets the given sprite urls return an image
     * @param string $defaultSprite The default sprite url PokeAPI returns for the pokemon
     * @param string[] $validSprites The sprite urls that return an image
     */
    private function fakePokeApi(string $defaultSprite, array $validSprites): void
    {
        $sprites = [];
        foreach ($validSprites as $url) {
            $sprites[$url] = Http::response($this->png);
        }

        Http::fake([
            'beta.pokeapi.co/*' => Http::response(['data' => ['pokemon' => [[
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
                    'front_default' => $defaultSprite,
                    'front_shiny' => null,
                    'front_female' => null,
                    'front_shiny_female' => null,
                ]]],
            ]]]]),
            ...$sprites,
            '*' => Http::response('Not Found', 404),
        ]);
    }

    public function testImportShowsSpriteDownloadProgress(): void
    {
        $this->fakePokeApi(self::spriteUrl . '99001.png', [self::spriteUrl . '99001.png', self::spriteUrl . 'shiny/99001.png']);

        $this->artisan('import:pksanc', ['--filter' => 'pokemon'])
            ->expectsOutputToContain('Downloading 2 pokemon sprites...')
            ->assertExitCode(0);

        $pokemon = Pokemon::where('pokemon', 'importtestmon')->first();
        $this->assertEquals('importtestmon_default.png', $pokemon->sprite);
        $this->assertEquals('importtestmon_shiny.png', $pokemon->sprite_shiny);
    }

    public function testImportSkipsDownloadedSprites(): void
    {
        $this->fakePokeApi(self::spriteUrl . '99001.png', [self::spriteUrl . '99001.png', self::spriteUrl . 'shiny/99001.png']);
        Storage::put('modules/pksanc/pokemon/importtestmon_default.png', $this->png);
        Storage::put('modules/pksanc/pokemon/importtestmon_shiny.png', $this->png);

        $this->artisan('import:pksanc', ['--filter' => 'pokemon'])
            ->expectsOutputToContain('All pokemon sprites have already been downloaded.')
            ->assertExitCode(0);
    }

    public function testImportPromptsForMissingSprite(): void
    {
        $replacement = self::spriteUrl . 'replacement.png';
        $this->fakePokeApi(self::spriteUrl . '99001.png', [$replacement, self::spriteUrl . 'shiny/replacement.png']);

        $this->artisan('import:pksanc', ['--filter' => 'pokemon'])
            ->expectsQuestion('Enter the url to use instead, or leave empty to skip', $replacement)
            ->assertExitCode(0);

        $this->assertEquals('importtestmon_default.png', Pokemon::where('pokemon', 'importtestmon')->value('sprite'));
    }
}
