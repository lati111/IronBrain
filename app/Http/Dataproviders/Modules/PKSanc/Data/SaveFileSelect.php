<?php
namespace App\Http\Dataproviders\Modules\PKSanc\Data;

use App\Enum\GenericStringEnum;
use App\Http\Dataproviders\AbstractCardlist;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\PKSanc\Game;
use App\Models\PKSanc\ImportCsv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Symfony\Component\HttpFoundation\Response;

class SaveFileSelect extends AbstractCardlist
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
            ->get();

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $data);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request): Builder
    {
        $user = Auth::user();

        /** @var Builder $modules */
        $modules = ImportCsv::jointable(Game::getTableName(), ImportCsv::getTableName(), 'game', '=', 'game')
            ->where('uploader_uuid', $user->uuid)
            ->where('validated', true)
            ->selectRaw(sprintf("CONCAT(%s.name, ' (', %s.name, ')') as display_name", ImportCsv::getTableName(), Game::getTableName()))
            ->addSelect([
                'uuid',
                sprintf('%s.name as save_name', ImportCsv::getTableName()),
                sprintf('%s.name as game_name', Game::getTableName()),
            ]);

        return $modules;
    }

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return ['save_name', 'game_name'];
    }
}
