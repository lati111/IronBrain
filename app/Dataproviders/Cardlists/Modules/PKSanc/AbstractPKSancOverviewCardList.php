<?php
namespace App\Dataproviders\Cardlists\Modules\PKSanc;

use App\Dataproviders\Cardlists\AbstractCardlist;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Filterable;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;

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

//        $filter = new SelectFilter(new StoredPokemon, 'species_name',
//            new ForeignData(StoredPokemon::class, 'pokemon', Pokemon::class, 'pokemon'));
//        $filters['species'] = $filter;
//
//        $filter = new SelectFilter(new StoredPokemon, 'gender');
//        $filters['gender'] = $filter;
//
//        $filter = new SelectFilter(new StoredPokemon, 'name',
//            new ForeignData(StoredPokemon::class, 'nature', Nature::class, 'nature'));
//        $filters['nature'] = $filter;
//
//        $filter = new SelectFilter(new StoredPokemon, 'name',
//            new ForeignData(StoredPokemon::class, 'ability', Ability::class, 'ability'));
//        $filters['ability'] = $filter;
//
//        $filter = new PokemonTypeSelectFilter();
//        $filters['type'] = $filter;
//
//        $filter = new SelectFilter(new StoredPokemon, 'name',
//            new ForeignData(StoredPokemon::class, 'tera_type', Type::class, 'type'));
//        $filters['tera_type'] = $filter;
//
//        $filter = new SelectFilter(new StoredPokemon, 'name',
//            new ForeignData(StoredPokemon::class, 'hidden_power_type', Type::class, 'type'));
//        $filters['hidden_power'] = $filter;
//
//        $filter = new NumberFilter(new StoredPokemon, 'level');
//        $filters['level'] = $filter;
//
//        $filter = new NumberFilter(new StoredPokemon, 'friendship');
//        $filters['friendship'] = $filter;
//
//        $gameTable = app(Game::class)->getTable();
//        $csvTable = app(ImportCsv::class)->getTable();
//        $selector = new CustomColumn(sprintf("concat(%s.name, ' - ', %s.name)", $gameTable, $csvTable), 'save_file');
//        $filter = new SelectFilter(new StoredPokemon, $selector,
//            new ForeignData(StoredPokemon::class, 'csv_uuid', ImportCsv::class, 'uuid',
//                new ForeignData(ImportCsv::class, 'game', Game::class, 'game')
//            )
//        );
//        $filters['save_file'] = $filter;
//
//        $filter = new SelectFilter(new StoredPokemon, 'name',
//            new ForeignData(StoredPokemon::class, 'uuid', Origin::class, 'pokemon_uuid',
//                new ForeignData(Origin::class, 'game', Game::class, 'game',)
//            )
//        );
//        $filters['game'] = $filter;
//
//        $filter = new DateFilter(new StoredPokemon, 'met_date',
//            new ForeignData(StoredPokemon::class, 'uuid', Origin::class, 'pokemon_uuid')
//        );
//        $filters['met_date'] = $filter;
//
//        $filter = new NumberFilter(new StoredPokemon, 'height');
//        $filters['height'] = $filter;
//
//        $filter = new NumberFilter(new StoredPokemon, 'weight');
//        $filters['weight'] = $filter;
//
//        $filter = new BoolFilter('is', new StoredPokemon, 'is_shiny');
//        $filters['shiny'] = $filter;
//
//        $filter = new BoolFilter('is', new StoredPokemon, 'is_alpha');
//        $filters['alpha'] = $filter;
//
//        $filter = new BoolFilter('can', new StoredPokemon, 'can_gigantamax');
//        $filters['gigantamax'] = $filter;
//
//        $filter = new BoolFilter('has', new StoredPokemon, 'has_n_sparkle');
//        $filters['n_sparkle'] = $filter;
//
//        $filter = new NumberFilter(new StoredPokemon, 'generation',
//            new ForeignData(StoredPokemon::class, 'pokemon', Pokemon::class, 'pokemon'));
//        $filters['generation'] = $filter;
//
//        $ownedCondition = new IsConditionFilter('owner_uuid', Auth::user()->uuid);
//        foreach ($filters as $key => $filter) {
//            $filter->addCondition($ownedCondition);
//            $filters[$key] = $filter;
//        }

        return $filters;
    }
}
