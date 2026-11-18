<?php

namespace App\Console\Commands\Modules\PKSanc;

use App\Console\Commands\Traits\PromptsForMissingImages;
use App\Console\Commands\Traits\RunsImports;
use App\Service\PKSanc\ImportService;
use App\Service\PKSanc\PokeApiService;
use Illuminate\Console\Command;

class ImportData extends Command
{
    use PromptsForMissingImages, RunsImports;

    private readonly PokeApiService $api;
    private ImportService $importer;

    public function __construct(PokeApiService $api)
    {
        $this->api = $api;

        parent::__construct();
    }

    /** {@inheritdoc} */
    protected $signature = 'import:pksanc {--filter=} {--database=} {--refresh-images : Download all sprites again, even if they already exist}';

    /** {@inheritdoc} */
    protected $description = 'Imports PKSanc data from PokeApi';

    public function handle(ImportService $importer): int
    {
        // The importer keeps track of what it imported, so a fresh one is used for every run
        $this->importer = $importer;

        if (($connection = $this->option('database')) !== null) {
            $this->importer->setConnection($connection);
        }

        $this->importer->onMissingImage($this->missingImagePrompt());
        $this->importer->setRefreshImages((bool) $this->option('refresh-images'));

        $this->line('Importing PKSanc data...');
        $this->newLine();

        $imports = [
            'type' => fn () => $this->importTypes(),
            'nature' => fn () => $this->importNatures(),
            'move' => fn () => $this->importMoves(),
            'ability' => fn () => $this->importAbilities(),
            'pokeball' => fn () => $this->importPokeballs(),
            'pokemon' => fn () => $this->importPokemon(),
            'game' => fn () => $this->importGames(),
        ];

        $aliases = ['types' => 'type', 'natures' => 'nature', 'moves' => 'move', 'abilities' => 'ability', 'pokeballs' => 'pokeball', 'games' => 'game'];
        $filter = strtolower($this->option('filter') ?? '');
        $filter = $aliases[$filter] ?? $filter;
        if ($filter !== '' && !isset($imports[$filter])) {
            $this->error(sprintf('Unknown filter "%s". Valid filters are: %s', $filter, implode(', ', array_keys($imports))));
            return self::INVALID;
        }

        foreach ($imports as $name => $import) {
            if ($filter === '' || $filter === $name) {
                $import();
            }
        }

        return $this->finishImport('PKSanc');
    }

    /**
     * Gets a list of all types from PokeAPI and imports them to the database
     */
    private function importTypes(): void
    {
        $this->runImport('types',
            fn () => $this->api->getTypes(),
            fn (array $data) => $this->importer->importType($data),
        );
    }

    /**
     * Gets a list of all natures from PokeAPI and imports them to the database
     */
    private function importNatures(): void
    {
        $this->runImport('natures',
            fn () => $this->api->getNatures(),
            fn (array $data) => $this->importer->importNature($data),
        );
    }

    /**
     * Gets a list of all moves from PokeAPI and imports them to the database
     */
    private function importMoves(): void
    {
        $this->runImport('moves',
            fn () => $this->api->getMoves(),
            fn (array $data) => $this->importer->importMove($data),
        );
    }

    /**
     * Gets a list of all abilities from PokeAPI and imports them to the database
     */
    private function importAbilities(): void
    {
        $this->runImport('abilities',
            fn () => $this->api->getAbilities(),
            fn (array $data) => $this->importer->importAbility($data),
        );
    }

    /**
     * Gets a list of all pokeballs from PokeAPI and imports them to the database
     */
    private function importPokeballs(): void
    {
        $this->runImport('pokeballs',
            function () {
                $pokeballs = $this->api->getPokeballs();
                $this->importer->prefetchPokeballSprites($pokeballs, $this->downloadProgress('pokeball sprites'));
                return $pokeballs;
            },
            fn (array $data) => $this->importer->importPokeball($data),
            describe: fn (array $data) => $data['pokeball'],
        );
    }

    /**
     * Gets a list of all pokemon from PokeAPI and imports them to the database
     */
    private function importPokemon(): void
    {
        $this->runImport('pokemon',
            function () {
                $this->line('Fetching pokemon from PokeAPI...');
                $pokemon = $this->api->getPokemon();
                $this->importer->prefetchPokemonSprites($pokemon, $this->downloadProgress('pokemon sprites'));
                return $pokemon;
            },
            fn (array $data) => $this->importer->importPokemon($data),
            fn () => $this->importer->prunePokemon(),
            fn (array $data) => $data['pokemon'],
        );
    }

    /**
     * Gets a list of all games from the config JSON and imports them to the database
     */
    private function importGames(): void
    {
        $this->runImport('games',
            fn () => $this->importer->getGamesJson(),
            fn (string $code, string $name) => $this->importer->importGame($name, $code),
        );
    }
}
