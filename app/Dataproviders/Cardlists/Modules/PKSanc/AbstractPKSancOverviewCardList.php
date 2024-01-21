<?php
namespace App\Dataproviders\Cardlists\Modules\PKSanc;

use App\Dataproviders\Cardlists\AbstractCardlist;
use App\Dataproviders\Filters\AbstractFilter;
use App\Dataproviders\Filters\BoolFilter;
use App\Dataproviders\Filters\Conditions\IsConditionFilter;
use App\Dataproviders\Filters\CustomColumn;
use App\Dataproviders\Filters\DateFilter;
use App\Dataproviders\Filters\ForeignData;
use App\Dataproviders\Filters\NumberFilter;
use App\Dataproviders\Filters\PKSanc\PokemonTypeSelectFilter;
use App\Dataproviders\Filters\SelectFilter;
use App\Dataproviders\Interfaces\FilterableDataproviderInterface;
use App\Dataproviders\Traits\Filterable;
use App\Dataproviders\Traits\Paginatable;
use App\Dataproviders\Traits\Searchable;
use App\Models\PKSanc\Ability;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Nature;
use App\Models\PKSanc\Origin;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Type;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

abstract class AbstractPKSancOverviewCardList extends AbstractCardlist implements FilterableDataproviderInterface
{
    use Filterable, Searchable, Paginatable;

    /** @var array $searchterms Columns that should be searched for in a query */
    protected array $searchterms = ['pksanc__stored_pokemon.nickname', 'pksanc__stored_pokemon.pokemon'];

    /**
     * Applies filters to the search
     * @param Request $request
     * @param Builder|HasMany $builder
     * @param bool $pagination
     * @return Builder|HasMany|JsonResponse Returns the query or a json response
     */
    protected function applyTableFilters(Request $request, Builder|HasMany $builder, bool $pagination = true): Builder|HasMany|JsonResponse
    {
        $builder = $this->applySearch($request, $builder, $this->searchterms);

        $builder = $this->applyFilters($request, $builder);
        if ($builder instanceof JsonResponse) {
            return $builder;
        }

        $this->setPerPage(12);
        if ($pagination === true) {
            $builder = $this->applyPagination($request, $builder);
            if ($builder instanceof JsonResponse) {
                return $builder;
            }
        }

        return parent::applyTableFilters($request, $builder);
    }

    /**
     * Returns possible filters
     * @return array<AbstractFilter> Returns an array of filter objects
     */
    public function getFilterList(): array {
        $filters = [];

        $filter = new SelectFilter(new StoredPokemon, 'species_name',
            new ForeignData(StoredPokemon::class, 'pokemon', Pokemon::class, 'pokemon'));
        $filters['species'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'gender');
        $filters['gender'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignData(StoredPokemon::class, 'nature', Nature::class, 'nature'));
        $filters['nature'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignData(StoredPokemon::class, 'ability', Ability::class, 'ability'));
        $filters['ability'] = $filter;

        $filter = new PokemonTypeSelectFilter();
        $filters['type'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignData(StoredPokemon::class, 'tera_type', Type::class, 'type'));
        $filters['tera_type'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignData(StoredPokemon::class, 'hidden_power_type', Type::class, 'type'));
        $filters['hidden_power'] = $filter;

        $filter = new NumberFilter(new StoredPokemon, 'level');
        $filters['level'] = $filter;

        $filter = new NumberFilter(new StoredPokemon, 'friendship');
        $filters['friendship'] = $filter;

        $gameTable = app(Game::class)->getTable();
        $csvTable = app(ImportCsv::class)->getTable();
        $selector = new CustomColumn(sprintf("concat(%s.name, ' - ', %s.name)", $gameTable, $csvTable), 'save_file');
        $filter = new SelectFilter(new StoredPokemon, $selector,
            new ForeignData(StoredPokemon::class, 'csv_uuid', ImportCsv::class, 'uuid',
                new ForeignData(ImportCsv::class, 'game', Game::class, 'game')
            )
        );
        $filters['save_file'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignData(StoredPokemon::class, 'uuid', Origin::class, 'pokemon_uuid',
                new ForeignData(Origin::class, 'game', Game::class, 'game',)
            )
        );
        $filters['game'] = $filter;

        $filter = new DateFilter(new StoredPokemon, 'met_date',
            new ForeignData(StoredPokemon::class, 'uuid', Origin::class, 'pokemon_uuid')
        );
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

        $filter = new NumberFilter(new StoredPokemon, 'generation',
            new ForeignData(StoredPokemon::class, 'pokemon', Pokemon::class, 'pokemon'));
        $filters['generation'] = $filter;

        $ownedCondition = new IsConditionFilter('owner_uuid', Auth::user()->uuid);
        foreach ($filters as $key => $filter) {
            $filter->addCondition($ownedCondition);
            $filters[$key] = $filter;
        }

        return $filters;
    }

