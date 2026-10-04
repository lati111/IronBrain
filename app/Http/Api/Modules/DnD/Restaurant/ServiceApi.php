<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Enum\ErrorEnum;
use App\Http\Api\AbstractApi;
use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Ingredient;
use App\Models\DnD\Restaurant\Meal;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Table;
use App\Models\DnD\Restaurant\Work;
use App\Service\DnD\Restaurant\InventoryService;
use App\Service\DnD\Restaurant\KitchenService;
use App\Service\DnD\Restaurant\OrderService;
use App\Service\DnD\Restaurant\ShiftService;
use App\Service\DnD\Restaurant\WorkService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
        private readonly WorkService $workService,
        private readonly InventoryService $inventoryService,
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
     * Changes the restaurant's current time, either by moving it forward a number of minutes or by setting it. The
     * work areas get work done while it moves forward
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
            $this->workService->advanceTime($restaurant, (int) $request->get('advance'));
        } else {
            $this->workService->setTime($restaurant, $request->get('time'));
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
     * Sets how well a finished meal turned out
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $meal_uuid The uuid of the meal
     * @return JsonResponse The updated service state in JSON format
     */
    public function setMealQuality(Request $request, string $restaurant_uuid, string $meal_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'quality' => 'required|integer|min:-9999|max:9999',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        return $this->withMeal($restaurant_uuid, $meal_uuid, 'Quality saved',
            fn (Restaurant $restaurant, Meal $meal) => $this->kitchenService->setQuality($restaurant, $meal, (int) $request->get('quality')));
    }

    /**
     * Queues dirty pieces of a piece of equipment to be cleaned in the cleaning area
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
        return $this->attempt($restaurant, sprintf('Queued %d %s for cleaning', $amount, $equipment->name),
            fn () => $this->workService->queueCleaning($restaurant, $equipment, $amount));
    }

    /**
     * Queues every dirty piece of equipment that isn't queued yet to be cleaned in the cleaning area
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated service state in JSON format
     */
    public function cleanAllEquipment(string $restaurant_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        return $this->attempt($restaurant, 'Queued all dirty equipment for cleaning', fn () => $this->workService->queueAllCleaning($restaurant));
    }

    /**
     * Has an employee wait a table, taking its orders, or leaves the table without a waiter when none is given
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $table_uuid The uuid of the table
     * @return JsonResponse The updated service state in JSON format
     */
    public function assignWaiter(Request $request, string $restaurant_uuid, string $table_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_uuid' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $table = Table::where('uuid', $table_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($table === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Table not found');
        }

        $employee = null;
        if ($request->filled('employee_uuid')) {
            $employee = Employee::where('uuid', $request->get('employee_uuid'))->where('restaurant_uuid', $restaurant->uuid)->first();
            if ($employee === null) {
                return $this->respond(Response::HTTP_NOT_FOUND, 'Employee not found');
            }
        }

        $this->workService->assignWaiter($table, $employee);

        $message = $employee !== null ? sprintf('%s is waiting %s', $employee->name, $table->name) : $table->name . ' has no waiter';
        return $this->respond(Response::HTTP_OK, $message, $this->getState($restaurant));
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
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|gt:0|max:999999',
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
        $validator = Validator::make($request->all(), [
            'price' => 'required|numeric|min:0|max:99999999',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
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
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $recipe = Recipe::where('uuid', $recipe_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($recipe === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Recipe not found');
        }

        return $action($restaurant, $recipe);
    }

    /**
     * Moves a job to the front of its queue, so it is worked on next
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $work_uuid The uuid of the job
     * @return JsonResponse The updated service state in JSON format
     */
    public function prioritizeWork(string $restaurant_uuid, string $work_uuid): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $job = Work::where('uuid', $work_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($job === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Job not found');
        }

        $this->workService->prioritize($job);
        return $this->respond(Response::HTTP_OK, 'Prioritized', $this->getState($restaurant));
    }

    //| Staff

    /**
     * Puts an employee to work in a work area, or takes them out of it when no work area is given
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $employee_uuid The uuid of the employee
     * @return JsonResponse The updated service state in JSON format
     */
    public function assignEmployee(Request $request, string $restaurant_uuid, string $employee_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'station' => ['nullable', 'string', Rule::in(WorkStation::values())],
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $employee = Employee::where('uuid', $employee_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($employee === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Employee not found');
        }

        $station = $request->filled('station') ? WorkStation::from($request->get('station')) : null;
        $this->workService->assignEmployee($employee, $station);

        $message = $station !== null
            ? sprintf('%s is working at %s', $employee->name, strtolower($station->label()))
            : $employee->name . ' stopped working';
        return $this->respond(Response::HTTP_OK, $message, $this->getState($restaurant));
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

        return $this->attempt($restaurant, 'On its way to ' . $table->name, fn () => $this->orderService->deliverPlate($restaurant, $plate, $table));
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

        $items = $this->resolveOrderItems($restaurant, null, $request->get('items'));
        if ($items instanceof JsonResponse) {
            return $items;
        }

        return $this->attempt($restaurant, 'Order placed', fn () => $this->orderService->placeOrder($restaurant, $table, $items), Response::HTTP_CREATED);
    }

    /**
     * Edits the items of an order. Sent as JSON, with every item the order should have: existing ones by their uuid,
     * and new ones by their recipe, each with the one customer it is for. Unserved items that are left out are removed
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $order_uuid The uuid of the order
     * @return JsonResponse The updated service state in JSON format
     */
    public function editOrder(Request $request, string $restaurant_uuid, string $order_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items'                 => 'required|array|min:1|max:200',
            'items.*.uuid'          => 'nullable|string|max:255',
            'items.*.recipe_uuid'   => 'required_without:items.*.uuid|nullable|string|max:255',
            'items.*.customer_uuid' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        return $this->withOrder($restaurant_uuid, $order_uuid, function (Restaurant $restaurant, Order $order) use ($request) {
            $items = $this->resolveOrderItems($restaurant, $order, $request->get('items'));
            if ($items instanceof JsonResponse) {
                return $items;
            }

            return $this->attempt($restaurant, 'Order updated', fn () => $this->orderService->editOrder($restaurant, $order, $items));
        });
    }

    /**
     * Sets the tip and the discount of an order, which change what is paid once it is finished
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $order_uuid The uuid of the order
     * @return JsonResponse The updated service state in JSON format
     */
    public function adjustOrder(Request $request, string $restaurant_uuid, string $order_uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tip'      => 'required|numeric|min:0|max:99999999',
            'discount' => 'required|numeric|min:0|max:99999999',
        ]);

        if ($validator->fails()) {
            return $this->respond(Response::HTTP_BAD_REQUEST, ErrorEnum::VALIDATION_FAIL, $validator->errors());
        }

        return $this->withOrder($restaurant_uuid, $order_uuid, fn (Restaurant $restaurant, Order $order) => $this->attempt(
            $restaurant, 'Tip and discount saved',
            fn () => $this->orderService->setTipAndDiscount($order, (float) $request->get('tip'), (float) $request->get('discount'))
        ));
    }

    /**
     * Marks every served portion of an order that isn't eaten yet as eaten, returning their serving equipment dirty
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $order_uuid The uuid of the order
     * @return JsonResponse The updated service state in JSON format
     */
    public function consumeOrder(string $restaurant_uuid, string $order_uuid): JsonResponse
    {
        return $this->withOrder($restaurant_uuid, $order_uuid, fn (Restaurant $restaurant, Order $order) => $this->attempt(
            $restaurant, 'Meal consumed', fn () => $this->orderService->consumeOrder($restaurant, $order)
        ));
    }

    /**
     * Marks a single served portion of an order as eaten, returning its serving equipment dirty
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $order_uuid The uuid of the order
     * @param string $item_uuid The uuid of the portion
     * @return JsonResponse The updated service state in JSON format
     */
    public function consumeOrderItem(string $restaurant_uuid, string $order_uuid, string $item_uuid): JsonResponse
    {
        return $this->withOrder($restaurant_uuid, $order_uuid, function (Restaurant $restaurant, Order $order) use ($item_uuid) {
            $item = $order->Items()->where('uuid', $item_uuid)->first();
            if ($item === null) {
                return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
            }

            return $this->attempt($restaurant, $item->name . ' consumed', fn () => $this->orderService->consumeItem($restaurant, $item));
        });
    }

    /**
     * Finishes an order, paying it into the shift total
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $order_uuid The uuid of the order
     * @return JsonResponse The updated service state in JSON format
     */
    public function finishOrder(string $restaurant_uuid, string $order_uuid): JsonResponse
    {
        return $this->withOrder($restaurant_uuid, $order_uuid, fn (Restaurant $restaurant, Order $order) => $this->attempt(
            $restaurant, 'Order finished', fn () => $this->orderService->finishOrder($restaurant, $order)
        ));
    }

    /**
     * Cancels an order, so nothing is paid for it
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $order_uuid The uuid of the order
     * @return JsonResponse The updated service state in JSON format
     */
    public function cancelOrder(string $restaurant_uuid, string $order_uuid): JsonResponse
    {
        return $this->withOrder($restaurant_uuid, $order_uuid, fn (Restaurant $restaurant, Order $order) => $this->attempt(
            $restaurant, 'Order cancelled', fn () => $this->orderService->cancelOrder($restaurant, $order)
        ));
    }

    /**
     * Resolves the restaurant and one of its orders, then responds with what the action gives
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $order_uuid The uuid of the order
     * @param Closure $action The action, given the restaurant and order, returning the response
     * @return JsonResponse The response, or an error, in JSON format
     */
    private function withOrder(string $restaurant_uuid, string $order_uuid, Closure $action): JsonResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Restaurant not found');
        }

        $order = Order::where('uuid', $order_uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
        if ($order === null) {
            return $this->respond(Response::HTTP_NOT_FOUND, 'Order not found');
        }

        return $action($restaurant, $order);
    }

    /**
     * Resolves the items given for an order: existing items of the order by their uuid, and new ones by their recipe,
     * each with the customer it is for
     * @param Restaurant $restaurant The restaurant
     * @param Order|null $order The order the existing items belong to, or null when placing a new order
     * @param array $entries The items as sent
     * @return array|JsonResponse The items, each with an item or a recipe and a customer, or an error when one isn't found
     */
    private function resolveOrderItems(Restaurant $restaurant, ?Order $order, array $entries): array|JsonResponse
    {
        $items = [];
        foreach ($entries as $entry) {
            $customer = null;
            if (($entry['customer_uuid'] ?? null) !== null) {
                $customer = Customer::where('uuid', $entry['customer_uuid'])->where('restaurant_uuid', $restaurant->uuid)->first();
                if ($customer === null) {
                    return $this->respond(Response::HTTP_NOT_FOUND, 'Customer not found');
                }
            }

            if ($order !== null && ($entry['uuid'] ?? null) !== null) {
                $item = $order->Items()->where('uuid', $entry['uuid'])->first();
                if ($item === null) {
                    return $this->respond(Response::HTTP_NOT_FOUND, 'Item not found');
                }

                $items[] = ['item' => $item, 'customer' => $customer];
                continue;
            }

            $recipe = Recipe::where('uuid', $entry['recipe_uuid'])->where('restaurant_uuid', $restaurant->uuid)->first();
            if ($recipe === null) {
                return $this->respond(Response::HTTP_NOT_FOUND, 'Recipe not found');
            }

            $items[] = ['recipe' => $recipe, 'customer' => $customer];
        }

        return $items;
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
     * Gets the full service state: the kitchen, its staff and work areas, and the orders being served
     * @param Restaurant $restaurant The restaurant
     * @return array The service state
     */
    private function getState(Restaurant $restaurant): array
    {
        return $this->kitchenService->getState($restaurant)
            + $this->workService->describe($restaurant)
            + ['orders' => $this->orderService->describeOrders($restaurant)];
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
