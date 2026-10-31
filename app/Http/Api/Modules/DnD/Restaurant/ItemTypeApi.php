<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage as FileStorage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The ingredient and equipment types the items in the restaurant's inventory are of
 */
class ItemTypeApi extends AbstractInventoryApi
{
    use ResolvesRestaurant;

    /** @var string The folder ingredient icons are saved in */
    private const string INGREDIENT_ICON_PATH = 'modules/dnd/restaurant/ingredient';

    //| Ingredients

    /**
     * Creates a new ingredient type for the restaurant
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the ingredient and the updated inventory state in JSON format
     */
    public function createIngredient(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'name'       => 'required|string|max:64',
            'category'   => 'nullable|string|max:64',
            'expires_in' => 'required|integer|min:0',
            'size'       => 'required|numeric|gt:0|max:999999',
            'quality'    => 'nullable|integer|min:-9999|max:9999',
            'parent_uuid' => 'nullable|string|max:255',
            'icon'       => 'nullable|image|max:2048',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $parentUuid = $request->get('parent_uuid');
        if ($this->isMissingParent(Ingredient::class, $restaurant, $parentUuid)) {
            return $this->notFound('Parent ingredient');
        }

        $ingredient = new Ingredient();
        $ingredient->restaurant_uuid = $restaurant->uuid;
        $ingredient->parent_uuid     = $parentUuid;
        $ingredient->name            = $request->get('name');
        $ingredient->category        = $this->normalizeCategory($request->get('category'));
        $ingredient->expires_in      = (int) $request->get('expires_in');
        $ingredient->size            = round((float) $request->get('size'), 2);
        $ingredient->quality         = (int) $request->get('quality', 0);

        if ($request->hasFile('icon')) {
            $ingredient->icon = FileStorage::putFile(self::INGREDIENT_ICON_PATH, $request->file('icon'));
        }

        $ingredient->save();

        return $this->respondWithInventory(Response::HTTP_CREATED, 'Ingredient created', $restaurant, ['uuid' => $ingredient->uuid]);
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
        $invalid = $this->validateRequest($request, [
            'name'       => 'nullable|string|max:64',
            'category'   => 'nullable|string|max:64',
            'expires_in' => 'nullable|integer|min:0',
            'size'       => 'nullable|numeric|gt:0|max:999999',
            'quality'    => 'nullable|integer|min:-9999|max:9999',
            'parent_uuid' => 'nullable|string|max:255',
            'icon'       => 'nullable|image|max:2048',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $ingredient = $this->findInRestaurant(Ingredient::class, $restaurant, $ingredient_uuid);
        if ($ingredient === null) {
            return $this->notFound('Ingredient');
        }

        $ingredient->name       = $request->get('name', $ingredient->name);
        $ingredient->category   = $request->has('category') ? $this->normalizeCategory($request->get('category')) : $ingredient->category;
        $ingredient->expires_in = $request->has('expires_in') ? (int) $request->get('expires_in') : $ingredient->expires_in;
        $ingredient->size       = $request->has('size') ? round((float) $request->get('size'), 2) : $ingredient->size;
        $ingredient->quality    = $request->filled('quality') ? (int) $request->get('quality') : $ingredient->quality;

        if ($request->has('parent_uuid')) {
            $parentUuid = $request->get('parent_uuid');
            if ($parentUuid === $ingredient->uuid) {
                return $this->respond(Response::HTTP_BAD_REQUEST, 'An ingredient cannot be a variant of itself');
            }
            if ($this->isMissingParent(Ingredient::class, $restaurant, $parentUuid)) {
                return $this->notFound('Parent ingredient');
            }
            $ingredient->parent_uuid = $parentUuid;
        }

        if ($request->hasFile('icon')) {
            $oldIcon = $ingredient->icon;
            $ingredient->icon = FileStorage::putFile(self::INGREDIENT_ICON_PATH, $request->file('icon'));
            if ($oldIcon !== null) {
                FileStorage::delete($oldIcon);
            }
        }

        $ingredient->save();

        return $this->respondWithInventory(Response::HTTP_OK, 'Ingredient updated', $restaurant, ['uuid' => $ingredient->uuid]);
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
            return $this->notFound('Restaurant');
        }

        $ingredient = $this->findInRestaurant(Ingredient::class, $restaurant, $ingredient_uuid);
        if ($ingredient === null) {
            return $this->notFound('Ingredient');
        }

        $icon = $ingredient->icon;
        $ingredient->delete();
        if ($icon !== null) {
            FileStorage::delete($icon);
        }

        return $this->respondWithInventory(Response::HTTP_OK, 'Ingredient deleted', $restaurant);
    }

    //| Equipment

    /**
     * Creates a new equipment type for the restaurant
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the equipment and the updated inventory state in JSON format
     */
    public function createEquipment(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'name'             => 'required|string|max:64',
            'description'      => 'nullable|string',
            'category'         => ['required', 'string', Rule::in(EquipmentCategory::values())],
            'cleaning_time'    => 'required|numeric|min:0|max:999999',
            'multiplier'       => 'required|numeric|min:0',
            'size'             => 'required|numeric|gt:0|max:999999',
            'parent_uuid'      => 'nullable|string|max:255',
            'requires_storage' => 'nullable|boolean',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $parentUuid = $request->get('parent_uuid');
        if ($this->isMissingParent(Equipment::class, $restaurant, $parentUuid)) {
            return $this->notFound('Parent equipment');
        }

        $equipment = new Equipment();
        $equipment->restaurant_uuid  = $restaurant->uuid;
        $equipment->parent_uuid      = $parentUuid;
        $equipment->name             = $request->get('name');
        $equipment->description      = $request->get('description');
        $equipment->category         = EquipmentCategory::from($request->get('category'));
        $equipment->cleaning_time    = round((float) $request->get('cleaning_time'), 2);
        $equipment->multiplier       = (float) $request->get('multiplier');
        $equipment->size             = round((float) $request->get('size'), 2);
        // Equipment needs storage unless explicitly said otherwise
        $equipment->requires_storage = $request->has('requires_storage') ? $request->boolean('requires_storage') : true;
        $equipment->save();

        return $this->respondWithInventory(Response::HTTP_CREATED, 'Equipment created', $restaurant, ['uuid' => $equipment->uuid]);
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
        $invalid = $this->validateRequest($request, [
            'name'             => 'nullable|string|max:64',
            'description'      => 'nullable|string',
            'category'         => ['nullable', 'string', Rule::in(EquipmentCategory::values())],
            'cleaning_time'    => 'nullable|numeric|min:0|max:999999',
            'multiplier'       => 'nullable|numeric|min:0',
            'size'             => 'nullable|numeric|gt:0|max:999999',
            'parent_uuid'      => 'nullable|string|max:255',
            'requires_storage' => 'nullable|boolean',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $equipment = $this->findInRestaurant(Equipment::class, $restaurant, $equipment_uuid);
        if ($equipment === null) {
            return $this->notFound('Equipment');
        }

        if ($request->has('parent_uuid')) {
            $parentUuid = $request->get('parent_uuid');
            if ($parentUuid === $equipment->uuid) {
                return $this->respond(Response::HTTP_BAD_REQUEST, 'Equipment cannot be its own parent');
            }
            if ($this->isMissingParent(Equipment::class, $restaurant, $parentUuid)) {
                return $this->notFound('Parent equipment');
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
        $equipment->cleaning_time = $request->has('cleaning_time') ? round((float) $request->get('cleaning_time'), 2) : $equipment->cleaning_time;
        $equipment->multiplier    = $request->has('multiplier') ? (float) $request->get('multiplier') : $equipment->multiplier;
        $equipment->size          = $request->has('size') ? round((float) $request->get('size'), 2) : $equipment->size;
        $equipment->save();

        return $this->respondWithInventory(Response::HTTP_OK, 'Equipment updated', $restaurant, ['uuid' => $equipment->uuid]);
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
            return $this->notFound('Restaurant');
        }

        $equipment = $this->findInRestaurant(Equipment::class, $restaurant, $equipment_uuid);
        if ($equipment === null) {
            return $this->notFound('Equipment');
        }

        $equipment->delete();

        return $this->respondWithInventory(Response::HTTP_OK, 'Equipment deleted', $restaurant);
    }

    //| Helpers

    /**
     * Checks whether a parent was given that the restaurant doesn't have, for a type that is a variant of another
     * @param string $model The model of the type, Ingredient or Equipment
     * @param Restaurant $restaurant The restaurant
     * @param string|null $parentUuid The uuid of the parent, or null for none
     * @return bool Whether the parent was given but not found
     */
    private function isMissingParent(string $model, Restaurant $restaurant, ?string $parentUuid): bool
    {
        return $parentUuid !== null && $this->findInRestaurant($model, $restaurant, $parentUuid) === null;
    }

    /**
     * Tidies up a category as entered, where an empty one means no category
     * @param string|null $category The category as entered
     * @return string|null The category, or null for none
     */
    private function normalizeCategory(?string $category): ?string
    {
        $category = trim($category ?? '');
        return $category === '' ? null : $category;
    }
}
