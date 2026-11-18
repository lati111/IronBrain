<?php

namespace App\Http\Dataproviders;

use App\Enum\GenericStringEnum;
use App\Http\Api\AbstractApi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lati111\LaravelDataproviders\Traits\Paginatable;
use Symfony\Component\HttpFoundation\Response;

abstract class AbstractDatalist extends AbstractApi
{
    protected function getToken(Request $request): string {
        $request->session()->token();
        return csrf_token();
    }

    protected function applyTableFilters(Request $request, Builder|HasMany $builder, bool $pagination = true): Builder|HasMany|JsonResponse
    {
        return $builder;
    }

    /**
     * Responds with the items wrapped in the datalist schema v3 envelope. Paginatable dataproviders also
     * include the pagination details, so the datalist doesn't need a separate request to the pages endpoint.
     * Must be called after the items have been retrieved through getData(), as that loads the pagination details.
     * @param Request $request The request parameters as given by Laravel
     * @param iterable $items The items to respond with
     * @return JsonResponse The envelope in JSON format
     */
    protected function respondWithItems(Request $request, iterable $items): JsonResponse
    {
        $envelope = ['items' => $items];

        if (in_array(Paginatable::class, class_uses_recursive($this), true)) {
            $envelope['pagination'] = [
                'page' => $this->page,
                'perpage' => $this->perPage,
                'pages' => (int) $this->getPages($request),
            ];
        }

        return $this->respond(Response::HTTP_OK, GenericStringEnum::DATA_RETRIEVED, $envelope);
    }
}
