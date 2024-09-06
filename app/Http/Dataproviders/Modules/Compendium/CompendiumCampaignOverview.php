<?php
namespace App\Http\Dataproviders\Modules\Compendium;

use App\Enum\GenericStringEnum;
use App\Exceptions\IronBrainException;
use App\Http\Dataproviders\AbstractCardlist;
use App\Http\Dataproviders\Filters\PKSanc\StoredPokemonTypeSelectFilter;
use App\Http\Dataproviders\Interfaces\FilterableDataproviderInterface;
use App\Http\Dataproviders\Traits\HasFilters;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\Compendium\Campaign;
use App\Models\Compendium\Player;
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
use Illuminate\Database\Eloquent\Relations\Relation;
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

class CompendiumCampaignOverview extends AbstractCardlist implements FilterableDataproviderInterface
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
                ->get();
        } catch (IronBrainException $e) {
            return $this->respond($e->getCode(), $e->publicMessage);
        }

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $data);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request): Builder
    {
        /** @var Builder $query */
        $query = Campaign::jointable(Player::getTableName(), Campaign::getTableName(), 'uuid', '=', 'campaign_uuid')
            ->where('user_uuid', Auth::user()->uuid)
            ->select([
                sprintf('%s.*', Campaign::getTableName()),
            ]);

        return $query;
    }

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return ['title'];
    }

    /** { @inheritdoc } */
    public function getFilterList(): array {
        $filters = [];

        return $filters;
    }
}
