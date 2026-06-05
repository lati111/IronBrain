<?php

namespace App\Console\Commands\Modules\Arsenal;

use App\Service\Arsenal\ImportService;
use App\Service\Arsenal\WarframeApiService;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Command;

class ImportData extends Command
{
    private readonly WarframeApiService $api;
    private readonly ImportService $importer;

    public function __construct(ImportService $importService, WarframeApiService $api)
    {
        $this->importer = $importService;
        $this->api = $api;

        parent::__construct();
    }

    /** {@inheritdoc} */
    protected $signature = 'import:arsenal {--filter=} {--database=}';

    /** {@inheritdoc} */
    protected $description = 'Imports arsenal data from the WarframeApi';

    public function handle(): void
    {
        if (($connection = $this->option('database')) !== null) {
            $this->importer->setConnection($connection);
        }

        $this->line('Importing arsenal data...');
        $this->newLine();

        $results = [];
        $changedString = '%s imported';
        switch(strtolower($this->option('filter'))) {
            case 'warframe':
            case 'warframes':
                $changedCount = $this->importWarframes();
                $results[] = ['Warframes', sprintf($changedString, $changedCount)];
                break;
            case 'weapon':
            case 'weapons':
                $changedCount = $this->importWeapons();
                $results[] = ['Weapons', sprintf($changedString, $changedCount)];
                break;
            case 'companion':
            case 'companions':
                $changedCount = $this->importCompanions();
                $results[] = ['Companions', sprintf($changedString, $changedCount)];
                break;
            default:
                $changedCount = $this->importWarframes();
                $results[] = ['Warframes', sprintf($changedString, $changedCount)];

                $changedCount = $this->importWeapons();
                $results[] = ['Weapons', sprintf($changedString, $changedCount)];

                $changedCount = $this->importCompanions();
                $results[] = ['Companions', sprintf($changedString, $changedCount)];
        }

        $this->info('Import successful!');
        $this->table(['', ''], $results);
    }

    /**
     * Gets a list of all warframes and import them
     * @return int Returns a count of the amount of warframes changed
     */
    private function importWarframes(): int
    {
        $this->line('Importing warframes...');

        try {
            $changedCount = 0;
            $warframeCollection = $this->api->getWarframes();

            $bar = $this->output->createProgressBar(count($warframeCollection));
            $bar->start();

            foreach ($warframeCollection as $typeData) {
                $wasChanged = $this->importer->importWarframe($typeData);
                $bar->advance();
                if ($wasChanged) {
                    $changedCount++;
                }
            }

            $bar->finish();
        } catch (\ErrorException|GuzzleException $e) {
            $this->error($e->getMessage());
            $this->line('Skipping warframe import');
        } finally {
            $this->newLine();
        }

        return $changedCount;
    }

    /**
     * Gets a list of all weapons and import them
     * @return int Returns a count of the amount of weapons changed
     */
    private function importWeapons(): int
    {
        $this->line('Importing weapons...');

        try {
            $changedCount = 0;
            $itemCollection = $this->api->getWeapons();

            $bar = $this->output->createProgressBar(count($itemCollection));
            $bar->start();

            foreach ($itemCollection as $data) {
                $wasChanged = $this->importer->importWeapon($data);
                $bar->advance();
                if ($wasChanged) {
                    $changedCount++;
                }
            }

            $bar->finish();
        } catch (\ErrorException|GuzzleException $e) {
            $this->error($e->getMessage());
            $this->line('Skipping weapon import');
        } finally {
            $this->newLine();
        }

        return $changedCount;
    }

    /**
     * Gets a list of all weapons and import them
     * @return int Returns a count of the amount of weapons changed
     */
    private function importCompanions(): int
    {
        $this->line('Importing companions...');

        try {
            $changedCount = 0;
            $itemCollection = $this->api->getCompanions();

            $bar = $this->output->createProgressBar(count($itemCollection));
            $bar->start();

            foreach ($itemCollection as $data) {
                $wasChanged = $this->importer->importCompanion($data);
                $bar->advance();
                if ($wasChanged) {
                    $changedCount++;
                }
            }

            $bar->finish();
        } catch (\ErrorException|GuzzleException $e) {
            $this->error($e->getMessage());
            $this->line('Skipping companion import');
        } finally {
            $this->newLine();
        }

        return $changedCount;
    }
}
