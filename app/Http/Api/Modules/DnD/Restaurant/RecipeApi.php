<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Service\DnD\Restaurant\InventoryService;
use App\Service\DnD\Restaurant\RecipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class RecipeApi extends AbstractApi
{
    use ResolvesRestaurant;

    /**
     * @var string[] The whole number fields of a recipe of at least 0: its difficulty, and its durations. Prep time,
     * cooking time and fresh for are in minutes, good for is in hours and expires in is in days
     */
    private const array INTEGER_FIELDS = ['difficulty', 'prep_time', 'cooking_time', 'fresh_for', 'good_for', 'expires_in'];

    /** @var int How many portions a recipe produces when not specified */
    private const int DEFAULT_PORTIONS = 1;

    public function __construct(
        private readonly RecipeService $recipeService,
        private readonly InventoryService $inventoryService,
    ) {
    }

    /**
     * Creates a new recipe for the restaurant, along with the ingredients and equipment it requires and the
     * byproducts it produces
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The uuid of the recipe and the updated inventory state in JSON format
     */
    public function createRecipe(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), array_merge([
            'name'        => 'required|string|max:64',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'portions'    => 'nullable|integer|min:1',
        ], array_fill_keys(self::INTEGER_FIELDS, 'required|integer|min:0'), $this->getComponentRules()));

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $components = $this->resolveComponents($request, $restaurant);
        if ($components instanceof JsonResponse) {
            return $components;
        }

        $recipe = new Recipe();
        $recipe->restaurant_uuid = $restaurant->uuid;
        $recipe->name            = $request->get('name');
        $recipe->description     = $request->get('description');
        $recipe->price           = (float) $request->get('price');
        $recipe->portions        = (int) ($request->get('portions') ?? self::DEFAULT_PORTIONS);
        foreach (self::INTEGER_FIELDS as $field) {
            $recipe->{$field} = (int) $request->get($field);
        }

        $this->saveRecipe($recipe, $components);

        return $this->respond(Response::HTTP_CREATED, 'Recipe created', [
            'uuid' => $recipe->uuid,
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Updates an existing recipe. Any field that is left out keeps its current value, and the ingredients, equipment
     * or byproducts are only replaced when their list is given
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $recipe_uuid The uuid of the recipe
     * @return JsonResponse The uuid of the recipe and the updated inventory state in JSON format
     */
    public function editRecipe(Request $request, string $restaurant_uuid, string $recipe_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), array_merge([
            'name'        => 'nullable|string|max:64',
            'description' => 'nullable|string',
            'price'       => 'nullable|numeric|min:0',
            'portions'    => 'nullable|integer|min:1',
        ], array_fill_keys(self::INTEGER_FIELDS, 'nullable|integer|min:0'), $this->getComponentRules()));

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $recipe = Recipe::where('uuid', $recipe_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($recipe === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Recipe not found');
        }

        $components = $this->resolveComponents($request, $restaurant);
        if ($components instanceof JsonResponse) {
            return $components;
        }

        $recipe->name        = $request->get('name', $recipe->name);
        $recipe->description = $request->has('description') ? $request->get('description') : $recipe->description;
        $recipe->price       = $request->has('price') ? (float) $request->get('price') : $recipe->price;
        $recipe->portions    = $request->has('portions') ? (int) $request->get('portions') : $recipe->portions;
        foreach (self::INTEGER_FIELDS as $field) {
            $recipe->{$field} = $request->has($field) ? (int) $request->get($field) : $recipe->{$field};
        }

        $this->saveRecipe($recipe, $components);

        return $this->respond(Response::HTTP_OK, 'Recipe updated', [
            'uuid' => $recipe->uuid,
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Deletes a recipe, along with its ingredient, equipment and byproduct entries
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $recipe_uuid The uuid of the recipe
     * @return JsonResponse The updated inventory state in JSON format
     */
    public function deleteRecipe(string $restaurant_uuid, string $recipe_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $recipe = Recipe::where('uuid', $recipe_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($recipe === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Recipe not found');
        }

        $recipe->delete();

        return $this->respond(Response::HTTP_OK, 'Recipe deleted', [
            'inventory' => $this->inventoryService->getInventory($restaurant),
        ]);
    }

    /**
     * Gets the validation rules for the component lists of a recipe
     * @return array The validation rules
     */
    private function getComponentRules(): array
    {
        $rules = [];
        foreach ($this->recipeService->getComponentKinds() as $kind) {
            $rules[$kind]               = 'nullable|array';
            $rules[$kind . '.*.uuid']   = 'required|string|max:255|distinct';
            // Ingredients can be used and produced in fractions, while equipment comes in whole pieces
            $rules[$kind . '.*.amount'] = $this->recipeService->getComponentModel($kind) === Equipment::class
                ? 'required|integer|min:1'
                : 'required|numeric|gt:0|max:999999';
        }

        // Equipment is used during a stage of making the recipe, and may hold the meal as a container
        $rules[RecipeService::EQUIPMENT . '.*.stage']     = ['nullable', 'string', Rule::in(EquipmentStage::values())];
        $rules[RecipeService::EQUIPMENT . '.*.container'] = 'nullable|boolean';

        return $rules;
    }

    /**
     * Checks that no equipment used for serving is marked as a container, as serving equipment can't hold a meal
     * @param Request $request The request parameters as given by Laravel
     * @return JsonResponse|null An error response when it is, or null when the equipment is valid
     */
    private function validateContainers(Request $request): ?JsonResponse
    {
        foreach ($request->get(RecipeService::EQUIPMENT) ?? [] as $entry) {
            $stage = EquipmentStage::tryFrom($entry['stage'] ?? '') ?? EquipmentStage::PREP;
            if ($stage->allowsContainer() === false && filter_var($entry['container'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return $this->respond(Response::HTTP_BAD_REQUEST, 'Equipment used for ' . strtolower($stage->label()) . ' can\'t be a container');
            }
        }

        return null;
    }

    /**
     * Resolves the component lists given in the request. A list that is left out resolves to null, meaning the
     * recipe's current components of that kind are kept
     * @param Request $request The request parameters as given by Laravel
     * @param Restaurant $restaurant The restaurant the components must belong to
     * @return array|JsonResponse The resolved amounts per kind, or an error response when a component is not found
     */
    private function resolveComponents(Request $request, Restaurant $restaurant): array|JsonResponse
    {
        $invalid = $this->validateContainers($request);
        if ($invalid !== null) {
            return $invalid;
        }

        $components = [];
        foreach ($this->recipeService->getComponentKinds() as $kind) {
            if ($request->has($kind) === false) {
                $components[$kind] = null;
                continue;
            }

            $components[$kind] = $this->recipeService->resolveComponents($restaurant, $kind, $request->get($kind) ?? []);
            if ($components[$kind] === null) {
                $isEquipment = $this->recipeService->getComponentModel($kind) === Equipment::class;
                return $this->respond(Response::HTTP_NOT_FOUND, $isEquipment ? 'Equipment not found' : 'Ingredient not found');
            }
        }

        return $components;
    }

    /**
     * Saves a recipe together with whichever component lists were given
     * @param Recipe $recipe The recipe to save
     * @param array $components The resolved component amounts per kind, where null means unchanged
     */
    private function saveRecipe(Recipe $recipe, array $components): void
    {
        DB::transaction(function () use ($recipe, $components) {
            $recipe->save();

            foreach ($components as $kind => $amounts) {
                if ($amounts !== null) {
                    $this->recipeService->syncComponents($recipe, $kind, $amounts);
                }
            }
        });
    }
}
