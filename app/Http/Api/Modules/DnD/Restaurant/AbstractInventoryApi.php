<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\RespondsWithErrors;
use App\Models\DnD\Restaurant\Restaurant;
use App\Service\DnD\Restaurant\InventoryService;
use App\Service\DnD\Restaurant\InventoryStateService;
use Illuminate\Http\JsonResponse;

/**
 * The parts of the restaurant's inventory managed on its manage page, which respond with the updated inventory state
 */
abstract class AbstractInventoryApi extends AbstractApi
{
    use RespondsWithErrors;

    public function __construct(
        protected readonly InventoryService $inventoryService,
        protected readonly InventoryStateService $inventoryStateService,
    )
    {
    }

    /**
     * Responds with the updated inventory state of the restaurant
     * @param int $status The status code
     * @param string $message The message
     * @param Restaurant $restaurant The restaurant
     * @param array $data Anything to respond with ahead of the inventory state, such as the uuid of what was created
     * @return JsonResponse The data and the inventory state in JSON format
     */
    protected function respondWithInventory(int $status, string $message, Restaurant $restaurant, array $data = []): JsonResponse
    {
        return $this->respond($status, $message, $data + ['inventory' => $this->inventoryStateService->getInventory($restaurant)]);
    }
}
