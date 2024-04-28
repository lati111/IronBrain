<?php
namespace App\Dataproviders\Cardlists\Modules\PKSanc;

use App\Exceptions\IronBrainException;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Filterable;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;

class PKSancPokedexCardList extends AbstractPKSancOverviewCardList
{
    use Dataprovider, Paginatable, Searchable;

    /**
     * Gets the data after being modified by the query parameters
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse The data in JSON format
     */
    public function data(Request $request): JsonResponse
    {
        try {
            $data = $this->getData($request)
                ->get()
                ->map(function (Pokemon $pkmn) {
                    if ($pkmn->sprite === null) {
                        $pkmn->sprite = Pokemon::where('species', $pkmn->species)->where('form_index', 0)->first()->sprite;
                    }

                    if ($pkmn->sprite !== null) {
                        $pkmn['sprite'] = asset('img/modules/pksanc/pokemon/'.$pkmn->sprite);
                    } else {
                        $pkmn['sprite'] = asset('img/modules/pksanc/pokemon/unknown_sprite.png');
                    }

                    return $pkmn;
                });
        } catch (IronBrainException $e) {
            return response()->json($e->publicMessage, $e->getCode());
        }

        return response()->json($data, 200);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request): Builder
    {
        $unowned = StoredPokemon::selectRaw('count(*)')
            ->whereRaw(sprintf("`%s`.`pokemon` = `%s`.`pokemon`", Pokemon::getTableName(), StoredPokemon::getTableName()))
            ->toSql();

        // amount owned subquery
        $amountOwned = StoredPokemon::selectRaw('count(*)')
            ->whereRaw(sprintf("`%s`.`pokemon` = `%s`.`pokemon`", Pokemon::getTableName(), StoredPokemon::getTableName()))
            ->toSql();

        // shinies owned
        $shiniesOwned = StoredPokemon::selectRaw('count(*)')
            ->whereRaw('`is_shiny` = 1')
            ->whereRaw(sprintf("`%s`.`pokemon` = `%s`.`pokemon`", Pokemon::getTableName(), StoredPokemon::getTableName()))
            ->toSql();

        /** @var Builder $pokemonCollection */
        $pokemonCollection = Pokemon::selectRaw(sprintf(
                '(%s) as `amount_owned`, (%s) as `shinies_owned`, ((%s) = 0) as `unowned`',
                $amountOwned, $shiniesOwned, $unowned
            ))
            ->orderBy('internal_pokedex_id')
            ->orderBy('form_index')
            ->addSelect([
                'species',
                'species_name',
                'form_name',
                'pokedex_id',
                'sprite'
            ]);

        return $pokemonCollection;
    }

    /**
     * Gets the amount of pages that exists with the given query parameters
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse The amount of pages in JSON format
     */
    public function count(Request $request): JsonResponse
    {
        return response()->json($this->getPages($request), 200);
    }

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return ['species_name', 'form_name'];
    }
}
