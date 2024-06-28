<?php
namespace App\Http\Dataproviders\Cardlists\Config;

use App\Http\Dataproviders\Cardlists\AbstractCardlist;
use App\Models\Config\Project;
use App\Service\TimeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;

class ProjectOverviewCardlist extends AbstractCardlist
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
            ->get()
            ->map(function (Project $module) {
                $module['route'] = route($module['route']);
                $module['thumbnail'] = asset('img/project/thumbnail/'.$module['thumbnail']);
                $module['time_ago'] = TimeService::time_elapsed_string($module->updated_at);
                return $module;
            });

        return response()->json($data, 200);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request): Builder
    {
        /** @var Builder $modules */
        $modules = Project::select()
            ->where('in_overview', true)
            ->where('active', true)
            ->orderBy('updated_at', 'desc');

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
        return ['name', 'description'];
    }
}
