<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\ErrorEnum;
use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Storage;
use App\Service\DnD\Restaurant\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage as FileStorage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class InventoryApi extends AbstractApi
{
    use ResolvesRestaurant;

    /** @var string The folder storage background images are saved in */
    private const string STORAGE_IMAGE_PATH = 'modules/dnd/restaurant/storage';
    /** @var string The folder ingredient icons are saved in */
    private const string INGREDIENT_ICON_PATH = 'modules/dnd/restaurant/ingredient';

    public function __construct(private readonly InventoryService $inventoryService)
    {
    }

    /**
     * Gets the full inventory state of a restaurant
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The inventory state in JSON format
     */
    public function getInventory(string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        return $this->respond(Response::HTTP_OK, 'Inventory retrieved', $this->inventoryService->getInventory($restaurant));
    }

    /**
     * Moves an amount of an ingredient or equipment between storage spaces and the unsaved general storage
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function transferItem(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type'              => ['required', 'string', Rule::in([InventoryService::TYPE_INGREDIENT, InventoryService::TYPE_EQUIPMENT])],
            'item_uuid'         => 'required|string|max:255',
            // Ingredients can be moved in fractions, while equipment is checked to be whole when moving it
            'amount'            => 'required|numeric|gt:0|max:999999',
            'from_storage_uuid' => 'nullable|string|max:255',
            'to_storage_uuid'   => 'nullable|string|max:255|different:from_storage_uuid',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $fromUuid = $request->get('from_storage_uuid');
        $toUuid = $request->get('to_storage_uuid');
        if ($fromUuid === null && $toUuid === null) {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'A storage to move from or to is required');
        }

        $model = $request->get('type') === InventoryService::TYPE_INGREDIENT ? Ingredient::class : Equipment::class;
        $item = $model::where('uuid', $request->get('item_uuid'))
            ->where('restaurant_uuid', $restaurant->uuid)
            ->first();

        if ($item === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
        }

        $from = $fromUuid !== null ? $this->getStorage($restaurant, $fromUuid) : null;
        $to = $toUuid !== null ? $this->getStorage($restaurant, $toUuid) : null;
        if (($fromUuid !== null && $from === null) || ($toUuid !== null && $to === null)) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Storage not found');
        }

        try {
            $this->inventoryService->transfer($item, (float) $request->get('amount'), $from, $to);
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->respond(Response::HTTP_OK, 'Item moved', [
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Creates a new storage space at the end of the restaurant's storage spaces
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function createStorage(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'                  => 'required|string|max:64',
            'capacity'              => 'required|integer|min:1',
            'expiration_multiplier' => 'required|numeric|min:0',
            'background_image'      => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
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

        return $this->respond(Response::HTTP_CREATED, 'Storage space created', [
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Saves a new order for the restaurant's storage spaces
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function reorderStorages(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order'   => 'required|array',
            'order.*' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
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

        return $this->respond(Response::HTTP_OK, 'Storage spaces reordered', [
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
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
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $storage = $this->getStorage($restaurant, $storage_uuid);
        if ($storage === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Storage not found');
        }

        $released = $this->inventoryService->getContents($storage);
        $image = $storage->background_image;

        $storage->delete();
        if ($image !== null) {
            FileStorage::delete($image);
        }

        return $this->respond(Response::HTTP_OK, 'Storage space deleted', [
            'released' => $released,
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Creates a new ingredient type for the restaurant
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the ingredient and the updated inventory state in JSON format
     */
    public function createIngredient(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|string|max:64',
            'expires_in' => 'required|integer|min:0',
            'size'       => 'required|numeric|gt:0|max:999999',
            'icon'       => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $ingredient = new Ingredient();
        $ingredient->restaurant_uuid = $restaurant->uuid;
        $ingredient->name            = $request->get('name');
        $ingredient->expires_in      = (int) $request->get('expires_in');
        $ingredient->size            = round((float) $request->get('size'), 2);

        if ($request->hasFile('icon')) {
            $ingredient->icon = FileStorage::putFile(self::INGREDIENT_ICON_PATH, $request->file('icon'));
        }

        $ingredient->save();

        return $this->respond(Response::HTTP_CREATED, 'Ingredient created', [
            'uuid' => $ingredient->uuid,
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Creates a new equipment type for the restaurant
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the equipment and the updated inventory state in JSON format
     */
    public function createEquipment(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'             => 'required|string|max:64',
            'description'      => 'nullable|string',
            'category'         => ['required', 'string', Rule::in(EquipmentCategory::values())],
            'cleaning_time'    => 'required|integer|min:0',
            'multiplier'       => 'required|numeric|min:0',
            'size'             => 'required|integer|min:1',
            'parent_uuid'      => 'nullable|string|max:255',
            'requires_storage' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $parentUuid = $request->get('parent_uuid');
        if ($parentUuid !== null && Equipment::where('uuid', $parentUuid)->where('restaurant_uuid', $restaurant->uuid)->exists() === false) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Parent equipment not found');
        }

        $equipment = new Equipment();
        $equipment->restaurant_uuid  = $restaurant->uuid;
        $equipment->parent_uuid      = $parentUuid;
        $equipment->name             = $request->get('name');
        $equipment->description      = $request->get('description');
        $equipment->category         = EquipmentCategory::from($request->get('category'));
        $equipment->cleaning_time    = (int) $request->get('cleaning_time');
        $equipment->multiplier       = (float) $request->get('multiplier');
        $equipment->size             = (int) $request->get('size');
        // Equipment needs storage unless explicitly said otherwise
        $equipment->requires_storage = $request->has('requires_storage') ? $request->boolean('requires_storage') : true;
        $equipment->save();

        return $this->respond(Response::HTTP_CREATED, 'Equipment created', [
            'uuid' => $equipment->uuid,
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Updates an existing ingredient type for the restaurant. Any field that is left out keeps its current value
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $ingredient_uuid The uuid of the ingredient
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function editIngredient(Request $request, string $restaurant_uuid, string $ingredient_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'nullable|string|max:64',
            'expires_in' => 'nullable|integer|min:0',
            'size'       => 'nullable|numeric|gt:0|max:999999',
            'icon'       => 'nullable|image|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $ingredient = Ingredient::where('uuid', $ingredient_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($ingredient === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Ingredient not found');
        }

        $ingredient->name       = $request->get('name', $ingredient->name);
        $ingredient->expires_in = $request->has('expires_in') ? (int) $request->get('expires_in') : $ingredient->expires_in;
        $ingredient->size       = $request->has('size') ? round((float) $request->get('size'), 2) : $ingredient->size;

        if ($request->hasFile('icon')) {
            $oldIcon = $ingredient->icon;
            $ingredient->icon = FileStorage::putFile(self::INGREDIENT_ICON_PATH, $request->file('icon'));
            if ($oldIcon !== null) {
                FileStorage::delete($oldIcon);
            }
        }

        $ingredient->save();

        return $this->respond(Response::HTTP_OK, 'Ingredient updated', [
            'uuid' => $ingredient->uuid,
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Updates an existing equipment type for the restaurant. Any field that is left out keeps its current value
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $equipment_uuid The uuid of the equipment
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function editEquipment(Request $request, string $restaurant_uuid, string $equipment_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'             => 'nullable|string|max:64',
            'description'      => 'nullable|string',
            'category'         => ['nullable', 'string', Rule::in(EquipmentCategory::values())],
            'cleaning_time'    => 'nullable|integer|min:0',
            'multiplier'       => 'nullable|numeric|min:0',
            'size'             => 'nullable|integer|min:1',
            'parent_uuid'      => 'nullable|string|max:255',
            'requires_storage' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $equipment = Equipment::where('uuid', $equipment_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($equipment === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Equipment not found');
        }

        if ($request->has('parent_uuid')) {
            $parentUuid = $request->get('parent_uuid');
            if ($parentUuid === $equipment->uuid) {
                return $this->respond(Response::HTTP_BAD_REQUEST, 'Equipment cannot be its own parent');
            }
            if ($parentUuid !== null && Equipment::where('uuid', $parentUuid)->where('restaurant_uuid', $restaurant->uuid)->exists() === false) {
                return $this->respond(Response::HTTP_NOT_FOUND, 'Parent equipment not found');
            }
            $equipment->parent_uuid = $parentUuid;
        }

        if ($request->has('requires_storage')) {
            $requiresStorage = $request->boolean('requires_storage');
            if ($requiresStorage !== $equipment->requires_storage && $this->inventoryService->getTotalStock($equipment) > 0) {
                return $this->respond(Response::HTTP_BAD_REQUEST, 'Remove all stored and owned ' . $equipment->name . ' before changing its storage requirement');
            }
            $equipment->requires_storage = $requiresStorage;
        }

        $equipment->name          = $request->get('name', $equipment->name);
        $equipment->description   = $request->has('description') ? $request->get('description') : $equipment->description;
        $equipment->category      = $request->filled('category') ? EquipmentCategory::from($request->get('category')) : $equipment->category;
        $equipment->cleaning_time = $request->has('cleaning_time') ? (int) $request->get('cleaning_time') : $equipment->cleaning_time;
        $equipment->multiplier    = $request->has('multiplier') ? (float) $request->get('multiplier') : $equipment->multiplier;
        $equipment->size          = $request->has('size') ? (int) $request->get('size') : $equipment->size;
        $equipment->save();

        return $this->respond(Response::HTTP_OK, 'Equipment updated', [
            'uuid' => $equipment->uuid,
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Deletes an ingredient type. It is removed from every storage space and recipe it was part of
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $ingredient_uuid The uuid of the ingredient
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function deleteIngredient(string $restaurant_uuid, string $ingredient_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $ingredient = Ingredient::where('uuid', $ingredient_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($ingredient === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Ingredient not found');
        }

        $icon = $ingredient->icon;
        $ingredient->delete();
        if ($icon !== null) {
            FileStorage::delete($icon);
        }

        return $this->respond(Response::HTTP_OK, 'Ingredient deleted', [
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Deletes an equipment type. It is removed from every storage space and recipe it was part of, and any
     * equipment that was part of it no longer has a parent
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $equipment_uuid The uuid of the equipment
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function deleteEquipment(string $restaurant_uuid, string $equipment_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $equipment = Equipment::where('uuid', $equipment_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($equipment === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Equipment not found');
        }

        $equipment->delete();

        return $this->respond(Response::HTTP_OK, 'Equipment deleted', [
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
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
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $amount = (int) $request->get('amount');
        if ($amount === 0) {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'Amount cannot be zero');
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $equipment = Equipment::where('uuid', $equipment_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($equipment === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Equipment not found');
        }

        try {
            $this->inventoryService->adjustOwnedEquipment($restaurant, $equipment, $amount);
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->respond(Response::HTTP_OK, 'Equipment updated', [
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Gets a storage space belonging to a restaurant
     * @param Restaurant $restaurant The restaurant
     * @param string $uuid The uuid of the storage space
     * @return Storage|null The storage space, or null if it does not belong to the restaurant
     */
    private function getStorage(Restaurant $restaurant, string $uuid): ?Storage
    {
        return Storage::where('uuid', $uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
    }
}
