<?php
namespace App\Http\Api\Modules\DnD\Restaurant;

use App\Http\Api\Modules\DnD\Restaurant\Traits\ResolvesRestaurant;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Table;
use App\Service\DnD\Restaurant\OrderService;
use App\Service\DnD\Restaurant\ServiceStateService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The orders at the tables during service: placing and editing them, bringing plated portions to them, the portions
 * being eaten, and finishing or cancelling them
 */
class OrderApi extends AbstractServiceApi
{
    use ResolvesRestaurant;

    public function __construct(ServiceStateService $serviceStateService, private readonly OrderService $orderService)
    {
        parent::__construct($serviceStateService);
    }

    //| Placing & editing

    /**
     * Places an order at a table. Sent as JSON, with an item for each portion: a recipe, and the one customer it is
     * for, or none when it is for the table
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return JsonResponse The updated service state in JSON format
     */
    public function placeOrder(Request $request, string $restaurant_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'table_uuid'            => 'required|string|max:255',
            'items'                 => 'required|array|min:1|max:200',
            'items.*.recipe_uuid'   => 'required|string|max:255',
            'items.*.customer_uuid' => 'nullable|string|max:255',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $table = $this->findInRestaurant(Table::class, $restaurant, $request->get('table_uuid'));
        if ($table === null) {
            return $this->notFound('Table');
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
        $invalid = $this->validateRequest($request, [
            'items'                 => 'required|array|min:1|max:200',
            'items.*.uuid'          => 'nullable|string|max:255',
            'items.*.recipe_uuid'   => 'required_without:items.*.uuid|nullable|string|max:255',
            'items.*.customer_uuid' => 'nullable|string|max:255',
        ]);
        if ($invalid !== null) {
            return $invalid;
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
        $invalid = $this->validateRequest($request, [
            'tip'      => 'required|numeric|min:0|max:99999999',
            'discount' => 'required|numeric|min:0|max:99999999',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        return $this->withOrder($restaurant_uuid, $order_uuid, fn (Restaurant $restaurant, Order $order) => $this->attempt(
            $restaurant, 'Tip and discount saved',
            fn () => $this->orderService->setTipAndDiscount($order, (float) $request->get('tip'), (float) $request->get('discount'))
        ));
    }

    //| Serving & eating

    /**
     * Brings a plated portion to a table that ordered it
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @param string $plate_uuid The uuid of the plated portion
     * @return JsonResponse The updated service state in JSON format
     */
    public function deliverPlate(Request $request, string $restaurant_uuid, string $plate_uuid): JsonResponse
    {
        $invalid = $this->validateRequest($request, [
            'table_uuid' => 'required|string|max:255',
        ]);
        if ($invalid !== null) {
            return $invalid;
        }

        $restaurant = $this->getRestaurant($restaurant_uuid);
        if ($restaurant === null) {
            return $this->notFound('Restaurant');
        }

        $plate = $this->findInRestaurant(ServedMeal::class, $restaurant, $plate_uuid);
        if ($plate === null) {
            return $this->notFound('Plate');
        }

        $table = $this->findInRestaurant(Table::class, $restaurant, $request->get('table_uuid'));
        if ($table === null) {
            return $this->notFound('Table');
        }

        return $this->attempt($restaurant, 'On its way to ' . $table->name, fn () => $this->orderService->deliverPlate($restaurant, $plate, $table));
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
                return $this->notFound('Item');
            }

            return $this->attempt($restaurant, $item->name . ' consumed', fn () => $this->orderService->consumeItem($restaurant, $item));
        });
    }

    //| Finishing & cancelling

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

    //| Helpers

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
            return $this->notFound('Restaurant');
        }

        $order = $this->findInRestaurant(Order::class, $restaurant, $order_uuid);
        if ($order === null) {
            return $this->notFound('Order');
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
                $customer = $this->findInRestaurant(Customer::class, $restaurant, $entry['customer_uuid']);
                if ($customer === null) {
                    return $this->notFound('Customer');
                }
            }

            if ($order !== null && ($entry['uuid'] ?? null) !== null) {
                $item = $order->Items()->where('uuid', $entry['uuid'])->first();
                if ($item === null) {
                    return $this->notFound('Item');
                }

                $items[] = ['item' => $item, 'customer' => $customer];
                continue;
            }

            $recipe = $this->findInRestaurant(Recipe::class, $restaurant, $entry['recipe_uuid']);
            if ($recipe === null) {
                return $this->notFound('Recipe');
            }

            $items[] = ['recipe' => $recipe, 'customer' => $customer];
        }

        return $items;
    }
}
