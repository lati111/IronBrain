<?php

namespace App\Dataproviders\Traits;

use App\Dataproviders\Interfaces\FilterableDataproviderInterface;
use App\Enum\Component\Dataprovider\FilterlistMessagesEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use LogicException;

trait Filterable
{
    public function __construct() {
        if (!in_array(FilterableDataproviderInterface::class, class_implements($this, false))) {
            throw new LogicException(sprintf("Class '%s' does not have interface '%s'", parent::class, FilterableDataproviderInterface::class));
        }
    }

    public function filters(Request $request) {
        $validator = Validator::make($request->all(), [
            'filter' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        $filters = $this->getFilterList();
        if ($request->get('filter') === null) {
            return response()->json(array_keys($filters), Response::HTTP_OK);
        }

        if (isset($filters[$request->get('filter')]) === false) {
            return response()->json(FilterlistMessagesEnum::FILTER_NOT_AVAILABE, Response::HTTP_BAD_REQUEST);
        }

        $data = $filters[$request->get('filter')]->getJson();
        return response()->json($data, Response::HTTP_OK);
    }

    protected function applyTableFilters(Request $request, $builder, bool $pagination = true) {
        $builder = parent::applyTableFilters($request, $builder, $pagination);
        if ($request->get('filters', '[]') === '[]') {
            return $builder;
        }

        $filters = json_decode($request->get('filters'), true);
        $validator = Validator::make($filters, [
            '*.filter' => 'required|string',
            '*.operator' => 'required|string',
            '*.value' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), Response::HTTP_BAD_REQUEST);
        }

        $filterlist = $this->getFilterList();
        foreach($filters as $filterdata) {
            if (array_key_exists($filterdata['filter'], $filterlist) === false) {
                return response()->json(FilterlistMessagesEnum::FILTER_NOT_AVAILABE, Response::HTTP_BAD_REQUEST);
            }

            $filterlist[$filterdata['filter']]->handle($builder, $filterdata['operator'], $filterdata['value']);
        }



        return $builder;
    }
}
