<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Service\DnD\Restaurant\KitchenService;
use App\Service\DnD\Restaurant\ServiceStateService;
use App\Service\DnD\Restaurant\WorkService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The meals made during service, from being planned through prep and cooking to being plated
 */
class MealApi extends AbstractServiceApi
{
    use ResolvesRestaurant;

    public function __construct(
        ServiceStateService $serviceStateService,
        private readonly KitchenService $kitchenService,
        private readonly WorkService $workService,
    )
    {
        parent::__construct($serviceStateService);
    }

    //| Making meals

    /**
     * Plans a meal of a recipe, taking its ingredients out of storage
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated service state in JSON format
     */
    public function planMeal(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'recipe_uuid' => 'required|string|max:255',
            // The ingredient chosen for each category the recipe takes, keyed by category
            'choices'     => 'nullable|array',
            'choices.*'   => 'nullable|string|max:255',
            // The variant to use instead of each piece of equipment the recipe takes, keyed by that equipment's uuid
            'equipment'   => 'nullable|array',
            'equipment.*' => 'nullable|string|max:255',
            // The variant to use instead of each ingredient the recipe takes, keyed by that ingredient's uuid
            'ingredients'   => 'nullable|array',
            'ingredients.*' => 'nullable|string|max:255',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $recipe = $this->findInRestaurant(Recipe::class, $restaurant, $request->get('recipe_uuid'));
        if ($recipe === null) {
            return $this->notFound('Recipe');
        }

        $choices = [];
        foreach ($request->get('choices') ?? [] as $category => $ingredientUuid) {
            if ($ingredientUuid === null || $ingredientUuid === '') {
                continue;
            }

            $ingredient = $this->findInRestaurant(Ingredient::class, $restaurant, $ingredientUuid);
            if ($ingredient === null) {
                return $this->notFound('Ingredient');
            }
            $choices[$category] = $ingredient;
        }

        return $this->attempt($restaurant, 'Meal planned',
            fn () => $this->kitchenService->planMeal($restaurant, $recipe, $choices, $request->get('equipment') ?? [], $request->get('ingredients') ?? []),
            Response::HTTP_CREATED);
    }

    /**
     * Queues a planned meal to be prepped in the prep area
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $meal_uuid The uuid of the meal
     * @return JsonResponse The updated service state in JSON format
     */
    public function prepareMeal(string $restaurant_uuid, string $meal_uuid): JsonResponse
    {
        return $this->withMeal($restaurant_uuid, $meal_uuid, 'Queued for prep',
            fn (Restaurant $restaurant, Meal $meal) => $this->workService->queuePrep($restaurant, $meal));
    }

    /**
     * Puts a prepped meal in a cooker, which starts its cooking timer
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $meal_uuid The uuid of the meal
     * @return JsonResponse The updated service state in JSON format
     */
    public function cookMeal(Request $request, string $restaurant_uuid, string $meal_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'cooker_uuid' => 'required|string|max:255',
            'slot'        => 'required|integer|min:1',
            // The variant to use instead of each piece of cooking equipment the recipe takes, keyed by its uuid
            'equipment'   => 'nullable|array',
            'equipment.*' => 'nullable|string|max:255',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        return $this->withMeal($restaurant_uuid, $meal_uuid, 'Meal is cooking', function (Restaurant $restaurant, Meal $meal) use ($request) {
            $cooker = $this->findInRestaurant(Equipment::class, $restaurant, $request->get('cooker_uuid'));
            if ($cooker === null) {
                return $this->notFound('Cooker');
            }

            $this->kitchenService->cookMeal($restaurant, $meal, $cooker, (int) $request->get('slot'), $request->get('equipment') ?? []);
            return null;
        });
    }

    /**
     * Passes time until a cooking meal is done
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $meal_uuid The uuid of the meal
     * @return JsonResponse The updated service state in JSON format
     */
    public function skipMeal(string $restaurant_uuid, string $meal_uuid): JsonResponse
    {
        return $this->withMeal($restaurant_uuid, $meal_uuid, 'Skipped until the meal is done',
            fn (Restaurant $restaurant, Meal $meal) => $this->workService->skipUntilDone($restaurant, $meal));
    }

