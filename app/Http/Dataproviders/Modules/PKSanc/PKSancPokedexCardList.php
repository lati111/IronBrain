<?php
namespace App\Http\Dataproviders\Modules\PKSanc;

use App\Enum\GenericStringEnum;
use App\Enum\PKSanc\PokedexMarkings;
use App\Exceptions\IronBrainException;
use App\Http\Dataproviders\AbstractCardlist;
use App\Http\Dataproviders\Filters\PKSanc\PokemonTypeSelectFilter;
use App\Http\Dataproviders\Interfaces\FilterableDataproviderInterface;
use App\Http\Dataproviders\Traits\HasFilters;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\PokedexMarking;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lati111\LaravelDataproviders\Filters\CustomColumn;
use Lati111\LaravelDataproviders\Filters\IsFilter;
use Lati111\LaravelDataproviders\Filters\NumberFilter;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Filterable;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Symfony\Component\HttpFoundation\Response;

class PKSancPokedexCardList extends AbstractCardlist implements FilterableDataproviderInterface
{
    use Dataprovider, Paginatable, HasPages, Searchable, Filterable, HasFilters;

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
            return $this->respond($e->getCode(), $e->publicMessage);
        }

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $data);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request): Builder
    {
        $user = Auth::user();

        $owned = StoredPokemon::selectRaw('count(*)')
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

        // marked as caught
        $markedAsRead = PokedexMarking::selectRaw('count(*)')
            ->whereRaw(sprintf("`%s`.`pokedex_id` = `%s`.`pokedex_id`", PokedexMarking::getTableName(), Pokemon::getTableName()))
            ->whereRaw(sprintf("`%s`.`form_index` = `%s`.`form_index`", PokedexMarking::getTableName(), Pokemon::getTableName()))
            ->whereRaw(sprintf("`%s`.`marking` = '%s'", PokedexMarking::getTableName(), PokedexMarkings::CAUGHT))
            ->whereRaw(sprintf("`%s`.`user_uuid` = '%s'", PokedexMarking::getTableName(), $user->uuid))
            ->toSql();


        // marked as hidden
        $markedAsHidden = PokedexMarking::selectRaw('count(*)')
            ->whereRaw(sprintf("`%s`.`pokedex_id` = `%s`.`pokedex_id`", PokedexMarking::getTableName(), Pokemon::getTableName()))
            ->whereRaw(sprintf("`%s`.`form_index` = `%s`.`form_index`", PokedexMarking::getTableName(), Pokemon::getTableName()))
            ->whereRaw(sprintf("`%s`.`marking` = '%s'", PokedexMarking::getTableName(), PokedexMarkings::HIDDEN))
            ->whereRaw(sprintf("`%s`.`user_uuid` = '%s'", PokedexMarking::getTableName(), $user->uuid))
            ->toSql();

        /** @var Builder $pokemonCollection */
        $pokemonCollection = Pokemon::selectRaw(sprintf(
                '(%s) as `amount_owned`, (%s) as `shinies_owned`, ((%s) > 0) as `owned`, ((%s) = 0) as `unowned`, ((%s) > 0) as `marked-as-read`, ((%s) > 0) as `marked-as-hidden`',
                $amountOwned, $shiniesOwned, $owned, $owned, $markedAsRead, $markedAsHidden
            ))
            ->orderBy('internal_pokedex_id')
            ->orderBy('form_index')
            ->addSelect([
                'species',
                'species_name',
                'form_name',
                'pokedex_id',
                'form_index',
                'sprite'
            ]);

        return $pokemonCollection;
    }

    public function filters(Request $request): JsonResponse
    {
        // Gets either a list of available filters, or a list of available options for a filter if one is specified
        $data = $this->getFilterData($request);

        unset($data['hidden']);

        // Return the data as a JsonResponse
        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $data);
    }

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return ['species_name', 'form_name'];
    }

    public function getFilterList(): array {
        $filters = [];

        $user = Auth::user();
        $markedAsHidden = PokedexMarking::selectRaw('count(*)')
            ->whereRaw(sprintf("`%s`.`pokedex_id` = `%s`.`pokedex_id`", PokedexMarking::getTableName(), Pokemon::getTableName()))
            ->whereRaw(sprintf("`%s`.`form_index` = `%s`.`form_index`", PokedexMarking::getTableName(), Pokemon::getTableName()))
            ->whereRaw(sprintf("`%s`.`marking` = '%s'", PokedexMarking::getTableName(), PokedexMarkings::HIDDEN))
            ->whereRaw(sprintf("`%s`.`user_uuid` = '%s'", PokedexMarking::getTableName(), $user->uuid))
            ->toSql();
        $selector = new CustomColumn(sprintf('(%s)', $markedAsHidden), 'hidden');
        $filters['hidden'] = new IsFilter(new Pokemon(), $selector, 0);

        $filter = new NumberFilter(new Pokemon(), 'generation');
        $filters['generation'] = $filter;

        $filters['type'] = new PokemonTypeSelectFilter();

        return $filters;
    }
}
