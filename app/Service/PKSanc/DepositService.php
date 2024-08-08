<?php

namespace App\Service\PKSanc;

use App\Enum\PKSanc\CsvVersions;
use App\Exceptions\Modules\PKSanc\ImportException;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\StagedPokemon;
use App\Models\PKSanc\StoredPokemon;
use App\Service\PKSanc\CsvHydrator\AbstractCsvHydrator;
use App\Service\PKSanc\CsvHydrator\CsvHydratorV1;
use App\Service\PKSanc\CsvHydrator\CsvHydratorV2;
use Carbon\Carbon;
use Throwable;

class DepositService
{

    /**
     * Imports a csv file and marks it's contents as staging
     * @param ImportCsv $csv The csv file that should be imported
     * @return ImportCsv Returns the imported csv
     * @throws Throwable
     */
    public function stageImport(ImportCsv $csv): ImportCsv
    {
        $csvFile = fopen($csv->getCsvPath(), 'r');
        $headers = fgetcsv($csvFile);
        $headerString = implode(',', $headers);

        $lineIndex = 1;
        $first = true;
        $finished = false;
        $importer = null;
        while ($finished === false) {
            $line = fgetcsv($csvFile);
            if ($line === false) {
                $finished = true;
                continue;
            }

            // Import lines to array
            $data = [];
            for ($i = 0; $i < count($headers); $i++) {
                $data[$headers[$i]] = $line[$i];
            }

            // Update csv version
            if($first === true) {
                $first = false;
                $csv->version = floatval($data['Version']);
                $csv->save();
            }

            if ($importer === null) {
                $importer = $this->getImporter($data['Version'], $headerString, $csv);
            }

            $importer->hydrate($data, $lineIndex);
            $lineIndex++;
        }

        return $csv;
    }

    /**
     * Apply the given staged pokemon
     * @param StagedPokemon $stagedPokemon
     * @return StoredPokemon
     */
    public function confirmStaging(StagedPokemon $stagedPokemon): StoredPokemon
    {
        $oldPokemon = $stagedPokemon->getOldPokemon();
        $newPokemon = $stagedPokemon->getNewPokemon();
        $stagedPokemon->delete();

        if ($oldPokemon !== null) {
            $newPokemon->prev_uuid = $oldPokemon->uuid;
            $newPokemon->version = $oldPokemon->version + 1;
            $newPokemon->save();

            $oldPokemon->delete();
        }

        $newPokemon->validated_at = Carbon::now();
        $newPokemon->save();

        return $newPokemon;
    }

    /**
     * Get the correct csv hydrator for this version
     * @param string $version The version of this csv as per PKSaveExtract
     * @param string $headers The headers of this csv file
     * @param ImportCsv $csv The csv model
     * @return AbstractCsvHydrator Returns the correct hydrator
     * @throws ImportException
     * @throws Throwable
     */
    private function getImporter(string $version, string $headers, ImportCsv $csv): AbstractCsvHydrator
    {
        $version = floatval(substr($version, 1));
        switch (true) {
            case ($version >= 1 && $version < 2 && $headers === CsvVersions::V1):
                return new CsvHydratorV1($csv);
            case ($version >= 2 && $version < 3 && $headers === CsvVersions::V1):
                return new CsvHydratorV2($csv);
            default:
                throw ImportException::unknownCsvVersion();
        }
    }
}
