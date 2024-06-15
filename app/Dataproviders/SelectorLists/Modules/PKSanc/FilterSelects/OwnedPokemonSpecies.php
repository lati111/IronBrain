<?php
namespace App\Dataproviders\SelectorLists\Modules\PKSanc\FilterSelects;

use App\Dataproviders\Cardlists\AbstractCardlist;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;

class OwnedPokemonSpecies extends AbstractCardlist
{
    use Dataprovider, Paginatable, Searchable;

    /**
     * Gets the data after being modified by the query parameters
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse The data in JSON format
     */
    public function data(Request $request): JsonResponse
    {
        $data = $this->getData($request)
            ->get();

        return response()->json($data, 200);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request): Builder
    {
        $this->setDefaultPerPage(50);

        $user = Auth::user();

        /** @var Builder $modules */
        $species = StoredPokemon::select(['species', 'species_name'])
            ->where('owner_uuid', $user->uuid)
            ->jointable(Pokemon::getTableName(), StoredPokemon::getTableName(), 'pokemon', '=', 'pokemon')
            ->orderBy(Pokemon::getTableName().'.internal_pokedex_id')
            ->distinct();

        return $species;
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
        return ['species_name'];
    }
}
