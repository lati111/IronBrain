<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class RestaurantApi extends AbstractApi
{
    /**
     * Creates a new restaurant owned by the current user
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse The uuid of the created restaurant in JSON format
     */
    public function createRestaurant(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = new Restaurant();
        $restaurant->owner_uuid = Auth::user()->uuid;
        $restaurant->name       = $request->get('name');
        $restaurant->save();

        return $this->respond(Response::HTTP_CREATED, 'Restaurant created', $restaurant->uuid);
    }
}
