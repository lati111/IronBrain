<?php
namespace App\Http\Dataproviders\Cardlists\Config;

use App\Enum\GenericStringEnum;
use App\Http\Dataproviders\Cardlists\AbstractCardlist;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\Config\Module;
use App\Service\TimeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Symfony\Component\HttpFoundation\Response;

class ModuleOverviewCardlist extends AbstractCardlist
{
    use Dataprovider, Paginatable, HasPages, Searchable;

    /**
     * Gets the data after being modified by the query parameters
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse The data in JSON format
     */
    public function data(Request $request): JsonResponse
    {
        $data = $this->getData($request)
            ->get()
            ->map(function (Module $module) {
                $module['route'] = route($module['route']);
                $module['thumbnail'] = asset('img/project/thumbnail/'.$module['thumbnail']);
                $module['time_ago'] = TimeService::time_elapsed_string($module->updated_at);
                return $module;
            });

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $data);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request): Builder
    {
        /** @var Builder $modules */
        $modules = Module::select()
            ->where('in_overview', true)
            ->where('active', true)
            ->orderBy('updated_at', 'desc');

        return $modules;
    }

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return ['name', 'description'];
    }
}
