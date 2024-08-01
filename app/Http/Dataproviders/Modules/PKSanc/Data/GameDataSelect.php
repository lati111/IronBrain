<?php
namespace App\Http\Dataproviders\Modules\PKSanc\Data;

use App\Enum\GenericStringEnum;
use App\Http\Dataproviders\AbstractCardlist;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\PKSanc\Game;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Symfony\Component\HttpFoundation\Response;

class GameDataSelect extends AbstractCardlist
{
    use Dataprovider, Searchable, HasPages;

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

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $data);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request): Builder
    {
        /** @var Builder $modules */
        $modules = Game::select();

        return $modules;
    }

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return ['name'];
    }
}
