<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Service\DnD\Restaurant\InventoryService;
use App\Service\DnD\Restaurant\OrderService;
use App\Service\DnD\Restaurant\ServiceStateService;
use App\Service\DnD\Restaurant\ShiftService;
use App\Service\DnD\Restaurant\WorkService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The restaurant's service as a whole: its state, its clock, ingredients brought in, sales, and ending it
 */
class ServiceApi extends AbstractServiceApi
{
    use ResolvesRestaurant;

    /** @var int The furthest the clock can be moved forward at once, in minutes: a week */
    private const int MAX_ADVANCE = 10080;

    public function __construct(
        ServiceStateService $serviceStateService,
        private readonly OrderService $orderService,
        private readonly ShiftService $shiftService,
        private readonly WorkService $workService,
        private readonly InventoryService $inventoryService,
    )
    {
        parent::__construct($serviceStateService);
    }

    /**
     * Gets the state of the restaurant's service: its clock and its kitchen. A refresh that gives the version it last
     * saw only gets told nothing changed, when that is the case
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The service state in JSON format
     */
    public function getService(Request $request, string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        if ($request->filled('version') && (int) $request->get('version') === $restaurant->state_version) {
            return $this->respond(Response::HTTP_OK, 'Unchanged', ['unchanged' => true, 'version' => $restaurant->state_version]);
        }

        return $this->respond(Response::HTTP_OK, 'Service retrieved', $this->getState($restaurant));
    }

    /**
     * Changes the restaurant's current time, either by moving it forward a number of minutes or by setting it. The
     * work areas get work done while it moves forward
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated service state in JSON format
     */
    public function setTime(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'advance' => 'required_without:time|nullable|integer|min:1|max:' . self::MAX_ADVANCE,
            'time'    => 'required_without:advance|nullable|date_format:H:i',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        if ($request->filled('advance')) {
            $this->workService->advanceTime($restaurant, (int) $request->get('advance'));
        } else {
            $this->workService->setTime($restaurant, $request->get('time'));
        }

        return $this->respond(Response::HTTP_OK, 'Time updated', $this->getState($restaurant));
    }

    //| Ingredients

    /**
     * Adds an ingredient brought in during service to the pile, where it waits to be sorted into the storage spaces
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $ingredient_uuid The uuid of the ingredient
     * @return JsonResponse The updated service state in JSON format
     */
    public function addIngredient(Request $request, string $restaurant_uuid, string $ingredient_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'amount' => 'required|numeric|gt:0|max:999999',
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

        $amount = round((float) $request->get('amount'), 2);
        $this->inventoryService->addToPile($restaurant, $ingredient, $amount);

        return $this->respond(Response::HTTP_OK, sprintf('Added %s %s', $amount + 0, $ingredient->name), $this->getState($restaurant));
    }

    //| Sales

    /**
     * Puts a recipe on sale for the ongoing service, at a price that replaces its usual one until the service ends
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $recipe_uuid The uuid of the recipe
     * @return JsonResponse The updated service state in JSON format
     */
    public function setSale(Request $request, string $restaurant_uuid, string $recipe_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'price' => 'required|numeric|min:0|max:99999999',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        return $this->withRecipe($restaurant_uuid, $recipe_uuid, fn (Restaurant $restaurant, Recipe $recipe) => $this->attempt(
            $restaurant, $recipe->name . ' is on sale',
            fn () => $this->orderService->setSale($restaurant, $recipe, (float) $request->get('price'))
        ));
    }

    /**
     * Takes a recipe off sale, so it is ordered at its usual price again
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $recipe_uuid The uuid of the recipe
     * @return JsonResponse The updated service state in JSON format
     */
    public function removeSale(string $restaurant_uuid, string $recipe_uuid): JsonResponse
    {
        return $this->withRecipe($restaurant_uuid, $recipe_uuid, fn (Restaurant $restaurant, Recipe $recipe) => $this->attempt(
            $restaurant, $recipe->name . ' is no longer on sale',
            fn () => $this->orderService->removeSale($restaurant, $recipe)
        ));
    }

    /**
     * Resolves the restaurant and one of its recipes, then responds with what the action gives
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $recipe_uuid The uuid of the recipe
     * @param Closure $action The action, given the restaurant and recipe, returning the response
     * @return JsonResponse The response, or an error, in JSON format
     */
    private function withRecipe(string $restaurant_uuid, string $recipe_uuid, Closure $action): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $recipe = $this->findInRestaurant(Recipe::class, $restaurant, $recipe_uuid);
        if ($recipe === null) {
            return $this->notFound('Recipe');
        }

        return $action($restaurant, $recipe);
    }

    //| Ending the service

    /**
     * Summarises the ongoing service: what has been sold, and the profit so far
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The summary in JSON format
     */
    public function getSummary(string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        return $this->respond(Response::HTTP_OK, 'Summary retrieved', $this->shiftService->getSummary($restaurant));
    }

    /**
     * Ends the ongoing service, adding its profit to the restaurant's money and logging it in the service history. The
     * restaurant can move on to the next day straight away, which ages its stored ingredients
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The summary of the service, and the ingredients that expired, in JSON format
     */
    public function endService(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'advance_day' => 'nullable|boolean',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        try {
            $summary = $this->shiftService->endService($restaurant);
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        $expired = $request->boolean('advance_day') ? $this->inventoryService->advanceDay($restaurant->fresh()) : [];

        return $this->respond(Response::HTTP_OK, 'Service ended', $summary + [
            'date' => $restaurant->fresh()->date->format('Y-m-d'),
            'expired' => $expired,
        ]);
    }
}
