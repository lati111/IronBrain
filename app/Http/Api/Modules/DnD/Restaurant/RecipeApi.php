<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Service\DnD\Restaurant\InventoryStateService;
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

    /** @var string What kind of recipe a recipe is when not specified */
    private const string DEFAULT_TYPE = 'meal';

    public function __construct(
        private readonly RecipeService $recipeService,
        private readonly InventoryStateService $inventoryStateService,
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
        // A variant is made of the changes it makes to its base recipe, so it is saved apart
        if ($request->filled('parent_uuid')) {
            return $this->saveVariant($request, $restaurant_uuid, null);
        }

        $validator = Validator::make($request->all(), array_merge([
            'name'        => 'required|string|max:64',
            'type'        => 'nullable|string|max:64',
            'category'    => 'nullable|string|max:64',
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

        $meals = $this->resolveMeals($request, $restaurant, null);
        if ($meals instanceof JsonResponse) {
            return $meals;
        }

        $recipe = new Recipe();
        $recipe->restaurant_uuid = $restaurant->uuid;
        $recipe->name            = $request->get('name');
        $recipe->type            = $this->normalizeText($request->get('type')) ?? self::DEFAULT_TYPE;
        $recipe->category        = $this->normalizeText($request->get('category'));
        $recipe->description     = $request->get('description');
        $recipe->price           = (float) $request->get('price');
        $recipe->portions        = (int) ($request->get('portions') ?? self::DEFAULT_PORTIONS);
        foreach (self::INTEGER_FIELDS as $field) {
            $recipe->{$field} = (int) $request->get($field);
        }

        $this->saveRecipe($recipe, $components, $this->getCategories($request), $meals);

        return $this->respond(Response::HTTP_CREATED, 'Recipe created', [
            'uuid' => $recipe->uuid,
            'inventory' => $this->inventoryStateService->getInventory($restaurant),
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
        $variant = Recipe::where('uuid', $recipe_uuid)->whereNotNull('parent_uuid')->first();
        if ($variant !== null) {
            return $this->saveVariant($request, $restaurant_uuid, $variant);
        }

        $validator = Validator::make($request->all(), array_merge([
            'name'        => 'nullable|string|max:64',
            'type'        => 'nullable|string|max:64',
            'category'    => 'nullable|string|max:64',
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

        $meals = $this->resolveMeals($request, $restaurant, $recipe);
        if ($meals instanceof JsonResponse) {
            return $meals;
        }

        $recipe->name        = $request->get('name', $recipe->name);
        $recipe->type        = $request->has('type') ? $this->normalizeText($request->get('type')) ?? self::DEFAULT_TYPE : $recipe->type;
        $recipe->category    = $request->has('category') ? $this->normalizeText($request->get('category')) : $recipe->category;
        $recipe->description = $request->has('description') ? $request->get('description') : $recipe->description;
        $recipe->price       = $request->has('price') ? (float) $request->get('price') : $recipe->price;
        $recipe->portions    = $request->has('portions') ? (int) $request->get('portions') : $recipe->portions;
        foreach (self::INTEGER_FIELDS as $field) {
            $recipe->{$field} = $request->has($field) ? (int) $request->get($field) : $recipe->{$field};
        }

        $this->saveRecipe($recipe, $components, $this->getCategories($request), $meals);

        return $this->respond(Response::HTTP_OK, 'Recipe updated', [
            'uuid' => $recipe->uuid,
            'inventory' => $this->inventoryStateService->getInventory($restaurant),
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

        // Variants are made of their changes to the base recipe, which would be lost along with it
        if ($recipe->Variants()->exists()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'Delete the variants of ' . $recipe->name . ' first');
        }

        $recipe->delete();

        return $this->respond(Response::HTTP_OK, 'Recipe deleted', [
            'inventory' => $this->inventoryStateService->getInventory($restaurant),
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

        // Ingredients can also be taken by category, any ingredient of which will do
        $rules['categories']            = 'nullable|array';
        $rules['categories.*.category'] = 'required|string|max:64|distinct';
        $rules['categories.*.amount']   = 'required|numeric|gt:0|max:999999';

        // Other meals the recipe takes, of a specific recipe or of any recipe of a category
        $rules['meals']               = 'nullable|array';
        $rules['meals.*.recipe_uuid'] = 'nullable|required_without:meals.*.category|string|max:255';
        $rules['meals.*.category']    = 'nullable|required_without:meals.*.recipe_uuid|string|max:64';
        $rules['meals.*.portions']    = 'required|integer|min:1|max:999999';

        return $rules;
    }

    /**
     * Gets the ingredients a recipe takes by category from the request, with their amounts
     * @param Request $request The request parameters as given by Laravel
     * @return array<string, float>|null The amounts keyed by category, or null when the list was left out, meaning the
     *                                   recipe's current ones are kept
     */
    private function getCategories(Request $request): ?array
    {
        if ($request->has('categories') === false) {
            return null;
        }

        $categories = [];
        foreach ($request->get('categories') ?? [] as $entry) {
            $categories[trim($entry['category'])] = round((float) $entry['amount'], 2);
        }

        return $categories;
    }

    /**
     * Resolves the other meals a recipe takes from the request, checking that every required recipe belongs to the
     * restaurant and isn't the recipe itself
     * @param Request $request The request parameters as given by Laravel
     * @param Restaurant $restaurant The restaurant the required recipes must belong to
     * @param Recipe|null $recipe The recipe being edited, or null when it is new
     * @return array|JsonResponse|null The meals, as recipe_uuid or category with portions, null when the list was left
     *                                 out, meaning the recipe's current ones are kept, or an error response
     */
    private function resolveMeals(Request $request, Restaurant $restaurant, ?Recipe $recipe): array|JsonResponse|null
    {
        if ($request->has('meals') === false) {
            return null;
        }

        // Keyed by what is required, so the same meal listed twice is only required once
        $meals = [];
        foreach ($request->get('meals') ?? [] as $entry) {
            $recipeUuid = $this->normalizeText($entry['recipe_uuid'] ?? null);
            $category = $recipeUuid === null ? $this->normalizeText($entry['category'] ?? null) : null;
            if ($recipeUuid === null && $category === null) {
                return $this->respond(Response::HTTP_BAD_REQUEST, 'Choose a recipe or category for each meal');
            }

            $key = $recipeUuid !== null ? 'recipe:' . $recipeUuid : 'category:' . $category;
            $meals[$key] = ['recipe_uuid' => $recipeUuid, 'category' => $category, 'portions' => (int) $entry['portions']];
        }

        $recipeUuids = array_values(array_filter(array_column($meals, 'recipe_uuid')));
        if ($recipe !== null && in_array($recipe->uuid, $recipeUuids, true)) {
            return $this->respond(Response::HTTP_BAD_REQUEST, 'A recipe can\'t require itself');
        }

        if (Recipe::where('restaurant_uuid', $restaurant->uuid)->whereIn('uuid', $recipeUuids)->count() !== count($recipeUuids)) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Recipe not found');
        }

        return array_values($meals);
    }

    /**
     * Tidies up text as entered, where empty text means none
     * @param string|null $text The text as entered
     * @return string|null The text, or null for none
     */
    private function normalizeText(?string $text): ?string
    {
        $text = trim($text ?? '');
        return $text === '' ? null : $text;
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
            $isEquipment = $this->recipeService->getComponentModel($kind) === Equipment::class;
            if ($components[$kind] === null) {
                return $this->respond(Response::HTTP_NOT_FOUND, $isEquipment ? 'Equipment not found' : 'Ingredient not found');
            }

            // Cookers are where meals are cooked rather than what a recipe takes, so they can't be part of one
            if ($isEquipment && Equipment::whereIn('uuid', array_keys($components[$kind]))->where('category', EquipmentCategory::COOKER->value)->exists()) {
                return $this->respond(Response::HTTP_BAD_REQUEST, 'Cookers can\'t be part of a recipe');
            }
        }

        return $components;
    }

    /**
     * Saves a recipe together with whichever component lists were given
     * @param Recipe $recipe The recipe to save
     * @param array $components The resolved component amounts per kind, where null means unchanged
     * @param array<string, float>|null $categories The amounts of the ingredients taken by category, keyed by
     *                                              category, or null when unchanged
     * @param array|null $meals The other meals the recipe takes, as given by resolveMeals, or null when unchanged
     */
    private function saveRecipe(Recipe $recipe, array $components, ?array $categories, ?array $meals): void
    {
        DB::transaction(function () use ($recipe, $components, $categories, $meals) {
            $recipe->save();

            foreach ($components as $kind => $amounts) {
                if ($amounts !== null) {
                    $this->recipeService->syncComponents($recipe, $kind, $amounts);
                }
            }

            if ($categories !== null) {
                $this->recipeService->syncCategories($recipe, $categories);
            }

            if ($meals !== null) {
                $this->recipeService->syncMeals($recipe, $meals);
            }

            // Variants are their base recipe with their changes, so they change along with it
            $this->recipeService->rebuildVariants($recipe);
        });
    }

    //| Variants

    /**
     * Creates or updates a variant of a recipe. A variant has a name and description of its own, while its stats are
     * what it adds to its base recipe's, and its lists are what it adds to its base recipe's, with the remove lists
     * holding what it takes away. Anything that is left out keeps its current value. Its own stats and lists are
     * rebuilt from its base recipe afterwards
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param Recipe|null $variant The variant to update, or null to create one of the recipe given as parent_uuid
     * @return JsonResponse The uuid of the variant and the updated inventory state in JSON format
     */
    private function saveVariant(Request $request, string $restaurant_uuid, ?Recipe $variant): JsonResponse
    {
        $creating = $variant === null;
        $validator = Validator::make($request->all(), array_merge([
            'name'        => ($creating ? 'required' : 'nullable') . '|string|max:64',
            'parent_uuid' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            // What is added to the base recipe's stats, which can also take away
            'price'       => 'nullable|numeric|min:-999999|max:999999',
            'portions'    => 'nullable|integer|min:-999999|max:999999',
            // What is taken away from each of the base recipe's lists
            'remove'      => 'nullable|array',
            'remove.*'    => 'nullable|array',
            'remove.*.*'  => 'string|max:255',
        ], array_fill_keys(self::INTEGER_FIELDS, 'nullable|integer|min:-999999|max:999999'), $this->getComponentRules()));

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        if ($creating) {
            $base = Recipe::where('uuid', $request->get('parent_uuid'))->where('restaurant_uuid', $restaurant->uuid)->first();
            if ($base === null) {
                return $this->respond(Response::HTTP_NOT_FOUND, 'Recipe not found');
            }
            if ($base->parent_uuid !== null) {
                return $this->respond(Response::HTTP_BAD_REQUEST, 'A variant can\'t have variants of its own');
            }

            $variant = new Recipe();
            $variant->restaurant_uuid = $restaurant->uuid;
            $variant->parent_uuid = $base->uuid;
        } elseif ($variant->restaurant_uuid !== $restaurant->uuid) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Recipe not found');
        }

        $components = $this->resolveComponents($request, $restaurant);
        if ($components instanceof JsonResponse) {
            return $components;
        }

        $meals = $this->resolveMeals($request, $restaurant, $creating ? null : $variant);
        if ($meals instanceof JsonResponse) {
            return $meals;
        }

        $variant->name = $request->get('name') ?? $variant->name;
        $variant->description = $request->has('description') ? $request->get('description') : $variant->description;
        $variant->variant_changes = $this->getVariantChanges($request, $variant->variant_changes ?? [], $components, $meals);

        DB::transaction(fn () => $this->recipeService->rebuildVariant($variant));

        return $this->respond($creating ? Response::HTTP_CREATED : Response::HTTP_OK, $creating ? 'Variant created' : 'Variant updated', [
            'uuid' => $variant->uuid,
            'inventory' => $this->inventoryStateService->getInventory($restaurant),
        ]);
    }

    /**
     * Works out a variant's changes to its base recipe from the request, keeping whatever was left out as it was
     * @param Request $request The request parameters as given by Laravel
     * @param array $current The variant's current changes
     * @param array $components The resolved components it adds per kind, where null means unchanged
     * @param array|null $meals The meals it adds, as given by resolveMeals, or null when unchanged
     * @return array The changes, as described by Recipe::variant_changes
     */
    private function getVariantChanges(Request $request, array $current, array $components, ?array $meals): array
    {
        $changes = ['stats' => $current['stats'] ?? [], 'add' => $current['add'] ?? [], 'remove' => $current['remove'] ?? []];

        foreach (Recipe::VARIANT_STATS as $field) {
            if ($request->filled($field)) {
                $changes['stats'][$field] = $field === 'price' ? round((float) $request->get($field), 2) : (int) $request->get($field);
            }
        }

        foreach ($components as $kind => $amounts) {
            if ($amounts !== null) {
                // Kept as plain values, as the stage of equipment is an enum
                $changes['add'][$kind] = array_map(fn (array $attributes) => array_map(
                    fn ($value) => $value instanceof EquipmentStage ? $value->value : $value, $attributes), $amounts);
            }
        }

        $categories = $this->getCategories($request);
        if ($categories !== null) {
            $changes['add']['categories'] = $categories;
        }
        if ($meals !== null) {
            $changes['add']['meals'] = $meals;
        }

        // Taken away by uuid, by category for ingredients taken by category, and by recipe uuid or category with the
        // category prefix for meals
        if ($request->has('remove')) {
            foreach (Recipe::VARIANT_LISTS as $list) {
                $changes['remove'][$list] = array_values(array_unique(array_map('strval', $request->input('remove.' . $list) ?? [])));
            }
        }

        return $changes;
    }
}
