<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use App\Service\DnD\Restaurant\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage as FileStorage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The restaurant's stock: its storage spaces, moving items between them, the days passing, and equipment owned outright
 */
class InventoryApi extends AbstractInventoryApi
{
    use ResolvesRestaurant;

    /** @var string The folder storage background images are saved in */
    private const string STORAGE_IMAGE_PATH = 'modules/dnd/restaurant/storage';

    /**
     * Gets the full inventory state of a restaurant
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The inventory state in JSON format
     */
    public function getInventory(string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        return $this->respond(Response::HTTP_OK, 'Inventory retrieved', $this->inventoryStateService->getInventory($restaurant));
    }

    //| Stock

    /**
     * Moves an amount of an ingredient or equipment between storage spaces and the unsaved general storage
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function transferItem(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'type'              => ['required', 'string', Rule::in([InventoryService::TYPE_INGREDIENT, InventoryService::TYPE_EQUIPMENT])],
            'item_uuid'         => 'required|string|max:255',
            // Ingredients can be moved in fractions, while equipment is checked to be whole when moving it
            'amount'            => 'required|numeric|gt:0|max:999999',
            'from_storage_uuid' => 'nullable|string|max:255',
            'to_storage_uuid'   => 'nullable|string|max:255|different:from_storage_uuid',
            // The stack of an ingredient to move, or the days left of one coming from general storage
            'stack_uuid'        => 'nullable|string|max:255',
            'days_left'         => 'nullable|numeric|gt:0|max:999999',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $fromUuid = $request->get('from_storage_uuid');
        $toUuid = $request->get('to_storage_uuid');
        if ($fromUuid === null && $toUuid === null) {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'A storage to move from or to is required');
        }

        // Players move ingredients around, but neither add them to the inventory nor take them out of it
        if ($restaurant->isPlayer(Auth::user())
            && ($request->get('type') !== InventoryService::TYPE_INGREDIENT || $fromUuid === null || $toUuid === null)) {
            return $this->respond(Response::HTTP_FORBIDDEN, 'Players can only move ingredients between storage spaces');
        }

        $model = $request->get('type') === InventoryService::TYPE_INGREDIENT ? Ingredient::class : Equipment::class;
        $item = $this->findInRestaurant($model, $restaurant, $request->get('item_uuid'));
        if ($item === null) {
            return $this->notFound('Item');
        }

        $from = $this->getLocation($restaurant, $fromUuid);
        $to = $this->getLocation($restaurant, $toUuid);
        if (($fromUuid !== null && $from === null) || ($toUuid !== null && $to === null)) {
            return $this->notFound('Storage');
        }

        try {
            $daysLeft = $request->filled('days_left') ? (float) $request->get('days_left') : null;
            $this->inventoryService->transfer($item, (float) $request->get('amount'), $from, $to, $request->get('stack_uuid'), $daysLeft);
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->respondWithInventory(Response::HTTP_OK, 'Item moved', $restaurant);
    }

    /**
     * Moves the restaurant's date a day forward, which ages its stored ingredients and throws away those that expire
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The ingredients that expired, and the updated inventory state, in JSON format
     */
    public function advanceDay(string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $expired = $this->inventoryService->advanceDay($restaurant);

        return $this->respondWithInventory(Response::HTTP_OK, 'A day has passed', $restaurant, ['expired' => $expired]);
    }

