<?php

namespace App\Service\PKSanc\CsvHydrator;

use App\Exceptions\Modules\PKSanc\ImportException;
use App\Exceptions\Modules\PKSanc\ImportValidationException;
use App\Models\AbstractModel;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\StagedPokemon;
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

    /**
     * Attampt to hydrate a pokemon from the given csv line
     * @param array $data The complete csv in array format
     * @param int $line The line of the csv the pokemon is locaed on
     * @returns StoredPokemon|null Returns the hydrated pokemon, or null if it was already hydrated
     * @throws ImportValidationException Thrown when the validation fails during hydration
     */
    public function hydrate(array $data, int $line): StoredPokemon|null
    {
        $this->loadData($data);
        if ($this->validate($data) === false) {
            $errors = [];
            foreach ($this->data as $column => $errorData) {
                if (count($errorData) > 1) {
                    foreach ($errorData['errors'] as $error) {
                        $errors[] = sprintf('%s: %s. %s', $column, $errorData['value'], $error);
                    }
                }
            }

            $this->importCsv->delete();
            foreach($this->importCsv->Pokemon()->get() as $pokemon) {
                $pokemon->delete();
            }

            throw new ImportValidationException($errors);
        }

        return $this->import($data, $line);
    }

    private function loadData(array $data): void
    {
        $this->data = [];
        foreach ($data as $key => $value) {
            $this->data[$key]['value'] = $value;
        }
    }

    abstract protected function validate(array $data): bool;

    abstract protected function import(array $data, int $line): StoredPokemon|null;

    protected function stagePokemon(StoredPokemon $pokemon): StagedPokemon|null
    {
        $origin = $pokemon->getOrigin();
        $existingPokemon = StoredPokemon::select('pksanc__stored_pokemon.*')
            ->where('owner_uuid', Auth::user()->uuid)
            ->rightJoin('pksanc__origin', 'pksanc__stored_pokemon.uuid', '=', 'pksanc__origin.pokemon_uuid')
            ->where('trainer_uuid', $origin->getTrainer()->uuid)
            ->where('validated_at', '!=', null)
            ->where('met_date', $origin->met_date)
            ->where('PID', $pokemon->PID)
            ->first();

        $stagedPokemon = new StagedPokemon;
        $stagedPokemon->new_pokemon_uuid = $pokemon->uuid;
        if ($existingPokemon !== null) {
            $stagedPokemon->old_pokemon_uuid = $existingPokemon->uuid;

            if ($this->wasPokemonUpdated($pokemon, $existingPokemon) === false) {
                $pokemon->delete();
                return null;
            }
        }

        $stagedPokemon->save();
        return $stagedPokemon;
    }

    protected function wasPokemonUpdated(StoredPokemon $pokemon, StoredPokemon $existingPokemon) {
        $wasChanged = ($this->modelHasChanges($pokemon, $existingPokemon, [
            'uuid',
            'csv_uuid',
            'csv_line',
            'validated_at',
            'created_at',
            'updated_at'
        ]));

        $wasChanged = ($this->modelHasChanges($pokemon->getOrigin(), $existingPokemon->getOrigin(), [
            'uuid',
            'pokemon_uuid',
            'created_at',
            'updated_at'
        ])) ? true : $wasChanged;

        $wasChanged = ($this->modelHasChanges($pokemon->getStats(), $existingPokemon->getStats(), [
            'uuid',
            'pokemon_uuid',
            'created_at',
            'updated_at'
        ])) ? true : $wasChanged;

        $wasChanged = ($this->modelHasChanges($pokemon->getContestStats(), $existingPokemon->getContestStats(), [
            'uuid',
            'pokemon_uuid',
            'created_at',
            'updated_at'
        ])) ? true : $wasChanged;

        $wasChanged = ($this->modelHasChanges($pokemon->getMoveset(), $existingPokemon->getMoveset(), [
            'uuid',
            'pokemon_uuid',
            'created_at',
            'updated_at'
        ])) ? true : $wasChanged;

        return $wasChanged;
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

    protected function modelHasChanges(AbstractModel $model1, AbstractModel $model2, array $blacklist = []): bool
    {
        $wasChanged = false;
        $arr1 = $model1->makeHidden($blacklist)->attributesToArray();
        $arr2 = $model2->makeHidden($blacklist)->attributesToArray();

        foreach ($arr1 as $key => $value) {
            if ($arr2[$key] != $value) {
                $wasChanged = true;
            }
        }

        return $wasChanged;
    }
}
