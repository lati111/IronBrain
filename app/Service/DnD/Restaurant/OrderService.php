<?php

namespace App\Service\DnD\Restaurant;

use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\OrderItem;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\Table;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    public function __construct(private readonly KitchenService $kitchenService)
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
            ->whereIn('status', [Order::STATUS_OPEN, Order::STATUS_FULFILLED])
            ->with('Items.Customer')
            ->orderBy('ordered_at')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Order $order) => [
                'uuid' => $order->uuid,
                'table_uuid' => $order->table_uuid,
                'status' => $order->status,
                'total_price' => $order->total_price,
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
                ])->values()->all(),
            ])->values()->all();
    }

    /**
     * Places an order at a table. Each item is a single portion of a recipe, for one customer seated at the table or
     * for the table as a whole, and becomes an entry of its own. The order's price is added to the restaurant's
     * shift total
     * @param Restaurant $restaurant The restaurant
     * @param Table $table The table ordering
     * @param array $items The items, each with a recipe and the customer it is for, or null for the table
     * @return Order The placed order
     * @throws InvalidArgumentException Thrown when nothing is ordered, or a customer isn't seated at the table
     */
    public function placeOrder(Restaurant $restaurant, Table $table, array $items): Order
    {
        if ($items === []) {
            throw new InvalidArgumentException('Nothing was ordered');
        }

        return DB::transaction(function () use ($restaurant, $table, $items) {
            $order = new Order();
            $order->restaurant_uuid = $restaurant->uuid;
            $order->table_uuid = $table->uuid;
            $order->service_uuid = $restaurant->getCurrentService()?->uuid;
            $order->status = Order::STATUS_OPEN;
            $order->ordered_at = $restaurant->getNow();
            $order->total_price = 0;
            $order->save();

            $total = 0;
            foreach ($items as ['recipe' => $recipe, 'customer' => $customer]) {
                /** @var Recipe $recipe */
                /** @var Customer|null $customer */
                if ($customer !== null && $customer->table_uuid !== $table->uuid) {
                    throw new InvalidArgumentException($customer->name . ' isn\'t seated at ' . $table->name);
                }

                $item = new OrderItem();
                $item->order_uuid = $order->uuid;
                $item->recipe_uuid = $recipe->uuid;
                $item->customer_uuid = $customer?->uuid;
                $item->name = $recipe->name;
                $item->price = $recipe->price;
                $item->save();

                $total += $recipe->price;
            }

            $order->total_price = round($total, 2);
            $order->save();

            $restaurant->shift_total = round($restaurant->shift_total + $total, 2);
            $restaurant->save();

            return $order;
        });
    }

    /**
     * Brings a plated portion to a table, serving the oldest entry of that recipe the table still waits for. Once
     * every entry of an order is served, the order is fulfilled
     * @param Restaurant $restaurant The restaurant
     * @param ServedMeal $plate The plated portion
     * @param Table $table The table to bring it to
     * @throws InvalidArgumentException Thrown when it was already served, or the table isn't waiting for it
     */
    public function deliverPlate(Restaurant $restaurant, ServedMeal $plate, Table $table): void
    {
        if ($plate->order_item_uuid !== null) {
            throw new InvalidArgumentException('This plate has already been served');
        }

        DB::transaction(function () use ($restaurant, $plate, $table) {
            $item = OrderItem::query()
                ->select(OrderItem::TABLE_NAME . '.*')
                ->join(Order::TABLE_NAME, Order::TABLE_NAME . '.uuid', '=', OrderItem::TABLE_NAME . '.order_uuid')
                ->where(Order::TABLE_NAME . '.table_uuid', $table->uuid)
                ->where(Order::TABLE_NAME . '.status', Order::STATUS_OPEN)
                ->where(OrderItem::TABLE_NAME . '.recipe_uuid', $plate->recipe_uuid)
                ->whereNull(OrderItem::TABLE_NAME . '.served_at')
                ->orderBy(Order::TABLE_NAME . '.ordered_at')
                ->orderBy(OrderItem::TABLE_NAME . '.created_at')
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                throw new InvalidArgumentException(sprintf('%s isn\'t waiting for %s', $table->name, $plate->Recipe->name));
            }

            $now = $restaurant->getNow();
            $item->served_at = $now;
            $item->save();

            $plate->order_item_uuid = $item->uuid;
            $plate->save();

            $order = $item->Order;
            if ($order->Items()->whereNull('served_at')->doesntExist()) {
                $order->status = Order::STATUS_FULFILLED;
                $order->fulfilled_at = $now;
                $order->save();
            }
        });
    }

    /**
     * Finishes a fulfilled order once its meal has been eaten, which makes all its serving equipment dirty
     * @param Restaurant $restaurant The restaurant
     * @param Order $order The order
     * @throws InvalidArgumentException Thrown when the order hasn't been fully served yet
     */
    public function consumeOrder(Restaurant $restaurant, Order $order): void
    {
        if ($order->status !== Order::STATUS_FULFILLED) {
            throw new InvalidArgumentException('Only fully served orders can be consumed');
        }

        DB::transaction(function () use ($restaurant, $order) {
            $plates = ServedMeal::whereIn('order_item_uuid', $order->Items()->pluck('uuid'))->get();
            foreach ($plates as $plate) {
                $this->kitchenService->releaseEquipment($restaurant, $plate->equipment ?? [], dirty: true);
                $plate->delete();
            }

            $order->status = Order::STATUS_FINISHED;
            $order->save();
        });
    }

    /**
     * Gets how many whole minutes have passed since a moment
     */
    private function minutesSince(Carbon $moment, Carbon $now): int
    {
        return max(0, intdiv($now->getTimestamp() - $moment->getTimestamp(), 60));
    }
}