    /**
     * Adjusts how much of an equipment type a restaurant directly owns. Only applies to equipment that does not
     * require storage, as it is never placed in a storage space
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $equipment_uuid The uuid of the equipment
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function adjustOwnedEquipment(Request $request, string $restaurant_uuid, string $equipment_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'amount' => 'required|integer',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $amount = (int) $request->get('amount');
        if ($amount === 0) {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'Amount cannot be zero');
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $equipment = $this->findInRestaurant(Equipment::class, $restaurant, $equipment_uuid);
        if ($equipment === null) {
            return $this->notFound('Equipment');
        }

        try {
            $this->inventoryService->adjustOwnedEquipment($restaurant, $equipment, $amount);
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->respondWithInventory(Response::HTTP_OK, 'Equipment updated', $restaurant);
    }

    //| Storage spaces

    /**
     * Creates a new storage space at the end of the restaurant's storage spaces
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function createStorage(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'name'                  => 'required|string|max:64',
            'capacity'              => 'required|integer|min:1',
            'expiration_multiplier' => 'required|numeric|min:0',
            'background_image'      => 'nullable|image|max:5120',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $storage = new Storage();
        $storage->restaurant_uuid       = $restaurant->uuid;
        $storage->name                  = $request->get('name');
        $storage->capacity              = (int) $request->get('capacity');
        $storage->expiration_multiplier = (float) $request->get('expiration_multiplier');
        $storage->order                 = (int) Storage::where('restaurant_uuid', $restaurant->uuid)->max('order') + 1;

        if ($request->hasFile('background_image')) {
            $storage->background_image = FileStorage::putFile(self::STORAGE_IMAGE_PATH, $request->file('background_image'));
        }

        $storage->save();

        return $this->respondWithInventory(Response::HTTP_CREATED, 'Storage space created', $restaurant);
    }

    /**
     * Updates a storage space. Any field that is left out keeps its current value. Its capacity can't go below what it
     * holds, and its background image can be replaced or removed
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $storage_uuid The uuid of the storage space
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function editStorage(Request $request, string $restaurant_uuid, string $storage_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'name'                    => 'nullable|string|max:64',
            'capacity'                => 'nullable|integer|min:1',
            'expiration_multiplier'   => 'nullable|numeric|min:0',
            'background_image'        => 'nullable|image|max:5120',
            'remove_background_image' => 'nullable|boolean',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $storage = $this->findInRestaurant(Storage::class, $restaurant, $storage_uuid);
        if ($storage === null) {
            return $this->notFound('Storage');
        }

        // What is stored has to keep fitting, so the space has to be emptied out before it can shrink that far
        if ($request->filled('capacity') && (int) $request->get('capacity') < $storage->getUsedCapacity()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, sprintf('%s holds %s, so its capacity can\'t be lower than that',
                $storage->name, round($storage->getUsedCapacity(), 2) + 0));
        }

        $storage->name                  = $request->get('name') ?? $storage->name;
        $storage->capacity              = $request->filled('capacity') ? (int) $request->get('capacity') : $storage->capacity;
        $storage->expiration_multiplier = $request->filled('expiration_multiplier') ? (float) $request->get('expiration_multiplier') : $storage->expiration_multiplier;

        // A new image replaces the old one, which is also removed when asked to
        $oldImage = $storage->background_image;
        if ($request->hasFile('background_image')) {
            $storage->background_image = FileStorage::putFile(self::STORAGE_IMAGE_PATH, $request->file('background_image'));
        } elseif ($request->boolean('remove_background_image')) {
            $storage->background_image = null;
        }

        $storage->save();
        if ($oldImage !== null && $oldImage !== $storage->background_image) {
            FileStorage::delete($oldImage);
        }

        return $this->respondWithInventory(Response::HTTP_OK, 'Storage space updated', $restaurant);
    }

    /**
     * Saves a new order for the restaurant's storage spaces
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function reorderStorages(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'order'   => 'required|array',
            'order.*' => 'required|string|max:255',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $order = array_values($request->get('order'));
        $existing = Storage::where('restaurant_uuid', $restaurant->uuid)->pluck('uuid')->all();
        if (count($order) !== count(array_unique($order)) || array_diff($existing, $order) !== [] || array_diff($order, $existing) !== []) {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'The order must contain every storage space exactly once');
        }

        DB::transaction(function () use ($order, $restaurant) {
            foreach ($order as $index => $uuid) {
                Storage::where('restaurant_uuid', $restaurant->uuid)->where('uuid', $uuid)->update(['order' => $index]);
            }
        });

        return $this->respondWithInventory(Response::HTTP_OK, 'Storage spaces reordered', $restaurant);
    }

    /**
     * Deletes a storage space. Its contents are returned so they can be placed in the general storage
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $storage_uuid The uuid of the storage space
     * @return JsonResponse The released contents and the updated inventory state in JSON format
     */
    public function deleteStorage(string $restaurant_uuid, string $storage_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $storage = $this->findInRestaurant(Storage::class, $restaurant, $storage_uuid);
        if ($storage === null) {
            return $this->notFound('Storage');
        }

        $released = $this->inventoryStateService->getContents($storage);
        $image = $storage->background_image;

        $storage->delete();
        if ($image !== null) {
            FileStorage::delete($image);
        }

        return $this->respondWithInventory(Response::HTTP_OK, 'Storage space deleted', $restaurant, ['released' => $released]);
    }

    //| Helpers

    /**
     * Gets where items are moved from or to: a storage space, or the pile of ingredients brought in during service
     * @param Restaurant $restaurant The restaurant
     * @param string|null $uuid The uuid of the storage space, PILE for the pile, or null for general storage
     * @return Storage|string|null The storage space or PILE, or null for general storage or when the storage space
     *                             does not belong to the restaurant
     */
    private function getLocation(Restaurant $restaurant, ?string $uuid): Storage|string|null
    {
        if ($uuid === InventoryService::PILE) {
            return InventoryService::PILE;
        }

        return $uuid !== null ? $this->findInRestaurant(Storage::class, $restaurant, $uuid) : null;
    }
}
