<?php

namespace App\Console\Commands\Modules\PKSanc;

use App\Service\PKSanc\ImportService;
use App\Service\PKSanc\PokeApiService;
use Illuminate\Console\Command;

class ImportData extends Command
{
    private readonly PokeApiService $api;
    private readonly ImportService $importer;

    public function __construct(ImportService $importService, PokeApiService $api)
    {
        $this->importer = $importService;
        $this->api = $api;

        parent::__construct();
    }

    /** {@inheritdoc} */
    protected $signature = 'pksanc:import {--filter=} {--database=}';

    /** {@inheritdoc} */
    protected $description = 'Imports PKSanc data from PokeApi';

    public function handle(): void
    {
        if (($connection = $this->option('database')) !== null) {
            $this->importer->setConnection($connection);
        }

        $this->line('Importing PKSanc data...');
        $this->newLine();

        $results = [];
        $changedString = '%s imported';
        switch(strtolower($this->option('filter'))) {
            case 'type':
                $typesChangedCount = $this->importTypes();
                $results[] = ['Type', sprintf($changedString, $typesChangedCount)];
                break;
            case 'nature':
                $naturesChangedCount = $this->importNatures();
                $results[] = ['Nature', sprintf($changedString, $naturesChangedCount)];
                break;
            case 'move':
                $movesChangedCount = $this->importMoves();
                $results[] = ['Moves', sprintf($changedString, $movesChangedCount)];
                break;
            case 'ability':
                $abilitiesChangedCount = $this->importAbilities();
                $results[] = ['Abilities', sprintf($changedString, $abilitiesChangedCount)];
                break;
            case 'pokeball':
                $pokeballsChangedCount = $this->importPokeballs();
                $results[] = ['Pokeballs', sprintf($changedString, $pokeballsChangedCount)];
                break;
            case 'pokemon':
                $pokemonChangedCount = $this->importPokemon();
                $results[] = ['Pokemon', sprintf($changedString, $pokemonChangedCount)];
                break;
            case 'game':
                $gameChangedCount = $this->importGames();
                $results[] = ['Games', sprintf($changedString, $gameChangedCount)];
                break;
            default:
                $typesChangedCount = $this->importTypes();
                $results[] = ['Type', sprintf($changedString, $typesChangedCount)];

                $naturesChangedCount = $this->importNatures();
                $results[] = ['Nature', sprintf($changedString, $naturesChangedCount)];

                $movesChangedCount = $this->importMoves();
                $results[] = ['Moves', sprintf($changedString, $movesChangedCount)];

                $abilitiesChangedCount = $this->importAbilities();
                $results[] = ['Abilities', sprintf($changedString, $abilitiesChangedCount)];

                $pokeballsChangedCount = $this->importPokeballs();
                $results[] = ['Pokeballs', sprintf($changedString, $pokeballsChangedCount)];

                $pokemonChangedCount = $this->importPokemon();
                $results[] = ['Pokemon', sprintf($changedString, $pokemonChangedCount)];

                $gameChangedCount = $this->importGames();
                $results[] = ['Games', sprintf($changedString, $gameChangedCount)];
        }

        $this->info('Import successful!');
        $this->table(['', ''], $results);
    }

    /**
     * Gets a list of all types from PokeAPI and imports them to the database
     * @return int Returns a count of the amount of types changed
     */
    private function importTypes(): int
    {
        $this->line('Importing types...');

        $changedCount = 0;
        $typeCollection = $this->api->getTypes();

        $bar = $this->output->createProgressBar(count($typeCollection));
        $bar->start();

        foreach ($typeCollection as $typeData) {
            $wasChanged = $this->importer->importType($typeData);
            $bar->advance();
            if ($wasChanged) {
                $changedCount++;
            }
        }

        $bar->finish();
        $this->newLine();
        return $changedCount;
    }

    /**
     * Gets a list of all natures from PokeAPI and imports them to the database
     * @return int Returns a count of the amount of natures changed
     */
    private function importNatures(): int
    {
        $this->line('Importing natures...');

        $changedCount = 0;
        $natureCollection = $this->api->getNatures();

        $bar = $this->output->createProgressBar(count($natureCollection));
        $bar->start();

        foreach ($natureCollection as $data) {
            $wasChanged = $this->importer->importNature($data);
            $bar->advance();
            if ($wasChanged) {
                $changedCount++;
            }
        }

        $bar->finish();
        $this->newLine();
        return $changedCount;
    }

    /**
     * Gets a list of all moves from PokeAPI and imports them to the database
     * @return int Returns a count of the amount of moves changed
     */
    private function importMoves(): int
    {
        $this->line('Importing moves...');

        $changedCount = 0;
        $moveCollection = $this->api->getMoves();

        $bar = $this->output->createProgressBar(count($moveCollection));
        $bar->start();

        foreach ($moveCollection as $data) {
            $wasChanged = $this->importer->importMove($data);
            $bar->advance();
            if ($wasChanged) {
                $changedCount++;
            }
        }

        $bar->finish();
        $this->newLine();
        return $changedCount;
    }

    /**
     * Gets a list of all abilities from PokeAPI and imports them to the database
     * @return int Returns a count of the amount of abilities changed
     */
    private function importAbilities(): int
    {
        $this->line('Importing abilities...');

        $changedCount = 0;
        $abilityCollection = $this->api->getAbilities();

        $bar = $this->output->createProgressBar(count($abilityCollection));
        $bar->start();

        foreach ($abilityCollection as $data) {
            $wasChanged = $this->importer->importAbility($data);
            $bar->advance();
            if ($wasChanged) {
                $changedCount++;
            }
        }

        $bar->finish();
        $this->newLine();
        return $changedCount;
    }

    /**
     * Gets a list of all pokeballs from PokeAPI and imports them to the database
     * @return int Returns a count of the amount of pokeballs changed
     */
    private function importPokeballs(): int
    {
        $this->line('Importing pokeballs...');

        $changedCount = 0;
        $pokeballCollection = $this->api->getPokeballs();

        $bar = $this->output->createProgressBar(count($pokeballCollection));
        $bar->start();

        foreach ($pokeballCollection as $data) {
            $wasChanged = $this->importer->importPokeball($data);
            $bar->advance();
            if ($wasChanged) {
                $changedCount++;
            }
        }

        $bar->finish();
        $this->newLine();
        return $changedCount;
    }

    /**
     * Gets a list of all pokemon from PokeAPI and imports them to the database
     * @return int Returns a count of the amount of pokemon changed
     */
    private function importPokemon(): int
    {
        $this->line('Importing pokemon...');

        $changedCount = 0;
        $pokemonCollection = $this->api->getPokemon();

        $bar = $this->output->createProgressBar(count($pokemonCollection));
        $bar->start();

        foreach ($pokemonCollection as $data) {
            $wasChanged = $this->importer->importPokemon($data);
            $bar->advance();
            if ($wasChanged) {
                $changedCount++;
            }
        }

        $bar->finish();
        $this->newLine();
        return $changedCount;
    }

    /**
     * Gets a list of all types from the config JSON and imports them to the database
     * @return int Returns a count of the amount of games changed
     */
    private function importGames(): int {
        $this->line('Importing pokemon...');

        $changedCount = 0;
        $json = $this->importer->getGamesJson();
        $bar = $this->output->createProgressBar(count($json));
        $bar->start();

        foreach ($json as $name => $code) {
            $wasChanged = $this->importer->importGame($name, $code);
            $bar->advance();
            if ($wasChanged) {
                $changedCount++;
            }
        }

        $bar->finish();
        $this->newLine();
        return $changedCount;
    }
}
