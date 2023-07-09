<?php

namespace App\Service\PKSanc;
use App\Enum\PKSanc\CsvVersions;
use App\Exceptions\Project\PKSanc\ImportException;
use App\Models\PKSanc\ImportCsv;
use App\Service\PKSanc\CsvHydrator\AbstractCsvHydrator;
use App\Service\PKSanc\CsvHydrator\CsvHydratorV1;

class DepositService
{
    public function stageImport(ImportCsv $csv): ImportCsv {
        $csvFile = fopen($csv->getCsvPath(), 'r');
        $headers = fgetcsv($csvFile);
        $headerString =  implode(',', $headers);

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
            for ($i=0; $i < count($headers); $i++) {
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

    private function getImporter(string $version, string $headers, ImportCsv $csv): AbstractCsvHydrator {
        $version = floatval(substr($version, 1));
        switch(true) {
            case ($version >= 1 && $version < 2 && $headers === CsvVersions::V1):
                return new CsvHydratorV1($csv);
            default:
                throw ImportException::unknownCsvVersion();
        }
    }
}
