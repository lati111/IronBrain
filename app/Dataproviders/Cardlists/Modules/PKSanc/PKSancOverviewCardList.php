<?php
namespace App\Dataproviders\Cardlists\Modules\PKSanc;

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

class PKSancOverviewCardList extends AbstractPKSancOverviewCardList
{
    /** {@inheritDoc} */
    public function data(Request $request): JsonResponse
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
            $data[] = $this->getCard($pokemon);
        }

        return response()->json($data, 200);
    }

    /** {@inheritDoc} */
    public function count(Request $request): JsonResponse
    {
        $pokemonCollection = StoredPokemon::select()
            ->where('validated_at', '!=', null)
            ->where('owner_uuid', Auth::user()->uuid);
        $count =  $this->getCount($request, $pokemonCollection, true);

        return response()->json($count, Response::HTTP_OK);
    }
}
