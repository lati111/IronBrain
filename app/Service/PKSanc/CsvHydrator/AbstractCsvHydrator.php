<?php

namespace App\Service\PKSanc\CsvHydrator;

use App\Enum\PKSanc\CsvVersions;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Validation\Validator;

abstract class AbstractCsvHydrator
{
    protected ImportCsv $importCsv;
    protected array $data = [];

    public function __construct(ImportCsv $csv)
    {
        $this->importCsv = $csv;
    }

    public function hydrate(array $data, int $line): StoredPokemon
    {
        $this->loadData($data);
        if ($this->validate($data) === false) {
            dd($this->data);
        }
        $pokemon = $this->import($data, $line);

        return $pokemon;
    }

    private function loadData(array $data): void
    {
        $this->data = [];
        foreach ($data as $key => $value) {
            $this->data[$key]['value'] = $value;
        }
    }

    abstract protected function validate(array $data): bool;

    abstract protected function import(array $data, int $line): StoredPokemon;

    protected function handleErrors(Validator $validator)
    {
        foreach ($validator->errors()->messages() as $field => $errors) {
            $this->data[$field]['errors'] = $errors;
        }
    }

    protected function parseBool(string $bool): bool
    {
        if ($bool === 'True') {
            return true;
        }

        return false;
    }
}
