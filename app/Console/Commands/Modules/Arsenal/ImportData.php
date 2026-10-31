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
        $changedString = '%s changed';
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
                $changedCount = $this->importWeapons();
                $results[] = ['Weapons', sprintf($changedString, $changedCount)];

                $changedCount = $this->importWarframes();
                $results[] = ['Warframes', sprintf($changedString, $changedCount)];

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

        $changedCount = 0;
        $failures = [];
        try {
            $this->line('Fetching warframes from API...');
            $warframeCollection = $this->api->getWarframes();

            $this->line('Fetch complete. Importing warframes...');
            $bar = $this->output->createProgressBar(count($warframeCollection));
            $bar->start();

            foreach ($warframeCollection as $typeData) {
                if ($this->importItem(fn () => $this->importer->importWarframe($typeData), $typeData, $failures)) {
                    $changedCount++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->reportFailures($failures);
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

        $changedCount = 0;
        $failures = [];
        try {
            $this->line('Fetching weapons from API...');
            $itemCollection = $this->api->getWeapons();

            $this->line('Fetch complete. Importing weapons...');
            $bar = $this->output->createProgressBar(count($itemCollection));
            $bar->start();

            foreach ($itemCollection as $data) {
                if ($this->importItem(fn () => $this->importer->importWeapon($data), $data, $failures)) {
                    $changedCount++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->reportFailures($failures);
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

        $changedCount = 0;
        $failures = [];
        try {
            $this->line('Fetching companions from API...');
            $itemCollection = $this->api->getCompanions();

            $this->line('Fetch complete. Importing companions...');
            $bar = $this->output->createProgressBar(count($itemCollection));
            $bar->start();

            foreach ($itemCollection as $data) {
                if ($this->importItem(fn () => $this->importer->importCompanion($data), $data, $failures)) {
                    $changedCount++;
                }
                $bar->advance();
            }

            $bar->finish();
            $this->reportFailures($failures);
        } catch (\ErrorException|GuzzleException $e) {
            $this->error($e->getMessage());
            $this->line('Skipping companion import');
        } finally {
            $this->newLine();
        }

        return $changedCount;
    }

    /**
     * Imports a single item, recording a failure instead of aborting the whole import
     * @param callable $import The import callback, returning whether the item was changed
     * @param array $data The item data, used to identify the item when it fails
     * @param array $failures The list of failures to add to
     * @return bool Returns whether the item was changed
     */
    private function importItem(callable $import, array $data, array &$failures): bool
    {
        try {
            return $import();
        } catch (\Exception $e) {
            $failures[] = sprintf('%s (%s): %s', $data['name'] ?? '?', $data['uniqueName'] ?? '?', $e->getMessage());
            return false;
        }
    }

    /**
     * Outputs the items that failed to import
     * @param array $failures The list of failures
     */
    private function reportFailures(array $failures): void
    {
        if ($failures === []) {
            return;
        }

        $this->newLine();
        $this->warn(sprintf('%d item(s) failed to import:', count($failures)));
        foreach ($failures as $failure) {
            $this->line(' - ' . $failure);
        }
    }
}
