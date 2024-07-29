<?php

namespace App\Http\Dataproviders\Config\Role;

use App\Enum\Auth\RoleEnum;
use App\Enum\GenericStringEnum;
use App\Exceptions\IronBrainDataException;
use App\Http\Api\AbstractApi;
use App\Http\Dataproviders\Traits\HasPages;
use App\Models\Auth\Permission;
use App\Models\Auth\Role;
use App\Models\Auth\RolePermission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Dataprovider;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Lati111\LaravelDataproviders\Traits\Searchable;
use Symfony\Component\HttpFoundation\Response;

class RolePermissionDatatable extends AbstractApi
{
    use Dataprovider, Paginatable, HasPages, Searchable;

    /**
     * Gets the data after being modified by the query parameters
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse The data in JSON format
     */
    public function data(Request $request): JsonResponse
    {
        try {
            $data = $this->getData($request)
                ->orderBy('group')
                ->get();
        } catch (IronBrainDataException $e) {
            return $this->respond($e->getCode(), $e->getMessage(), $e->getData());
        }

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $data);
    }

    /**
     * { @inheritdoc }
     * @throws IronBrainDataException
     */
    protected function getContent(Request $request): Builder
    {
        $role = Role::find($request->route('role_id'));
        if ($role === null) {
            throw new IronBrainDataException(RoleEnum::NOT_FOUND, RoleEnum::NOT_FOUND, Response::HTTP_NOT_FOUND);
        }

        /** @var Builder $query */
        $query = Permission::leftjointable(RolePermission::getTableName(), Permission::getTableName(), 'id', '=', 'permission_id')
            ->selectRaw(sprintf('COALESCE(%s.role_id is not null) as has_permission', RolePermission::getTableName()))
            ->addSelect([
                sprintf('%s.id', Permission::getTableName()),
                sprintf('%s.name', Permission::getTableName()),
                sprintf('%s.description', Permission::getTableName()),
                sprintf('%s.group', Permission::getTableName()),
            ]);

        return $query;
    }

    /** { @inheritdoc } */
    function getSearchFields(): array
    {
        return [
            sprintf('%s.name', Permission::getTableName()),
            sprintf('%s.description', Permission::getTableName()),
            sprintf('%s.group', Permission::getTableName()),
        ];
    }
}

