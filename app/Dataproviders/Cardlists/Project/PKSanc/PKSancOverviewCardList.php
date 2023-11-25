<?php
namespace App\Dataproviders\Cardlists\Project\PKSanc;

use App\Dataproviders\Cardlists\AbstractCardlist;
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
use App\Models\PKSanc\Ability;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use App\Models\PKSanc\Nature;
use App\Models\PKSanc\Origin;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use App\Models\PKSanc\Type;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class PKSancOverviewCardList extends AbstractCardlist implements FilterableDataproviderInterface
{
    use Filterable;

    private const ICON_LIST_HTML = "<div class='flex flex-col justify-center items-center h-full w-8 my-2 gap-2'>%s</div>";

    private const ICON_HTML = "<img src='%s' alt='%s' title='%s' class='h-%s'>";

    private const SPRITE_BLOCK_HTML =
        '<div class="pksanc-minimal-block flex flex-col justify-center gap-0 w-24">'.
            '<span class="text-center" title="species">%s</span>'.
            '<img src="%s" alt="%s sprite" title="sprite" class="w-24 h-24">'.
            '<div class="flex flex-col items-center">'.
                '<span class="text-center" title="name">%s</span>'.
            '</div>'.
        '</div>';

    private const MINIMAL_INFO_BLOCK =
        '<div class="pksanc-minimal-block flex flex-col items-center gap-2">'.
            '<span class="text-center" title="level">Level %s</span>'.
            '<span class="text-center" title="nature">%s</span>'.
            '<span class="text-center" title="ability">%s</span>'.
            '<img src="%s" alt="%s hidden power type" title="hidden power" class="w-28 h-6">'.
        '</div>';

    private const MINIMAL_TRAINER_BLOCK =
        '<div>'.
            '<div class="pksanc-minimal-block flex flex-col items-center gap-2">'.
                '<div class="flex flex-col items-center gap-0">'.
                    '<span class="text-center" title="save file">%s</span>'.
                    '<span class="text-center" title="caught game">%s</span>'.
                '</div>'.
                '<span class="text-center" title="caught location">%s</span>'.
                '<div class="flex flex-row items-center gap-1">'.
                    '<span class="text-center" title="trainer">%s</span>'.
                    '<img src="%s" alt="%s gender icon" title="%s" class="h-5">'.
                '</div>'.
            '</div>'.
        '</div>';

    public function data(Request $request)
    {
        $pokemonCollection = StoredPokemon::select()
            ->where('validated_at', '!=', null)
            ->where('owner_uuid', Auth::user()->uuid);
        $pokemonCollection = $this->applyTableFilters($request, $pokemonCollection);
        if ($pokemonCollection instanceof JsonResponse) {
            return $pokemonCollection;
        }

        $pokemonCollection = $pokemonCollection->get();

        $data = [];
        foreach ($pokemonCollection as $pokemon) {
            $iconList = $this->getIconList($pokemon);

            $spriteBlock = $this->getSpriteBlock(
                $pokemon->getSprite(),
                $pokemon->Pokemon()->getName(),
                $pokemon->Pokemon()->pokemon,
                ($pokemon->nickname !== null) ? $pokemon->nickname : $pokemon->Pokemon()->species_name
            );

            $infoBlock = $this->getMinimalInfoBlock(
                $pokemon->Nature(),
                $pokemon->Ability(),
                $pokemon->HiddenPower(),
                $pokemon->level
            );

            $origin = $pokemon->Origin();
            $trainer = $origin->Trainer();
            $trainerBlock = $this->getMinimalTrainerBlock(
                $pokemon->Csv()->name,
                $origin->Game()->name,
                $origin->met_location,
                $trainer->name,
                $trainer->gender,
            );

            $data[] = [
                $iconList,
                $spriteBlock,
                $infoBlock,
                $trainerBlock
            ];
        }

        return response()->json($data, 200);
    }

    public function count(Request $request) {
        $pokemonCollection = StoredPokemon::select()
            ->where('validated_at', '!=', null)
            ->where('owner_uuid', Auth::user()->uuid);
        $count =  $this->getCount($request, $pokemonCollection, true);

        return response()->json($count, Response::HTTP_OK);
    }

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
            new ForeignData(StoredPokemon::class, 'pokemon', Ability::class, 'ability'));
        $filters['ability'] = $filter;

        $filter = new PokemonTypeSelectFilter();
        $filters['type'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignData(StoredPokemon::class, 'tera_type', Type::class, 'type'));
        $filters['tera_type'] = $filter;

        $filter = new SelectFilter(new StoredPokemon, 'name',
            new ForeignData(StoredPokemon::class, 'tera_type', Type::class, 'type'));
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

    private function getSpriteBlock(string $sprite, string $species_name, string $species, string $name)
    {
        return sprintf(
            self::SPRITE_BLOCK_HTML,
            $species_name,
            asset('img/project/pksanc/pokemon/' . $sprite),
            $species,
            $name
        );
    }

    private function getMinimalInfoBlock(Nature $nature, Ability $ability, Type $hiddenPower, int $level): string
    {
        return sprintf(
            self::MINIMAL_INFO_BLOCK,
            $level,
            $nature->name,
            $ability->name,
            asset(sprintf(
                'img/project/pksanc/icon/type/%s_full.png',
                $hiddenPower->type
            )),
            $hiddenPower->name
        );
    }

    private function getMinimalTrainerBlock(string $saveName, string $game, string $location, string $trainerName, string $trainerGender): string
    {
        $genderSrc = asset('img/project/pksanc/icon/gender/male.png');
        $genderAlt = 'male';
        $genderTitle = 'Male';
        if ($trainerGender === 'F') {
            $genderSrc = asset('img/project/pksanc/icon/gender/female.png');
            $genderAlt = 'female';
            $genderTitle = 'Female';
        }

        return sprintf(
            self::MINIMAL_TRAINER_BLOCK,
            $saveName,
            $game,
            $location,
            $trainerName,
            $genderSrc,
            $genderAlt,
            $genderTitle
        );
    }

    private function getIconList(StoredPokemon $pokemon)
    {
        $pokeball = $pokemon->Pokeball();
        $iconString = $this->getIcon(
            asset('img/project/pksanc/pokeball/' . $pokeball->sprite),
            $pokeball->name . ' icon',
            $pokeball->name,
            8
        );

        switch ($pokemon->gender) {
            case 'M':
                $iconString .= $this->getIcon(
                    asset('img/project/pksanc/icon/gender/male.png'),
                    'male icon',
                    'Male',
                    6
                );
                break;
            case 'F':
                $iconString .= $this->getIcon(
                    asset('img/project/pksanc/icon/gender/female.png'),
                    'female icon',
                    'Female',
                    6
                );
                break;
            case '-':
                $iconString .= $this->getIcon(
                    asset('img/project/pksanc/icon/gender/none.png'),
                    'genderless icon',
                    'Genderless',
                    6
                );
                break;
        }

        $teraType = $pokemon->TeraType();
        $iconString .= $this->getIcon(
            asset('img/project/pksanc/icon/tera/' . $teraType->type . '.png'),
            $teraType->type . ' tera type icon',
            $teraType->name . ' tera type',
            8
        );

        if ($pokemon->is_alpha === 1) {
            $iconString .= $this->getIcon(
                asset('img/project/pksanc/icon/alpha.png'),
                'aplha icon',
                'Alpha',
                6
            );
        }

        if ($pokemon->can_gigantamax === 1) {
            $iconString .= $this->getIcon(
                asset('img/project/pksanc/icon/dyna.png'),
                'dynamax icon',
                'Dynamax',
                6
            );
        }

        if ($pokemon->has_n_sparkle === 1) {
            $iconString .= $this->getIcon(
                asset('img/project/pksanc/icon/n_sparkle.png'),
                'N sparkle icon',
                'N sparkle',
                6
            );
        }

        if ($pokemon->is_shiny === 1) {
            $iconString .= $this->getIcon(
                asset('img/project/pksanc/icon/shiny.png'),
                'shiny icon',
                'Shiny',
                6
            );
        }

        return sprintf(self::ICON_LIST_HTML, $iconString);
    }

    private function getIcon(string $src, string $alt, string $title, int $size): string
    {
        return sprintf(self::ICON_HTML, $src, $alt, $title, intval($size));
    }
}
