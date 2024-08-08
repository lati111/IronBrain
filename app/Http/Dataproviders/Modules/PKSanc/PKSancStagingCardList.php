<?php
namespace App\Http\Dataproviders\Modules\PKSanc;

use App\Exceptions\IronBrainException;
use App\Models\AbstractModel;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class PKSancStagingCardList extends AbstractPKSancOverviewCardList
{
    /**
     * { @inheritdoc }
     * @throws IronBrainException
     */
    protected function getContent(Request $request): Builder
    {
        $importUuid = $request->route()->parameter('import_uuid');
        $csv = ImportCsv::where('uuid', $importUuid)->first();
        if ($csv === null) {
            throw new IronBrainException(
                sprintf('No import csv matching the uuid %s found', $importUuid),
                'Import csv could not be loaded',
                ResponseAlias::HTTP_NOT_FOUND
            );
        }

        /** @var Builder $pokemonCollection */
        $pokemonCollection = $csv->Pokemon()
            ->where('validated_at', null)
            ->where(StoredPokemon::getTableName().'.owner_uuid', Auth::user()->uuid)
            ->with(['previous_version.old_pokemon' => [
                'pokemon',
                'pokeball',
                'nature',
                'ability',
                'tera_type',
                'hidden_power_type',
                'origin' => [
                    'trainer',
                    'original_game'
                ],
                'csv',
            ]])
            ->getQuery();

        return $this->applySelects($pokemonCollection);
    }

    /** { @inheritdoc } */
    protected function formatPokemon(StoredPokemon $pkmn): StoredPokemon {
        $pkmn['has_prev'] = false;
        if ($pkmn['previous_version'] === null) {
            return parent::formatPokemon($pkmn);
        }

        /** @var StoredPokemon|null $prevPkmn */
        $prevPkmn = $pkmn['previous_version']['old_pokemon'];
        if ($prevPkmn === null) {
            return parent::formatPokemon($pkmn);
        }

        $data = $prevPkmn->toArray();

        $prevPkmn['pokemon_name'] = ($data['pokemon']['form_name'] === null) ?
            $data['pokemon']['species_name'] : sprintf('%s (%s)', $data['pokemon']['species_name'], $data['pokemon']['form_name']);
        $prevPkmn['ability'] = $data['ability']['name'];
        $prevPkmn['nature'] = $data['nature']['name'];
        $prevPkmn['pokeball'] = $data['pokeball']['pokeball'];
        $prevPkmn['pokeball_name'] = $data['pokeball']['name'];
        $prevPkmn['save_name'] = $data['csv']['name'];
        $prevPkmn['game_name'] = $data['origin']['original_game']['name'];
        $prevPkmn['met_location'] = $data['origin']['met_location'];
        $prevPkmn['pokeball_sprite'] = $data['pokeball']['sprite'];
        $prevPkmn['trainer_name'] = $data['origin']['trainer']['name'];
        $prevPkmn['trainer_gender'] = $data['origin']['trainer']['gender'];

        $data = parent::formatPokemon($prevPkmn);
        foreach ($data->attributesToArray() as $key => $value) {
            $pkmn['prev-'.$key] = $value;
        }

        $pkmn['has_prev'] = true;
        return parent::formatPokemon($pkmn);
    }
}
