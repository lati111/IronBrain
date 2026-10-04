<?php

namespace App\Service\DnD\Restaurant;

use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\OrderItem;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Sale;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Table;
use App\Models\DnD\Restaurant\Work;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Orders at the tables: placing and editing them, bringing their portions, the portions being eaten, and finishing
 * or cancelling them. Taking an order and bringing a portion are jobs for the table's waiter, which take time as the
 * clock moves. An order is paid into the shift total once it is finished
 */
class OrderService
{
    /** @var int How long taking an order takes the waiter for each customer seated at the table, in minutes */
    public const int ORDER_MINUTES_PER_CUSTOMER = 2;
    /** @var int How long bringing a plated portion to its table takes the waiter, in minutes */
    public const int DELIVERY_MINUTES = 2;

    public function __construct(
        private readonly KitchenService $kitchenService,
        private readonly WorkService $workService,
    )
    {
    }

    /**
     * Describes the orders that are still being served or eaten, with how long ago they were placed and fulfilled
     * @param Restaurant $restaurant The restaurant
     * @return array The orders
     */
    public function describeOrders(Restaurant $restaurant): array
    {
        $now = $restaurant->getNow();

        return Order::where('restaurant_uuid', $restaurant->uuid)
            ->whereIn('status', Order::ACTIVE_STATUSES)
            ->with(['Items.Customer', 'Items.ServedMeal'])
            ->orderBy('ordered_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Order $order) => [
                'uuid' => $order->uuid,
                'table_uuid' => $order->table_uuid,
                'status' => $order->status,
                'total_price' => $order->total_price,
                'tip' => $order->tip,
                'discount' => $order->discount,
                'payable' => $order->getPayable(),
                'ordered_at' => $order->ordered_at->format('H:i'),
                'ordered_minutes_ago' => $this->minutesSince($order->ordered_at, $now),
                'fulfilled_minutes_ago' => $order->fulfilled_at !== null ? $this->minutesSince($order->fulfilled_at, $now) : null,
                'items' => $order->Items->sortBy('created_at')->map(fn (OrderItem $item) => [
                    'uuid' => $item->uuid,
                    'recipe_uuid' => $item->recipe_uuid,
                    'name' => $item->name,
                    'customer_uuid' => $item->customer_uuid,
                    'customer_name' => $item->Customer?->name,
                    'price' => $item->price,
                    'served' => $item->served_at !== null,
                    // A plate set aside for it, which the waiter is bringing
                    'on_the_way' => $item->served_at === null && $item->ServedMeal !== null,
                    'consumed' => $item->consumed_at !== null,
                ])->values()->all(),
            ])->values()->all();
    }

    //| Placing & editing

    /**
     * Places an order at a table, which its waiter then takes, a while for each customer seated there. Each item is a
     * single portion of a recipe, for one customer seated at the table or for the table as a whole, and becomes an
     * entry of its own, at the recipe's sale price when it is on sale. It is paid for once finished
     * @param Restaurant $restaurant The restaurant
     * @param Table $table The table ordering
     * @param array $items The items, each with a recipe and the customer it is for, or null for the table
     * @return Order The placed order
     * @throws InvalidArgumentException Thrown when nothing is ordered, nobody waits the table, or a customer isn't seated there
     */
    public function placeOrder(Restaurant $restaurant, Table $table, array $items): Order
    {
        if ($items === []) {
            throw new InvalidArgumentException('Nothing was ordered');
        }

        if ($table->employee_uuid === null) {
            throw new InvalidArgumentException('Nobody is waiting ' . $table->name);
        }

        $sales = $restaurant->getSalePrices();

        return DB::transaction(function () use ($restaurant, $table, $items, $sales) {
            $order = new Order();
            $order->restaurant_uuid = $restaurant->uuid;
            $order->table_uuid = $table->uuid;
            $order->service_uuid = $restaurant->getCurrentService()?->uuid;
            $order->status = Order::STATUS_TAKING;
            $order->ordered_at = $restaurant->getNow();
            $order->total_price = 0;
            $order->save();

            foreach ($items as ['recipe' => $recipe, 'customer' => $customer]) {
                $this->addItem($order, $table, $recipe, $customer, $sales);
            }

            $order->recalculate($restaurant->getNow());
            $order->save();

            $minutes = $table->Customers()->count() * self::ORDER_MINUTES_PER_CUSTOMER;
            $this->workService->queueOrderTaking($restaurant, $order, $table, $minutes);

            return $order->refresh();
        });
    }

    /**
     * Edits the items of an order. Items that are kept can be given to another customer, items that aren't served
     * yet can be taken off, and new items can be added at the current price
     * @param Restaurant $restaurant The restaurant
     * @param Order $order The order
     * @param array $entries The items the order should have: existing ones with their item and customer, and new
     *                       ones with their recipe and customer
     * @throws InvalidArgumentException Thrown when the order is done, a served item is taken off, nothing is left, or
     *                                  a customer isn't seated at the table
     */
    public function editOrder(Restaurant $restaurant, Order $order, array $entries): void
    {
        $this->assertActive($order);
        if ($entries === []) {
            throw new InvalidArgumentException('Nothing was ordered');
        }

        $table = $order->Table;
        $sales = $restaurant->getSalePrices();

        DB::transaction(function () use ($restaurant, $order, $entries, $table, $sales) {
            $kept = [];
            foreach ($entries as $entry) {
                /** @var OrderItem|null $item */
                $item = $entry['item'] ?? null;
                if ($item === null) {
                    $kept[] = $this->addItem($order, $table, $entry['recipe'], $entry['customer'], $sales)->uuid;
                    continue;
                }

                $this->assertSeated($entry['customer'], $table);
                $item->customer_uuid = $entry['customer']?->uuid;
                $item->save();
                $kept[] = $item->uuid;
            }

            foreach ($order->Items()->whereNotIn('uuid', $kept)->get() as $removed) {
                // What has been brought to the table, or is on its way there, can't be taken off the order anymore
                if ($removed->served_at !== null) {
                    throw new InvalidArgumentException($removed->name . ' has already been served');
                }
                if ($removed->ServedMeal()->exists()) {
                    throw new InvalidArgumentException($removed->name . ' is on its way');
                }
                $removed->delete();
            }

            $order->recalculate($restaurant->getNow());
            $order->save();
        });
    }

    /**
     * Sets the tip the table gives and the discount they get, which change what they pay once the order is finished
     * @param Order $order The order
     * @param float $tip The tip, in bits
     * @param float $discount The discount, in bits
     * @throws InvalidArgumentException Thrown when the order is done
     */
    public function setTipAndDiscount(Order $order, float $tip, float $discount): void
    {
        $this->assertActive($order);

        $order->tip = round($tip, 2);
        $order->discount = round($discount, 2);
        $order->save();
    }

    //| Serving & eating

    /**
     * Has the table's waiter bring a plated portion there, for the oldest entry of that recipe the table still waits
     * for that has no plate on its way yet. The plate is set aside for that entry straight away, and serves it once
     * the waiter arrives. Once every entry of an order is served, the order is fulfilled
     * @param Restaurant $restaurant The restaurant
     * @param ServedMeal $plate The plated portion
     * @param Table $table The table to bring it to
     * @throws InvalidArgumentException Thrown when it was already brought, nobody waits the table, or it isn't waiting for it
     */
    public function deliverPlate(Restaurant $restaurant, ServedMeal $plate, Table $table): void
    {
        if ($plate->order_item_uuid !== null) {
            throw new InvalidArgumentException('This plate has already been served');
        }

        if ($table->employee_uuid === null) {
            throw new InvalidArgumentException('Nobody is waiting ' . $table->name);
        }

        DB::transaction(function () use ($restaurant, $plate, $table) {
            $item = OrderItem::query()
                ->select(OrderItem::TABLE_NAME . '.*')
                ->join(Order::TABLE_NAME, Order::TABLE_NAME . '.uuid', '=', OrderItem::TABLE_NAME . '.order_uuid')
                ->where(Order::TABLE_NAME . '.table_uuid', $table->uuid)
                ->where(Order::TABLE_NAME . '.status', Order::STATUS_OPEN)
                ->where(OrderItem::TABLE_NAME . '.recipe_uuid', $plate->recipe_uuid)
                ->whereNull(OrderItem::TABLE_NAME . '.served_at')
                ->whereNotExists(fn ($query) => $query->from(ServedMeal::TABLE_NAME)
                    ->whereColumn(ServedMeal::TABLE_NAME . '.order_item_uuid', OrderItem::TABLE_NAME . '.uuid'))
                ->orderBy(Order::TABLE_NAME . '.ordered_at')
                ->orderBy(OrderItem::TABLE_NAME . '.created_at')
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                throw new InvalidArgumentException(sprintf('%s isn\'t waiting for %s', $table->name, $plate->Recipe->name));
            }

            $plate->order_item_uuid = $item->uuid;
            $plate->save();

            $this->workService->queueDelivery($restaurant, $plate, $table, self::DELIVERY_MINUTES);
        });
    }

    /**
     * Marks a served portion as eaten, which returns its serving equipment dirty. The order stays until it is finished
     * @param Restaurant $restaurant The restaurant
     * @param OrderItem $item The portion
     * @throws InvalidArgumentException Thrown when the order is done, or the portion isn't served or already eaten
     */
    public function consumeItem(Restaurant $restaurant, OrderItem $item): void
    {
        $this->assertActive($item->Order);
        if ($item->served_at === null) {
            throw new InvalidArgumentException($item->name . ' hasn\'t been served yet');
        }
        if ($item->consumed_at !== null) {
            throw new InvalidArgumentException($item->name . ' has already been eaten');
        }

        DB::transaction(function () use ($restaurant, $item) {
            $this->clearPlates($restaurant, [$item->uuid]);

            $item->consumed_at = $restaurant->getNow();
            $item->save();
        });
    }

    /**
     * Marks every served portion of an order that isn't eaten yet as eaten
     * @param Restaurant $restaurant The restaurant
     * @param Order $order The order
     * @throws InvalidArgumentException Thrown when the order is done, or nothing served is left to eat
     */
    public function consumeOrder(Restaurant $restaurant, Order $order): void
    {
        $this->assertActive($order);

        $items = $order->Items()->whereNotNull('served_at')->whereNull('consumed_at')->get();
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('Nothing served is left to eat');
        }

        DB::transaction(function () use ($restaurant, $items) {
            foreach ($items as $item) {
                $this->consumeItem($restaurant, $item);
            }
        });
    }

    //| Finishing & cancelling

    /**
     * Finishes an order, as the table pays and is done. What they pay goes into the shift total, and the serving
     * equipment of anything still on the table returns dirty
     * @param Restaurant $restaurant The restaurant
     * @param Order $order The order
     * @throws InvalidArgumentException Thrown when the order is already done
     */
    public function finishOrder(Restaurant $restaurant, Order $order): void
    {
        $this->assertActive($order);

        DB::transaction(function () use ($restaurant, $order) {
            $this->clearTable($restaurant, $order);

            // Orders placed before they were paid on finishing are already in the shift total
            if ($order->paid_at === null) {
                $restaurant->shift_total = round($restaurant->shift_total + $order->getPayable(), 2);
                $restaurant->save();
                $order->paid_at = $restaurant->getNow();
            }

            $order->status = Order::STATUS_FINISHED;
            $order->save();
        });
    }

    /**
     * Cancels an order, so nothing is paid for it. The serving equipment of anything on the table returns dirty
     * @param Restaurant $restaurant The restaurant
     * @param Order $order The order
     * @throws InvalidArgumentException Thrown when the order is already done
     */
    public function cancelOrder(Restaurant $restaurant, Order $order): void
    {
        $this->assertActive($order);

        DB::transaction(function () use ($restaurant, $order) {
            $this->clearTable($restaurant, $order);

            // Orders placed before they were paid on finishing are already in the shift total, which they leave again
            if ($order->paid_at !== null) {
                $restaurant->shift_total = round($restaurant->shift_total - $order->getPayable(), 2);
                $restaurant->save();
                $order->paid_at = null;
            }

            $order->status = Order::STATUS_CANCELLED;
            $order->save();
        });
    }

    //| Sales

    /**
     * Puts a recipe on sale for the ongoing service, at a price that replaces its usual one until the service ends
     * @param Restaurant $restaurant The restaurant
     * @param Recipe $recipe The recipe
     * @param float $price The price during the service
     * @throws InvalidArgumentException Thrown when no service is ongoing
     */
    public function setSale(Restaurant $restaurant, Recipe $recipe, float $price): void
    {
        $service = $restaurant->getCurrentService();
        if ($service === null) {
            throw new InvalidArgumentException('No service is ongoing');
        }

        $sale = Sale::where('service_uuid', $service->uuid)->where('recipe_uuid', $recipe->uuid)->first() ?? new Sale();
        $sale->service_uuid = $service->uuid;
        $sale->recipe_uuid = $recipe->uuid;
        $sale->price = round($price, 2);
        $sale->save();
    }

    /**
     * Takes a recipe off sale, so it is ordered at its usual price again
     * @param Restaurant $restaurant The restaurant
     * @param Recipe $recipe The recipe
     */
    public function removeSale(Restaurant $restaurant, Recipe $recipe): void
    {
        $service = $restaurant->getCurrentService();
        if ($service !== null) {
            Sale::where('service_uuid', $service->uuid)->where('recipe_uuid', $recipe->uuid)->delete();
        }
    }

    //| Helpers

    /**
     * Adds a portion of a recipe to an order, at its sale price when it is on sale
     * @param array<string, float> $sales The sale prices, keyed by recipe uuid
     * @throws InvalidArgumentException Thrown when the customer isn't seated at the table
     */
    private function addItem(Order $order, ?Table $table, Recipe $recipe, ?Customer $customer, array $sales): OrderItem
    {
        $this->assertSeated($customer, $table);

        $item = new OrderItem();
        $item->order_uuid = $order->uuid;
        $item->recipe_uuid = $recipe->uuid;
        $item->customer_uuid = $customer?->uuid;
        $item->name = $recipe->name;
        $item->price = $sales[$recipe->uuid] ?? $recipe->price;
        $item->save();

        return $item;
    }

    /**
     * Clears the table of an order that is done: the plates on it or on their way are taken away, returning their
     * serving equipment dirty, and the waiter stops taking it
     */
    private function clearTable(Restaurant $restaurant, Order $order): void
    {
        $this->clearPlates($restaurant, $order->Items()->pluck('uuid')->all());
        Work::where('order_uuid', $order->uuid)->delete();
    }

    /**
     * Takes the plates brought for order entries off the table, returning their serving equipment dirty. The jobs of
     * bringing those still on their way go with them
     * @param string[] $itemUuids The uuids of the order entries
     */
    private function clearPlates(Restaurant $restaurant, array $itemUuids): void
    {
        foreach (ServedMeal::whereIn('order_item_uuid', $itemUuids)->get() as $plate) {
            $this->kitchenService->releaseEquipment($restaurant, $plate->equipment ?? [], dirty: true);
            $plate->delete();
        }
    }

    /**
     * @throws InvalidArgumentException Thrown when the order is finished or cancelled
     */
    private function assertActive(Order $order): void
    {
        if (!$order->isActive()) {
            throw new InvalidArgumentException('This order is already ' . $order->status);
        }
    }

    /**
     * @throws InvalidArgumentException Thrown when a customer isn't seated at the table
     */
    private function assertSeated(?Customer $customer, ?Table $table): void
    {
        if ($customer !== null && $table !== null && $customer->table_uuid !== $table->uuid) {
            throw new InvalidArgumentException($customer->name . ' isn\'t seated at ' . $table->name);
        }
    }

    /**
     * Gets how many whole minutes have passed since a moment
     */
    private function minutesSince(Carbon $moment, Carbon $now): int
    {
        return max(0, intdiv($now->getTimestamp() - $moment->getTimestamp(), 60));
    }
}
