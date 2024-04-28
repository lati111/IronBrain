<?php
namespace App\Dataproviders\SelectorLists\Modules\PKSanc;

use App\Dataproviders\Cardlists\AbstractCardlist;
use App\Models\Config\Project;
use App\Models\PKSanc\Game;
use App\Service\TimeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;

class GameDataSelect extends AbstractCardlist
{
    use Dataprovider, Searchable;

    /**
     * Gets the data after being modified by the query parameters
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse The data in JSON format
     */
    public function data(Request $request): JsonResponse
    {
        $data = $this->getData($request)
            ->get()
            ->map(function (Game $game) {
                if ($game->is_romhack) {
                    $game['name'] .= ' (romhack)';
                }

                return $game;
            });

        return response()->json($data, 200);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request): Builder
    {
        /** @var Builder $modules */
        $modules = Game::select();

        return $modules;
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
        return ['name'];
    }
}
