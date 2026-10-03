<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Table;
use App\Service\DnD\Restaurant\KitchenService;
use App\Service\DnD\Restaurant\OrderService;
use App\Service\DnD\Restaurant\ShiftService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class ServiceApi extends AbstractApi
{
    use ResolvesRestaurant;

    /** @var int The furthest the clock can be moved forward at once, in minutes: a week */
    private const int MAX_ADVANCE = 10080;

    public function __construct(
        private readonly KitchenService $kitchenService,
        private readonly OrderService $orderService,
        private readonly ShiftService $shiftService,
    )
    {
    }

    /**
     * Gets the state of the restaurant's service: its clock and its kitchen
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The service state in JSON format
     */
    public function getService(string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        return $this->respond(Response::HTTP_OK, 'Service retrieved', $this->getState($restaurant));
    }

    /**
     * Changes the restaurant's current time, either by moving it forward a number of minutes or by setting it
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated service state in JSON format
     */
    public function setTime(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'advance' => 'required_without:time|nullable|integer|min:1|max:' . self::MAX_ADVANCE,
            'time'    => 'required_without:advance|nullable|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        if ($request->filled('advance')) {
            $this->kitchenService->advanceTime($restaurant, (int) $request->get('advance'));
        } else {
            $this->kitchenService->setTime($restaurant, $request->get('time'));
        }

        return $this->respond(Response::HTTP_OK, 'Time updated', $this->getState($restaurant));
    }

    /**
     * Plans a meal of a recipe, taking its ingredients out of storage
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated service state in JSON format
     */
    public function planMeal(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recipe_uuid' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $recipe = Recipe::where('uuid', $request->get('recipe_uuid'))->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($recipe === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Recipe not found');
        }

        return $this->attempt($restaurant, 'Meal planned', fn () => $this->kitchenService->planMeal($restaurant, $recipe), Response::HTTP_CREATED);
    }

    /**
     * Prepares a planned meal, which passes the recipe's prep time
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $meal_uuid The uuid of the meal
     * @return JsonResponse The updated service state in JSON format
     */
    public function prepareMeal(string $restaurant_uuid, string $meal_uuid): JsonResponse
    {
        return $this->withMeal($restaurant_uuid, $meal_uuid, 'Meal prepared',
            fn (Restaurant $restaurant, Meal $meal) => $this->kitchenService->prepareMeal($restaurant, $meal));
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
        $validator = Validator::make($request->all(), [
            'cooker_uuid' => 'required|string|max:255',
            'slot'        => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        return $this->withMeal($restaurant_uuid, $meal_uuid, 'Meal is cooking', function (Restaurant $restaurant, Meal $meal) use ($request) {
            $cooker = Equipment::where('uuid', $request->get('cooker_uuid'))->where('restaurant_uuid', $restaurant->uuid)->first();
            if ($cooker === null) {
                return $this->respond(Response::HTTP_NOT_FOUND, 'Cooker not found');
            }

            $this->kitchenService->cookMeal($restaurant, $meal, $cooker, (int) $request->get('slot'));
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
            fn (Restaurant $restaurant, Meal $meal) => $this->kitchenService->skipUntilDone($restaurant, $meal));
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
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $meal = $this->getMeal($restaurant, $meal_uuid);
        if ($meal === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Meal not found');
        }

        $refunded = $this->kitchenService->cancelMeal($restaurant, $meal);

        return $this->respond(Response::HTTP_OK, $refunded ? 'Meal cancelled, ingredients returned' : 'Meal scrapped', $this->getState($restaurant));
    }

    /**
     * Cleans dirty pieces of a piece of equipment, which passes its cleaning time for each piece
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $equipment_uuid The uuid of the equipment
     * @return JsonResponse The updated service state in JSON format
     */
    public function cleanEquipment(Request $request, string $restaurant_uuid, string $equipment_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer|min:1',
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

        $amount = (int) $request->get('amount');
        return $this->attempt($restaurant, sprintf('Cleaned %d %s', $amount, $equipment->name),
            fn () => $this->kitchenService->cleanEquipment($restaurant, $equipment, $amount));
    }

    /**
     * Cleans every dirty piece of equipment, which passes the cleaning time of each piece
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated service state in JSON format
     */
    public function cleanAllEquipment(string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        return $this->attempt($restaurant, 'Cleaned all equipment', fn () => $this->kitchenService->cleanAllEquipment($restaurant));
    }

    //| Plates & orders

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
     * Brings a plated portion to a table that ordered it
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $plate_uuid The uuid of the plated portion
     * @return JsonResponse The updated service state in JSON format
     */
    public function deliverPlate(Request $request, string $restaurant_uuid, string $plate_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_uuid' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $plate = ServedMeal::where('uuid', $plate_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($plate === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Plate not found');
        }

        $table = Table::where('uuid', $request->get('table_uuid'))->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($table === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Table not found');
        }

        return $this->attempt($restaurant, 'Served at ' . $table->name, fn () => $this->orderService->deliverPlate($restaurant, $plate, $table));
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
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $plate = ServedMeal::where('uuid', $plate_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($plate === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Plate not found');
        }

        return $this->attempt($restaurant, 'Plate scrapped', fn () => $this->kitchenService->scrapPlate($restaurant, $plate));
    }

    /**
     * Places an order at a table. Sent as JSON, with an item for each portion: a recipe, and the one customer it is
     * for, or none when it is for the table
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated service state in JSON format
     */
    public function placeOrder(Request $request, string $restaurant_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'table_uuid'            => 'required|string|max:255',
            'items'                 => 'required|array|min:1|max:200',
            'items.*.recipe_uuid'   => 'required|string|max:255',
            'items.*.customer_uuid' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $table = Table::where('uuid', $request->get('table_uuid'))->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($table === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Table not found');
        }

        $items = [];
        foreach ($request->get('items') as $entry) {
            $recipe = Recipe::where('uuid', $entry['recipe_uuid'])->where('restaurant_uuid', $restaurant->uuid)->first();
            if ($recipe === null) {
                return $this->respond(Response::HTTP_NOT_FOUND, 'Recipe not found');
            }

            $customer = null;
            if (($entry['customer_uuid'] ?? null) !== null) {
                $customer = Customer::where('uuid', $entry['customer_uuid'])->where('restaurant_uuid', $restaurant->uuid)->first();
                if ($customer === null) {
                    return $this->respond(Response::HTTP_NOT_FOUND, 'Customer not found');
                }
            }

            $items[] = ['recipe' => $recipe, 'customer' => $customer];
        }

        return $this->attempt($restaurant, 'Order placed', fn () => $this->orderService->placeOrder($restaurant, $table, $items), Response::HTTP_CREATED);
    }

    /**
     * Finishes a fully served order once its meal has been eaten, making its serving equipment dirty
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $order_uuid The uuid of the order
     * @return JsonResponse The updated service state in JSON format
     */
    public function consumeOrder(string $restaurant_uuid, string $order_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $order = Order::where('uuid', $order_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($order === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Order not found');
        }

        return $this->attempt($restaurant, 'Meal consumed', fn () => $this->orderService->consumeOrder($restaurant, $order));
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
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        return $this->respond(Response::HTTP_OK, 'Summary retrieved', $this->shiftService->getSummary($restaurant));
    }

    /**
     * Ends the ongoing service, adding its profit to the restaurant's money and logging it in the service history
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The summary of the service in JSON format
     */
    public function endService(string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        try {
            $summary = $this->shiftService->endService($restaurant);
        } catch (InvalidArgumentException $exception) {
            return $this->respond(Response::HTTP_BAD_REQUEST, $exception->getMessage());
        }

        return $this->respond(Response::HTTP_OK, 'Service ended', $summary);
    }

    //| Helpers

    /**
     * Gets the full service state: the kitchen along with the orders being served
     * @param Restaurant $restaurant The restaurant
     * @return array The service state
     */
    private function getState(Restaurant $restaurant): array
    {
        return $this->kitchenService->getState($restaurant) + ['orders' => $this->orderService->describeOrders($restaurant)];
    }

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
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $meal = $this->getMeal($restaurant, $meal_uuid);
        if ($meal === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Meal not found');
        }

        return $this->attempt($restaurant, $message, fn () => $action($restaurant, $meal));
    }

    /**
     * Performs a kitchen action, responding with the updated service state, or with the reason it isn't possible
     * @param Restaurant $restaurant The restaurant
     * @param string $message The message on success
     * @param Closure $action The action. May return a response to respond with instead
     * @param int $status The status code on success
     * @return JsonResponse The updated service state, or an error, in JSON format
     */
    private function attempt(Restaurant $restaurant, string $message, Closure $action, int $status = Response::HTTP_OK): JsonResponse
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

    /**
     * Gets a meal belonging to a restaurant
     * @param Restaurant $restaurant The restaurant
     * @param string $uuid The uuid of the meal
     * @return Meal|null The meal, or null if it does not belong to the restaurant
     */
    private function getMeal(Restaurant $restaurant, string $uuid): ?Meal
    {
        return Meal::where('uuid', $uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
    }
}
