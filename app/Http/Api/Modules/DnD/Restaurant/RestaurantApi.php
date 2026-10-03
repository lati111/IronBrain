<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RestaurantApi extends AbstractApi
{
    use ResolvesRestaurant;

    /** @var string[] The currency fields of a restaurant */
    private const array CURRENCY_FIELDS = ['gold', 'silver', 'copper', 'bits'];

    /**
     * Creates a new restaurant owned by the current user
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse The uuid of the created restaurant in JSON format
     */
    public function createRestaurant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'required|string|max:64',
            'date'         => 'required|date_format:Y-m-d',
            'opening_time' => 'required|date_format:H:i',
            'closing_time' => 'required|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = new Restaurant();
        $restaurant->owner_uuid   = Auth::user()->uuid;
        $restaurant->name         = $request->get('name');
        $restaurant->date         = $request->get('date');
        $restaurant->opening_time = $request->get('opening_time');
        $restaurant->closing_time = $request->get('closing_time');
        $restaurant->save();

        return $this->respond(Response::HTTP_CREATED, 'Restaurant created', $restaurant->uuid);
    }

    /**
     * Updates the details of a restaurant. Any field that is left out keeps its current value
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated details of the restaurant in JSON format
     */
    public function editRestaurant(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), array_merge([
            'name'         => 'nullable|string|max:64',
            'date'         => 'nullable|date_format:Y-m-d',
            'opening_time' => 'nullable|date_format:H:i',
            'closing_time' => 'nullable|date_format:H:i',
        ], array_fill_keys(self::CURRENCY_FIELDS, 'nullable|integer|min:0')));

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        // Unlike the currency, these can't be emptied, so an empty value counts as left out
        foreach (['name', 'date', 'opening_time', 'closing_time'] as $field) {
            if ($request->filled($field)) {
                $restaurant->{$field} = $request->get($field);
            }
        }
        foreach (self::CURRENCY_FIELDS as $field) {
            $restaurant->{$field} = $request->has($field) ? (int) $request->get($field) : $restaurant->{$field};
        }
        $restaurant->save();

        return $this->respond(Response::HTTP_OK, 'Restaurant updated', $restaurant->fresh()->getDetails());
    }
}
