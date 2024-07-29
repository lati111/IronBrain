<?php

namespace App\Http\Dataproviders\Config\Role;

use App\Enum\GenericStringEnum;
use App\Http\Api\AbstractApi;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\Auth\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Symfony\Component\HttpFoundation\Response;

class RoleOverviewDatatable extends AbstractApi
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
            ->get();

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $data);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request): Builder
    {
        /** @var Builder $query */
        $query = Role::select();

        return $query;
    }

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return [
            'name',
            'description'
        ];
    }
}

