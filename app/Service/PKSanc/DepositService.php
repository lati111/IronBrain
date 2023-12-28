<?php

namespace App\Service\PKSanc;

use App\Enum\PKSanc\CsvVersions;
use App\Exceptions\Modules\PKSanc\ImportException;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\StagedPokemon;
use App\Models\PKSanc\StoredPokemon;
use App\Service\PKSanc\CsvHydrator\AbstractCsvHydrator;
use App\Service\PKSanc\CsvHydrator\CsvHydratorV1;
use Carbon\Carbon;

class DepositService
{
    public function stageImport(ImportCsv $csv): ImportCsv
    {
        $csvFile = fopen($csv->getCsvPath(), 'r');
        $headers = fgetcsv($csvFile);
        $headerString = implode(',', $headers);

        $lineIndex = 1;
        $finished = false;
        $importer = null;
        while ($finished === false) {
            $line = fgetcsv($csvFile);
            if ($line === false) {
                $finished = true;
                continue;
            }

            $data = [];
            for ($i = 0; $i < count($headers); $i++) {
                $data[$headers[$i]] = $line[$i];
            }

            if ($importer === null) {
                $importer = $this->getImporter($data['Version'], $headerString, $csv);
            }

            $importer->hydrate($data, $lineIndex);
            $lineIndex++;
        }

        return $csv;
    }

    public function confirmStaging(StagedPokemon $stagedPokemon): StoredPokemon
    {
        $pokemon = $stagedPokemon->getOldPokemon();
        $newPokemon = $stagedPokemon->getNewPokemon();

        if ($pokemon !== null) {
            $pokemon->nickname = $newPokemon->nickname;
            $pokemon->pokemon = $newPokemon->pokemon;
            $pokemon->gender = $newPokemon->gender;
            $pokemon->nature = $newPokemon->nature;
            $pokemon->ability = $newPokemon->ability;
            $pokemon->pokeball = $newPokemon->pokeball;
            $pokemon->hidden_power_type = $newPokemon->hidden_power_type;
            $pokemon->tera_type = $newPokemon->tera_type;
            $pokemon->friendship = $newPokemon->friendship;
            $pokemon->level = $newPokemon->level;
            $pokemon->height = $newPokemon->height;
            $pokemon->weight = $newPokemon->weight;
            $pokemon->csv_uuid = $newPokemon->csv_uuid;
            $pokemon->csv_line = $newPokemon->csv_line;

            $newOrigin = $newPokemon->Origin();
            $oldOrigin = $pokemon->Origin();
            $newOrigin->pokemon_uuid = $oldOrigin->pokemon_uuid;

            $newStats = $newPokemon->Stats();
            $oldStats = $pokemon->Stats();
            $newStats->pokemon_uuid = $oldStats->pokemon_uuid;

            $newContestStats = $newPokemon->ContestStats();
            $oldContestStats = $pokemon->ContestStats();
            $newContestStats->pokemon_uuid = $oldContestStats->pokemon_uuid;

            $newMoveset = $newPokemon->Moveset();
            $oldMoveset = $pokemon->Moveset();
            $newMoveset->pokemon_uuid = $oldMoveset->pokemon_uuid;

            $newOrigin->save();
            $newStats->save();
            $newContestStats->save();
            $newMoveset->save();
            $oldOrigin->delete();

            foreach($pokemon->Ribbons()->get() as $ribbon) {
                $ribbon->delete();
            }

            foreach($newPokemon->Ribbons()->get() as $ribbon) {
                $ribbon->pokemon_uuid = $pokemon->uuid;
                $ribbon->save();
            }

            $newPokemon->delete();
        } else {
            $pokemon = $newPokemon;
        }

        $stagedPokemon->delete();
        $pokemon->validated_at = Carbon::now();
        $pokemon->save();

        return $pokemon;
    }

    private function getImporter(string $version, string $headers, ImportCsv $csv): AbstractCsvHydrator
    {
        $version = floatval(substr($version, 1));
        switch (true) {
            case ($version >= 1 && $version < 2 && $headers === CsvVersions::V1):
                return new CsvHydratorV1($csv);
            default:
                throw ImportException::unknownCsvVersion();
        }
    }
}
