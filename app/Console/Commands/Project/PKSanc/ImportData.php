<?php

namespace App\Console\Commands\Project\PKSanc;

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

    protected $signature = 'pksanc:import';

    protected $description = 'Imports PKSanc data from PokeApi';

    public function handle()
    {
        $this->line('Importing PKSanc data...');
        $this->newLine();

        $typesChangedCount = $this->importTypes();
        $naturesChangedCount = $this->importNatures();
        $movesChangedCount = $this->importMoves();
        $abilitiesChangedCount = $this->importAbilities();
        $pokeballsChangedCount = $this->importPokeballs();
        $pokemonChangedCount = $this->importPokemon();

        $changedString = '%s imported';
        $this->info('Import successful!');
        $this->table(
            ['', ''],
            [
                ['Type', sprintf($changedString, $typesChangedCount)],
                ['Nature', sprintf($changedString, $naturesChangedCount)],
                ['Move', sprintf($changedString, $movesChangedCount)],
                ['Ability', sprintf($changedString, $abilitiesChangedCount)],
                ['Pokeball', sprintf($changedString, $pokeballsChangedCount)],
                ['Pokemon', sprintf($changedString, $pokemonChangedCount)],
            ]
        );
    }

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
}