    /**
     * Generates the html array for a card from a pokemon
     * @param StoredPokemon $pokemon
     * @return array<string> Returns an array html blocks to put inside a card
     */
    protected function getCard(StoredPokemon $pokemon): array {
        $iconList = $this->getIconList($pokemon);

        $spriteBlock = view('modules.pksanc.snippits.minimal_sprite_block', [
            'name' => $pokemon->Pokemon()->getName(),
            'speciesName' => ($pokemon->nickname !== null) ? $pokemon->nickname : $pokemon->Pokemon()->species_name,
            'spritePath' => asset('img/modules/pksanc/pokemon/' . $pokemon->getSprite()),
            'spriteName' => $pokemon->Pokemon()->pokemon,
        ])->render();

        $hiddenPower = $pokemon->HiddenPower();
        $infoBlock = view('modules.pksanc.snippits.minimal_info_block', [
            'level' => $pokemon->level,
            'nature' => $pokemon->Nature()->name,
            'ability' => $pokemon->Ability()->name,
            'hiddenPower' => $hiddenPower->name,
            'hiddenPowerIconPath' => asset(sprintf(
                'img/modules/pksanc/icon/type/%s_full.png',
                $hiddenPower->type
            ))
        ])->render();

        $origin = $pokemon->getOrigin();
        $trainer = $origin->getTrainer();
        $gender = 'Male';
        $genderIconPath = asset('img/modules/pksanc/icon/gender/male.png');
        if ($trainer->gender === 'F') {
            $gender = 'Female';
            $genderIconPath = asset('img/modules/pksanc/icon/gender/female.png');
        }

        $trainerBlock = view('modules.pksanc.snippits.minimal_trainer_block', [
            'saveName' => $pokemon->Csv()->name,
            'caughtGame' => $origin->getGame()->name,
            'caughtLocation' => $origin->met_location,
            'trainerName' => $trainer->name,
            'trainer' => $trainer->name,
            'gender' => $gender,
            'genderIconPath' => $genderIconPath,
        ])->render();

        return [
            $iconList,
            $spriteBlock,
            $infoBlock,
            $trainerBlock
        ];
    }

    /**
     * Generates the iconlist block of a card
     * @param StoredPokemon $pokemon
     * @return string Returns the iconlist as plain html
     */
    private function getIconList(StoredPokemon $pokemon): string
    {
        $icons = [];
        $pokeball = $pokemon->Pokeball();
        $icons[] = [
            'src' => asset('img/modules/pksanc/pokeball/' . $pokeball->sprite),
            'alt' => $pokeball->name . ' icon',
            'title' => $pokeball->name . ' icon',
            'height' => 8
        ];

        switch ($pokemon->gender) {
            case 'M':
                $icons[] = [
                    'src' => asset('img/modules/pksanc/icon/gender/male.png'),
                    'alt' => 'male icon',
                    'title' => 'Male',
                    'height' => 6
                ];
                break;
            case 'F':
                $icons[] = [
                    'src' => asset('img/modules/pksanc/icon/gender/female.png'),
                    'alt' => 'female icon',
                    'title' => 'Female',
                    'height' => 6
                ];
                break;
            case '-':
                $icons[] = [
                    'src' => asset('img/modules/pksanc/icon/gender/none.png'),
                    'alt' => 'genderless icon',
                    'title' => 'Genderless',
                    'height' => 6
                ];
                break;
        }

        $teraType = $pokemon->TeraType();
        $icons[] = [
            'src' => asset('img/modules/pksanc/icon/tera/' . $teraType->type . '.png'),
            'alt' => $teraType->type . ' tera type icon',
            'title' => $teraType->name . ' tera type',
            'height' => 8
        ];

        if ($pokemon->is_alpha === true) {
            $icons[] = [
                'src' => asset('img/modules/pksanc/icon/alpha.png'),
                'alt' => 'aplha icon',
                'title' => 'Alpha',
                'height' => 6
            ];
        }

        if ($pokemon->can_gigantamax === true) {
            $icons[] = [
                'src' => asset('img/modules/pksanc/icon/dyna.png'),
                'alt' => 'dynamax icon',
                'title' => 'Dynamax',
                'height' => 6
            ];
        }

        if ($pokemon->has_n_sparkle === true) {
            $icons[] = [
                'src' => asset('img/modules/pksanc/icon/n_sparkle.png'),
                'alt' => 'N sparkle icon',
                'title' => 'N sparkle',
                'height' => 6
            ];
        }

        if ($pokemon->is_shiny === true) {
            $icons[] = [
                'src' => asset('img/modules/pksanc/icon/shiny.png'),
                'alt' => 'shiny icon',
                'title' => 'Shiny',
                'height' => 6
            ];
        }

        return view('modules.pksanc.snippits.icon_block', ['icons' => $icons])->render();
    }

}
