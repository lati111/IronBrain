<?php

namespace App\Http\Dataproviders\Config\Role;

use App\Http\Dataproviders\AbstractDatalist;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\Auth\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;

class RoleDataselect extends AbstractDatalist
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

        return $this->respondWithItems($request, $data);
    }

    /** { @inheritdoc } */
    protected function getContent(Request $request, bool $dataQuery = true): Builder
    {
        /** @var Builder $query */
        $query = Role::select([
            'id',
            'name'
        ]);

        return $query;
    }

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return ['name'];
    }
}

