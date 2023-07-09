<?php

namespace App\Service\PKSanc\CsvHydrator;

use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Trainer;
use Illuminate\Support\Facades\Auth;
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
            //TODO formal error screen
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

    protected function getPokemon(int $PID, string $metDate, string $game, Trainer $trainer): StoredPokemon
    {
        return StoredPokemon::select('pksanc__stored_pokemon.*')
            ->where('owner_uuid', Auth::user()->uuid)
            ->rightJoin('pksanc__origin', 'pksanc__stored_pokemon.uuid', '=', 'pksanc__origin.pokemon_uuid')
            ->where('trainer_uuid', $trainer->uuid)
            //->where('validated_at', '!=', null)
            ->where('met_date', $metDate)
            ->where('game', $game)
            ->where('PID', $PID)
            ->first() ?? new StoredPokemon;
    }

    protected function getTrainer(int $tid, int $sid, string $name): Trainer
    {
        return Trainer::where('trainer_id', $tid)
            ->where('secret_id', $sid)
            ->where('name', $name)
            ->first() ?? new Trainer();
    }

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
