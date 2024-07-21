<?php

namespace App\Http\Dataproviders\Datatables\Auth;

use App\Enum\GenericStringEnum;
use App\Http\Api\AbstractApi;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Symfony\Component\HttpFoundation\Response;

class UserOverviewDatatable extends AbstractApi
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
        $query = User::select([
            sprintf('%s.uuid', User::getTableName()),
            sprintf('%s.profile_picture', User::getTableName()),
            sprintf('%s.name', User::getTableName()),
            sprintf('%s.email', User::getTableName()),
            sprintf('%s.name as role', Role::getTableName()),

        ])->jointable(Role::getTableName(), User::getTableName(), 'role_id', '=', 'id');

        return $query;
    }

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return [sprintf('%s.name', User::getTableName()), 'email'];
    }
}

