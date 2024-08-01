<?php
namespace App\Http\Dataproviders\Modules\PKSanc\Data;

use App\Enum\GenericStringEnum;
use App\Http\Dataproviders\AbstractCardlist;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\PKSanc\Pokemon;
use App\Models\PKSanc\StoredPokemon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Symfony\Component\HttpFoundation\Response;

class OwnedPokemonSpeciesSelect extends AbstractCardlist
{
    use Dataprovider, Paginatable, Searchable, HasPages;

    /**
     * Gets the data after being modified by the query parameters
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse The data in JSON format
     */
    public function data(Request $request): JsonResponse
    {
        $data = $this->getData($request)
            ->get();

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $data);
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

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return ['species_name'];
    }
}
