<?php
namespace App\Dataproviders\Cardlists\Modules\PKSanc;

use App\Dataproviders\Cardlists\AbstractCardlist;
use App\Dataproviders\Filters\PKSanc\StoredPokemonTypeSelectFilter;
use App\Dataproviders\Interfaces\FilterableDataproviderInterface;
use App\Exceptions\IronBrainException;
use App\Models\PKSanc\Ability;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Nature;
use App\Models\PKSanc\Origin;
use App\Models\PKSanc\Pokeball;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Trainer;
use App\Models\PKSanc\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lati111\LaravelDataproviders\Filters\BoolFilter;
use Lati111\LaravelDataproviders\Filters\Conditions\IsConditionFilter;
use Lati111\LaravelDataproviders\Filters\CustomColumn;
use Lati111\LaravelDataproviders\Filters\DataSelectFilter;
use Lati111\LaravelDataproviders\Filters\DateFilter;
use Lati111\LaravelDataproviders\Filters\ForeignTable;
use Lati111\LaravelDataproviders\Filters\NumberFilter;
use Lati111\LaravelDataproviders\Filters\SelectFilter;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Filterable;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractPKSancOverviewCardList extends AbstractCardlist implements FilterableDataproviderInterface
{
    use Dataprovider, Paginatable, Searchable, Filterable;

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
                ->map(function (StoredPokemon $pkmn) {
                    $pkmn['sprite'] = asset('img/modules/pksanc/pokemon/'.$pkmn->getSprite());
                    $pkmn['pokeball_sprite'] = asset('img/modules/pksanc/pokeball/'.$pkmn['pokeball_sprite']);
                    $pkmn['tera_sprite'] = asset('img/modules/pksanc/icon/tera/'.$pkmn['tera_type'].'.png');
                    $pkmn['hidden_power_sprite'] = asset('img/modules/pksanc/icon/type/'.$pkmn['hidden_power_type'].'_full.png');
                    $pkmn['hidden_power_type'] = 'Hidden power ' . $pkmn['hidden_power_type'];

                    switch ($pkmn['gender']) {
                        case '-':
                            $pkmn['gender'] = 'None';
                            $pkmn['gender_sprite'] = asset('img/modules/pksanc/icon/gender/none.png');
                            break;
                        case 'F':
                            $pkmn['gender'] = 'Female';
                            $pkmn['gender_sprite'] = asset('img/modules/pksanc/icon/gender/female.png');
                            break;
                        case 'M':
                            $pkmn['gender'] = 'Male';
                            $pkmn['gender_sprite'] = asset('img/modules/pksanc/icon/gender/male.png');
                            break;
                    }

                    switch ($pkmn['trainer_gender']) {
                        case 'F':
                            $pkmn['trainer_gender'] = 'Female';
                            $pkmn['trainer_gender_sprite'] = asset('img/modules/pksanc/icon/gender/female.png');
                            break;
                        case 'M':
                            $pkmn['trainer_gender'] = 'Male';
                            $pkmn['trainer_gender_sprite'] = asset('img/modules/pksanc/icon/gender/male.png');
                            break;
                    }

                    return $pkmn;
                });
        } catch (IronBrainException $e) {
            return response()->json($e->publicMessage, $e->getCode());
        }

        return response()->json($data, 200);
    }

    // Method to be called from a route to get filter options
    public function filters(Request $request): JsonResponse
    {
        // Gets either a list of available filters, or a list of available options for a filter if one is specified
        $data = $this->getFilterData($request);

        // Return the data as a JsonResponse
        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Apply a series of select, join and order by statements to get the correct data
     * @param Builder $query The query to modify
     * @return Builder The modified query
     */
    protected function applySelects(Builder $query): Builder
    {
        return $query
            ->jointable(Pokemon::getTableName(), StoredPokemon::getTableName(), 'pokemon', '=', 'pokemon')
            ->jointable(Pokeball::getTableName(), StoredPokemon::getTableName(), 'pokeball', '=', 'pokeball')
            ->jointable(Nature::getTableName(), StoredPokemon::getTableName(), 'nature', '=', 'nature')
            ->jointable(Ability::getTableName(), StoredPokemon::getTableName(), 'ability', '=', 'ability')
            ->jointable(ImportCsv::getTableName(), StoredPokemon::getTableName(), 'csv_uuid', '=', 'uuid')
            ->jointable(Origin::getTableName(), StoredPokemon::getTableName(), 'uuid', '=', 'pokemon_uuid')
            ->leftjointable(Game::getTableName(), Origin::getTableName(), 'game', '=', 'game')
            ->leftjointable(Trainer::getTableName(), Origin::getTableName(), 'trainer_uuid', '=', 'uuid')
            ->orderBy('friendship', 'desc')
            ->orderBy('level', 'desc')
            ->orderBy('is_shiny', 'desc')
            ->selectRaw("CONCAT(`species_name`, COALESCE(CONCAT(' (', `form_name`, ')'), '')) as `pokemon_name`")
            ->addSelect([
                StoredPokemon::getTableName().'.uuid as uuid',
                StoredPokemon::getTableName().'.pokemon as pokemon',
                'nickname',
                StoredPokemon::getTableName().'.gender as gender',
                StoredPokemon::getTableName().'.pokeball as pokeball',
                Pokeball::getTableName().'.name as pokeball_name',
                Pokeball::getTableName().'.sprite as pokeball_sprite',
                'level',
                Nature::getTableName().'.name as nature',
                Ability::getTableName().'.name as ability',
                'hidden_power_type',
                'tera_type',
                ImportCsv::getTableName().'.name as save_name',
                Game::getTableName().'.name as game_name',
                Trainer::getTableName().'.name as trainer_name',
                Trainer::getTableName().'.gender as trainer_gender',
                'met_location',
                'is_shiny',
                'is_alpha',
                'can_gigantamax',
                'has_n_sparkle'
            ]);
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
        return ['pksanc__stored_pokemon.nickname', 'pksanc__stored_pokemon.pokemon'];
    }

    /** { @inheritdoc } */
    public function getFilterList(): array {
        $filters = [];

        $pokemonForeignTable = new ForeignTable(StoredPokemon::class, 'pokemon', Pokemon::class, 'pokemon');

        $filter = new DataSelectFilter(new StoredPokemon, 'species_name', route('pksanc.owned-species.dataselect'),
            'species', 'species_name', $pokemonForeignTable);
        $filters['species'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'gender', StoredPokemon::getTableName());
        $filters['gender'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignTable(StoredPokemon::class, 'nature', Nature::class, 'nature'));
        $filters['nature'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignTable(StoredPokemon::class, 'ability', Ability::class, 'ability'));
        $filters['ability'] = $filter;

        $filter = new StoredPokemonTypeSelectFilter();
        $filters['type'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignTable(StoredPokemon::class, 'tera_type', Type::class, 'type', 'tt_type'));
        $filters['tera_type'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignTable(StoredPokemon::class, 'hidden_power_type', Type::class, 'type', 'hpt_type'));
        $filters['hidden_power'] = $filter;

        $filter = new NumberFilter(new StoredPokemon, 'level');
        $filters['level'] = $filter;

        $filter = new NumberFilter(new StoredPokemon, 'friendship');
        $filters['friendship'] = $filter;

        $selector = new CustomColumn(sprintf("concat(%s.name, ' - ', %s.name)", 'ic_game', ImportCsv::getTableName()), 'save_file');
        $filter = new SelectFilter(new StoredPokemon, $selector,
            new ForeignTable(ImportCsv::class, 'game', Game::class, 'game', 'ic_game'));
        $filter->addLinkedTable(new ForeignTable(StoredPokemon::class, 'csv_uuid', ImportCsv::class, 'uuid'));
        $filters['save_file'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignTable(Origin::class, 'game', Game::class, 'game'));
        $filter->addLinkedTable(new ForeignTable(StoredPokemon::class, 'uuid', Origin::class, 'pokemon_uuid'));
        $filters['game'] = $filter;

        $filter = new DateFilter(new StoredPokemon, 'met_date',
            new ForeignTable(StoredPokemon::class, 'uuid', Origin::class, 'pokemon_uuid'));
        $filters['met_date'] = $filter;

        $filter = new NumberFilter(new StoredPokemon, 'height');
        $filters['height'] = $filter;

        $filter = new NumberFilter(new StoredPokemon, 'weight');
        $filters['weight'] = $filter;

        $filter = new BoolFilter('is', new StoredPokemon, 'is_shiny');
        $filters['shiny'] = $filter;

        $filter = new BoolFilter('is', new StoredPokemon, 'is_alpha');
        $filters['alpha'] = $filter;

        $filter = new BoolFilter('can', new StoredPokemon, 'can_gigantamax');
        $filters['gigantamax'] = $filter;

        $filter = new BoolFilter('has', new StoredPokemon, 'has_n_sparkle');
        $filters['n_sparkle'] = $filter;

        $filter = new NumberFilter(new StoredPokemon, 'generation', new ForeignTable(StoredPokemon::class, 'pokemon', Pokemon::class, 'pokemon'));
        $filters['generation'] = $filter;

        // add owned condition to all filters
        $ownedCondition = new IsConditionFilter(StoredPokemon::getTableName().'.owner_uuid', Auth::user()->uuid);
        foreach ($filters as $key => $filter) {
            $filter->addCondition($ownedCondition);
            $filters[$key] = $filter;
        }

        return $filters;
    }
}