    /**
     * Takes a done meal out of its cooker to be served
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $meal_uuid The uuid of the meal
     * @return JsonResponse The updated service state in JSON format
     */
    public function serveMeal(string $restaurant_uuid, string $meal_uuid): JsonResponse
    {
        return $this->withMeal($restaurant_uuid, $meal_uuid, 'Meal moved to serving',
            fn (Restaurant $restaurant, Meal $meal) => $this->kitchenService->serveMeal($restaurant, $meal));
    }

    /**
     * Cancels or scraps a meal. A planned meal gets its ingredients back, any later the ingredients are wasted
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $meal_uuid The uuid of the meal
     * @return JsonResponse The updated service state in JSON format
     */
    public function cancelMeal(string $restaurant_uuid, string $meal_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $meal = $this->findInRestaurant(Meal::class, $restaurant, $meal_uuid);
        if ($meal === null) {
            return $this->notFound('Meal');
        }

        $refunded = $this->kitchenService->cancelMeal($restaurant, $meal);

        return $this->respond(Response::HTTP_OK, $refunded ? 'Meal cancelled, ingredients returned' : 'Meal scrapped', $this->getState($restaurant));
    }

    /**
     * Sets how well a finished meal turned out
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $meal_uuid The uuid of the meal
     * @return JsonResponse The updated service state in JSON format
     */
    public function setMealQuality(Request $request, string $restaurant_uuid, string $meal_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'quality' => 'required|integer|min:-9999|max:9999',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        return $this->withMeal($restaurant_uuid, $meal_uuid, 'Quality saved',
            fn (Restaurant $restaurant, Meal $meal) => $this->kitchenService->setQuality($restaurant, $meal, (int) $request->get('quality')));
    }

    /**
     * Puts a finished meal into a meal being prepared whose recipe takes it. Portions of the finished meal left over
     * are plated, which has to be allowed explicitly
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $meal_uuid The uuid of the meal being prepared
     * @return JsonResponse The updated service state in JSON format
     */
    public function supplyMeal(Request $request, string $restaurant_uuid, string $meal_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'source_uuid'     => 'required|string|max:255',
            'plate_remaining' => 'nullable|boolean',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        return $this->withMeal($restaurant_uuid, $meal_uuid, 'Meal put in', function (Restaurant $restaurant, Meal $meal) use ($request) {
            $source = $this->findInRestaurant(Meal::class, $restaurant, $request->get('source_uuid'));
            if ($source === null) {
                return $this->notFound('Meal');
            }

            $this->kitchenService->supplyMeal($restaurant, $meal, $source, $request->boolean('plate_remaining'));
            return null;
        });
    }

    //| Plates

    /**
     * Plates a portion of a cooked meal, using the recipe's serving equipment
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $meal_uuid The uuid of the meal
     * @return JsonResponse The updated service state in JSON format
     */
    public function plateMeal(string $restaurant_uuid, string $meal_uuid): JsonResponse
    {
        return $this->withMeal($restaurant_uuid, $meal_uuid, 'Portion plated',
            fn (Restaurant $restaurant, Meal $meal) => $this->kitchenService->plateMeal($restaurant, $meal));
    }

    /**
     * Throws away a plated portion that hasn't been brought to a table, making its serving equipment dirty
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $plate_uuid The uuid of the plated portion
     * @return JsonResponse The updated service state in JSON format
     */
    public function scrapPlate(string $restaurant_uuid, string $plate_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $plate = $this->findInRestaurant(ServedMeal::class, $restaurant, $plate_uuid);
        if ($plate === null) {
            return $this->notFound('Plate');
        }

        return $this->attempt($restaurant, 'Plate scrapped', fn () => $this->kitchenService->scrapPlate($restaurant, $plate));
    }

    //| Helpers

    /**
     * Resolves the restaurant and one of its meals, then performs an action on the meal
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $meal_uuid The uuid of the meal
     * @param string $message The message on success
     * @param Closure $action The action, given the restaurant and meal. May return a response to respond with instead
     * @return JsonResponse The updated service state, or an error, in JSON format
     */
    private function withMeal(string $restaurant_uuid, string $meal_uuid, string $message, Closure $action): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $meal = $this->findInRestaurant(Meal::class, $restaurant, $meal_uuid);
        if ($meal === null) {
            return $this->notFound('Meal');
        }

        return $this->attempt($restaurant, $message, fn () => $action($restaurant, $meal));
    }
}
