<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\RespondsWithErrors;
use App\Models\DnD\Restaurant\Restaurant;
use App\Service\DnD\Restaurant\FloorService;
use Illuminate\Http\JsonResponse;

/**
 * The parts of the restaurant's floor managed on its manage page, which respond with the updated floor state
 */
abstract class AbstractFloorApi extends AbstractApi
{
    use RespondsWithErrors;

    public function __construct(protected readonly FloorService $floorService)
    {
    }

    /**
     * Responds with the updated floor state of the restaurant
     * @param int $status The status code
     * @param string $message The message
     * @param Restaurant $restaurant The restaurant
     * @param array $data Anything to respond with ahead of the floor state, such as the uuid of what was created
     * @return JsonResponse The data and the floor state in JSON format
     */
    protected function respondWithFloor(int $status, string $message, Restaurant $restaurant, array $data = []): JsonResponse
    {
        return $this->respond($status, $message, $data + ['floor' => $this->floorService->getFloor($restaurant)]);
    }
}
