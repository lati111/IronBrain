<?php

namespace App\Service\DnD\Restaurant;

use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\OrderItem;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServedMeal;
use App\Models\DnD\Restaurant\ServiceHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Starting and ending services, each of which is logged in the service history */
class ShiftService
{
    public function __construct(private readonly KitchenService $kitchenService)
    {
    }

    /**
     * Starts a service at a time on the restaurant's current date, with a fresh shift total
     * @param Restaurant $restaurant The restaurant
     * @param string $time The time the service starts at, as H:i
     * @return ServiceHistory The log of the service
     * @throws InvalidArgumentException Thrown when a service is already ongoing
     */
    public function startService(Restaurant $restaurant, string $time): ServiceHistory
    {
        if ($restaurant->isInService()) {
            throw new InvalidArgumentException('A service is already ongoing');
        }

        return DB::transaction(function () use ($restaurant, $time) {
            $restaurant->current_time = $time;
            $restaurant->shift_total = 0;
            $restaurant->save();

            $service = new ServiceHistory();
            $service->restaurant_uuid = $restaurant->uuid;
            $service->started_at = $restaurant->getNow();
            $service->profit = 0;
            $service->save();

            return $service;
        });
    }

    /**
     * Summarises the ongoing service: what has been sold, and the profit so far
     * @param Restaurant $restaurant The restaurant
     * @return array The summary, with the items sold grouped by name
     */
    public function getSummary(Restaurant $restaurant): array
    {
        $service = $restaurant->getCurrentService();

        $items = [];
        if ($service !== null) {
            $items = OrderItem::query()
                ->join(Order::TABLE_NAME, Order::TABLE_NAME . '.uuid', '=', OrderItem::TABLE_NAME . '.order_uuid')
                ->where(Order::TABLE_NAME . '.service_uuid', $service->uuid)
                ->groupBy(OrderItem::TABLE_NAME . '.name')
                ->orderBy(OrderItem::TABLE_NAME . '.name')
                ->selectRaw(OrderItem::TABLE_NAME . '.name, COUNT(*) as amount, SUM(' . OrderItem::TABLE_NAME . '.price) as total')
                ->get()
                ->map(fn ($row) => [
                    'name' => $row->name,
                    'amount' => (int) $row->amount,
                    'total' => round((float) $row->total, 2),
                ])->all();
        }

        return [
            'started_at' => $service?->started_at->format('H:i') ?? substr($restaurant->opening_time, 0, 5),
            'time' => $restaurant->getNow()->format('H:i'),
            'items' => $items,
            'profit' => $restaurant->shift_total,
        ];
    }

    /**
     * Ends the ongoing service. Its profit is added to the restaurant's money and logged in the service history,
     * orders that are still going are finished, all dirty equipment is cleaned, and the restaurant's current time is
     * cleared
     * @param Restaurant $restaurant The restaurant
     * @return array The summary of the service, as given by getSummary
     * @throws InvalidArgumentException Thrown when no service is ongoing
     */
    public function endService(Restaurant $restaurant): array
    {
        if (!$restaurant->isInService()) {
            throw new InvalidArgumentException('No service is ongoing');
        }

        $summary = $this->getSummary($restaurant);

        DB::transaction(function () use ($restaurant) {
            $now = $restaurant->getNow();

            // Services started before they were logged get a log from the opening time
            $service = $restaurant->getCurrentService();
            if ($service === null) {
                $service = new ServiceHistory();
                $service->restaurant_uuid = $restaurant->uuid;
                $service->started_at = Carbon::parse($restaurant->date->format('Y-m-d') . ' ' . $restaurant->opening_time);
            }

            $service->ended_at = $now;
            $service->profit = $restaurant->shift_total;
            $service->save();

            $this->finishOrders($restaurant);

            // Everything is washed up after closing, which takes no time off the clock as the service is over
            Equipment::where('restaurant_uuid', $restaurant->uuid)->update(['amount_dirty' => 0]);

            $restaurant->addMoney($restaurant->shift_total);
            $restaurant->shift_total = 0;
            $restaurant->current_time = null;
            $restaurant->save();
        });

        return $summary;
    }

    /**
     * Finishes the orders that are still being served or eaten, as their customers leave. The serving equipment of
     * the portions brought to them becomes dirty
     * @param Restaurant $restaurant The restaurant
     */
    private function finishOrders(Restaurant $restaurant): void
    {
        $orders = Order::where('restaurant_uuid', $restaurant->uuid)
            ->whereIn('status', [Order::STATUS_OPEN, Order::STATUS_FULFILLED])
            ->get();

        foreach ($orders as $order) {
            $plates = ServedMeal::whereIn('order_item_uuid', $order->Items()->pluck('uuid'))->get();
            foreach ($plates as $plate) {
                $this->kitchenService->releaseEquipment($restaurant, $plate->equipment ?? [], dirty: true);
                $plate->delete();
            }

            $order->status = Order::STATUS_FINISHED;
            $order->save();
        }
    }
}
