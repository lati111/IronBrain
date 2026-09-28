<?php

namespace App\Console\Commands\Modules\Arsenal;

use App\Console\Commands\Traits\PromptsForMissingImages;
use App\Console\Commands\Traits\RunsImports;
use App\Service\Arsenal\ImportService;
use App\Service\Arsenal\WarframeApiService;
use Illuminate\Console\Command;

class ImportData extends Command
{
    use PromptsForMissingImages, RunsImports;

    private readonly WarframeApiService $api;
    private ImportService $importer;

    public function __construct(WarframeApiService $api)
    {
        $this->api = $api;

        parent::__construct();
    }

    /** {@inheritdoc} */
    protected $signature = 'import:arsenal {--filter=} {--database=} {--refresh-images : Download all images again, even if they already exist}';

    /** {@inheritdoc} */
    protected $description = 'Imports arsenal data from the WarframeApi';

    public function handle(ImportService $importer): int
    {
        // The importer keeps track of what it imported, so a fresh one is used for every run
        $this->importer = $importer;

        if (($connection = $this->option('database')) !== null) {
            $this->importer->setConnection($connection);
        }

        $this->importer->onMissingImage($this->missingImagePrompt());
        $this->importer->setRefreshImages((bool) $this->option('refresh-images'));

        $this->line('Importing arsenal data...');
        $this->newLine();

        // Weapons are imported first, so warframes can link their exalted weapons
        $imports = [
            'weapon' => fn () => $this->importWeapons(),
            'warframe' => fn () => $this->importWarframes(),
            'companion' => fn () => $this->importCompanions(),
        ];

        $filter = rtrim(strtolower($this->option('filter') ?? ''), 's');
        if ($filter !== '' && !isset($imports[$filter])) {
            $this->error(sprintf('Unknown filter "%s". Valid filters are: %s', $this->option('filter'), implode(', ', array_keys($imports))));
            return self::INVALID;
        }

        foreach ($imports as $name => $import) {
            if ($filter === '' || $filter === $name) {
                $import();
            }
        }

        return $this->finishImport('Arsenal');
    }

    /**
     * Gets a list of all warframes and import them
     */
    private function importWarframes(): void
    {
        $this->runImport('warframes',
            fn () => $this->fetch('warframes', fn () => $this->api->getWarframes(), 'warframe'),
            fn (array $data) => $this->importer->importWarframe($data),
            fn () => $this->importer->pruneWarframes(),
            fn (array $data) => $data['name'] ?? '',
        );
    }

    /**
     * Gets a list of all weapons and import them
     */
    private function importWeapons(): void
    {
        $this->runImport('weapons',
            fn () => $this->fetch('weapons', fn () => $this->api->getWeapons(), 'weapon'),
            fn (array $data) => $this->importer->importWeapon($data),
            fn () => $this->importer->pruneWeapons(),
            fn (array $data) => $data['name'] ?? '',
        );
    }

    /**
     * Gets a list of all companions and import them
     */
    private function importCompanions(): void
    {
        $this->runImport('companions',
            fn () => $this->fetch('companions', fn () => $this->api->getCompanions(), 'companion'),
            fn (array $data) => $this->importer->importCompanion($data),
            fn () => $this->importer->pruneCompanions(),
            fn (array $data) => $data['name'] ?? '',
        );
    }

    /**
     * Fetches items from the API and downloads their images
     * @param string $label The plural name of the items
     * @param \Closure(): array $fetch Fetches the items
     * @param string $category The asset category of the items
     * @return array Returns the items
     */
    private function fetch(string $label, \Closure $fetch, string $category): array
    {
        $this->line(sprintf('Fetching %s from API...', $label));
        $items = $fetch();

        $this->importer->prefetchAssets($items, $category, $this->downloadProgress(sprintf('%s images', rtrim($label, 's'))));

        return $items;
    }
}
