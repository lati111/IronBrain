<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Boost;
use App\Models\DnD\Restaurant\Restaurant;
use App\Service\DnD\Restaurant\FloorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The boosts a restaurant can activate during service, configured on its manage page
 */
class BoostApi extends AbstractApi
{
    use ResolvesRestaurant;

    public function __construct(private readonly FloorService $floorService)
    {
    }

    /**
     * Creates a boost
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the boost and the updated floor state in JSON format
     */
    public function createBoost(Request $request, string $restaurant_uuid): JsonResponse
    {
        return $this->saveBoost($request, $restaurant_uuid, null);
    }

    /**
     * Updates a boost. Every field is required, as a boost is small enough to always be sent whole. Boosts that are
     * already active keep the bonus they were activated with
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $boost_uuid The uuid of the boost
     * @return JsonResponse The uuid of the boost and the updated floor state in JSON format
     */
    public function editBoost(Request $request, string $restaurant_uuid, string $boost_uuid): JsonResponse
    {
        return $this->saveBoost($request, $restaurant_uuid, $boost_uuid);
    }

    /**
     * Deletes a boost. Boosts already active from it last until they run out
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $boost_uuid The uuid of the boost
     * @return JsonResponse The updated floor state in JSON format
     */
    public function deleteBoost(string $restaurant_uuid, string $boost_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $boost = $this->getBoost($restaurant, $boost_uuid);
        if ($boost === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Boost not found');
        }

        $boost->delete();

        return $this->respond(Response::HTTP_OK, 'Boost deleted', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    /**
     * Creates or updates a boost from the request. Only meal boosts improve quality, and a speed bonus needs a
     * duration to last for
     * @param string|null $boost_uuid The uuid of the boost to update, or null to create one
     */
    private function saveBoost(Request $request, string $restaurant_uuid, ?string $boost_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'          => 'required|string|max:64',
            'target'        => ['required', 'string', Rule::in(Boost::TARGETS)],
            'speed_bonus'   => 'required|numeric|min:0|max:100',
            'minutes_off'   => 'required|numeric|min:0|max:99999',
            'quality_bonus' => 'nullable|integer|min:-9999|max:9999',
            // In minutes of the restaurant's time, at most a week
            'duration'      => 'required|integer|min:0|max:10080',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        if ((float) $request->get('speed_bonus') > 0 && (int) $request->get('duration') === 0) {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'A speed bonus needs a duration');
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $boost = $boost_uuid !== null ? $this->getBoost($restaurant, $boost_uuid) : new Boost();
        if ($boost === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Boost not found');
        }

        $boost->restaurant_uuid = $restaurant->uuid;
        $boost->name = trim($request->get('name'));
        $boost->target = $request->get('target');
        $boost->speed_bonus = round((float) $request->get('speed_bonus'), 2);
        $boost->minutes_off = round((float) $request->get('minutes_off'), 2);
        $boost->quality_bonus = $boost->target === Boost::TARGET_MEAL ? (int) $request->get('quality_bonus', 0) : 0;
        $boost->duration = (int) $request->get('duration');
        $boost->save();

        return $this->respond($boost_uuid === null ? Response::HTTP_CREATED : Response::HTTP_OK,
            $boost_uuid === null ? 'Boost created' : 'Boost updated', [
                'uuid' => $boost->uuid,
                'floor' => $this->floorService->getFloor($restaurant),
            ]);
    }

    /**
     * Gets a boost belonging to a restaurant
     * @return Boost|null The boost, or null if it does not belong to the restaurant
     */
    private function getBoost(Restaurant $restaurant, string $uuid): ?Boost
    {
        return Boost::where('uuid', $uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
    }
}
