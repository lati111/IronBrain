<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\RespondsWithErrors;
use App\Models\DnD\Restaurant\Restaurant;
use App\Service\DnD\Restaurant\ServiceStateService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The actions taken during service, which respond with the updated service state so the service page can redraw
 */
abstract class AbstractServiceApi extends AbstractApi
{
    use RespondsWithErrors;

    public function __construct(protected readonly ServiceStateService $serviceStateService)
    {
    }

    /**
     * Gets the full service state: the kitchen, its staff and work areas, the orders being served, and who is looking
     * @param Restaurant $restaurant The restaurant
     * @return array The service state
     */
    protected function getState(Restaurant $restaurant): array
    {
        $user = Auth::user();

        return $this->serviceStateService->getState($restaurant)
            // Who is looking: players only control the employees assigned to them
            + ['viewer' => ['user_uuid' => $user?->uuid, 'player' => $user !== null && $restaurant->isPlayer($user)]];
    }

    /**
     * Performs a kitchen action, responding with the updated service state, or with the reason it isn't possible
     * @param Restaurant $restaurant The restaurant
     * @param string $message The message on success
     * @param Closure $action The action. May return a response to respond with instead
     * @param int $status The status code on success
     * @return JsonResponse The updated service state, or an error, in JSON format
     */
    protected function attempt(Restaurant $restaurant, string $message, Closure $action, int $status = Response::HTTP_OK): JsonResponse
    {
        try {
            $result = $action();
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return $this->respond($status, $message, $this->getState($restaurant->fresh()));
    }
}
