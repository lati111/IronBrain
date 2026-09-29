<?php

namespace App\Http\Dataproviders\Config\User;

use App\Http\Dataproviders\AbstractDatalist;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\Auth\Role;
use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Lati111\LaravelDataproviders\Traits\Sortable;

class UserOverviewDatatable extends AbstractDatalist
{
    use Dataprovider, Paginatable, HasPages, Searchable, Sortable;

    function __construct() {
        $this->columnAliases = [
            'role' => 'role_id'
        ];
    }

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
        $query = User::select([
            sprintf('%s.uuid', User::getTableName()),
            sprintf('%s.profile_picture', User::getTableName()),
            sprintf('%s.username', User::getTableName()),
            sprintf('%s.email', User::getTableName()),
            sprintf('%s.name as role', Role::getTableName()),

        ])->jointable(Role::getTableName(), User::getTableName(), 'role_id', '=', 'id');

        return $query;
    }

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return [
            sprintf('%s.username', User::getTableName()),
            'email'
        ];
    }

    /** { @inheritdoc } */
    function getAllowedSortColumns(): array
    {
        return [
            'username',
            'email',
            'role_id'
        ];
    }
}

