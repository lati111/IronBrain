<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\RestaurantUser;
use App\Service\DnD\Restaurant\FloorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The players of a restaurant: users invited to control the employees assigned to them during service
 */
class PlayerApi extends AbstractApi
{
    use ResolvesRestaurant;

    public function __construct(private readonly FloorService $floorService)
    {
    }

    /**
     * Invites a user to play in the restaurant by their username, giving them access as a player straight away
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated floor state in JSON format
     */
    public function invitePlayer(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $user = User::where('username', trim($request->get('username')))->first();
        if ($user === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'User not found');
        }

        try {
            $this->floorService->invitePlayer($restaurant, $user);
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->respond(Response::HTTP_CREATED, $user->username . ' was invited', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }

    /**
     * Removes a player from the restaurant. Their employees are left to the owner and managers
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $user_uuid The uuid of the player
     * @return JsonResponse The updated floor state in JSON format
     */
    public function removePlayer(string $restaurant_uuid, string $user_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $access = RestaurantUser::where('restaurant_uuid', $restaurant->uuid)->where('user_uuid', $user_uuid)
            ->where('role', RestaurantUser::ROLE_PLAYER)->first();
        if ($access === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Player not found');
        }

        $this->floorService->removePlayer($restaurant, $access);

        return $this->respond(Response::HTTP_OK, 'Player removed', [
            'floor' => $this->floorService->getFloor($restaurant),
        ]);
    }
}
