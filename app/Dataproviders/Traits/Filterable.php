<?php

namespace App\Dataproviders\Traits;

use App\Dataproviders\Interfaces\FilterableDataproviderInterface;
use App\Enum\Component\Dataprovider\FilterlistMessagesEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use LogicException;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

trait Filterable
{
    public function filters(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'filter' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), ResponseAlias::HTTP_BAD_REQUEST);
        }

        $filters = $this->getFilterList();
        if ($request->get('filter') === null) {
            return response()->json(array_keys($filters), ResponseAlias::HTTP_OK);
        }

        if (isset($filters[$request->get('filter')]) === false) {
            return response()->json(FilterlistMessagesEnum::FILTER_NOT_AVAILABE, ResponseAlias::HTTP_BAD_REQUEST);
        }

        $data = $filters[$request->get('filter')]->getJson();
        return response()->json($data, ResponseAlias::HTTP_OK);
    }

    protected function applyFilters(Request $request, Builder|HasMany $builder): Builder|HasMany|JsonResponse
    {
        if ($request->get('filters', '[]') === '[]') {
            return $builder;
        }

        $filters = json_decode($request->get('filters'), true);
        $validator = Validator::make($filters, [
            '*.filter' => 'required|string',
            '*.operator' => 'required|string',
            '*.value' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), ResponseAlias::HTTP_BAD_REQUEST);
        }

        $filterlist = $this->getFilterList();
        foreach($filters as $filterdata) {
            if (array_key_exists($filterdata['filter'], $filterlist) === false) {
                return response()->json(FilterlistMessagesEnum::FILTER_NOT_AVAILABE, ResponseAlias::HTTP_BAD_REQUEST);
            }

            $filterlist[$filterdata['filter']]->handle($builder, $filterdata['operator'], $filterdata['value']);
        }

        return $builder;
    }
}
