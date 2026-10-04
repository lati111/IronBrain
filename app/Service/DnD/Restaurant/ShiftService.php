<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Equipment;
use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\OrderItem;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\ServiceHistory;
use App\Models\DnD\Restaurant\Table;
use App\Models\DnD\Restaurant\Work;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Starting and ending services, each of which is logged in the service history */
class ShiftService
{
    public function __construct(private readonly OrderService $orderService)
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
                ->where(Order::TABLE_NAME . '.status', '!=', Order::STATUS_CANCELLED)
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
     * Ends the ongoing service. Orders that are still going are finished and paid for, after which the profit is
     * added to the restaurant's money and logged in the service history. All dirty equipment is cleaned, the
     * employees stop working, and the restaurant's current time is cleared
     * @param Restaurant $restaurant The restaurant
     * @return array The summary of the service, as given by getSummary
     * @throws InvalidArgumentException Thrown when no service is ongoing
     */
    public function endService(Restaurant $restaurant): array
    {
        if (!$restaurant->isInService()) {
            throw new InvalidArgumentException('No service is ongoing');
        }

        return DB::transaction(function () use ($restaurant) {
            $this->finishOrders($restaurant);
            $summary = $this->getSummary($restaurant);
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

            // Everything is washed up after closing, which takes no time off the clock as the service is over
            Equipment::where('restaurant_uuid', $restaurant->uuid)->update(['amount_dirty' => 0]);
            Work::where('restaurant_uuid', $restaurant->uuid)
                ->whereIn('station', [WorkStation::CLEANING->value, WorkStation::WAITING->value])
                ->delete();

            // The staff goes home, while prep that is still queued waits for the next service
            Employee::where('restaurant_uuid', $restaurant->uuid)->update(['station' => null]);
            Table::where('restaurant_uuid', $restaurant->uuid)->update(['employee_uuid' => null]);

            $restaurant->addMoney($restaurant->shift_total);
            $restaurant->shift_total = 0;
            $restaurant->current_time = null;
            $restaurant->save();

            return $summary;
        });
    }

    /**
     * Finishes the orders that are still being served or eaten, as their customers pay and leave
     * @param Restaurant $restaurant The restaurant
     */
    private function finishOrders(Restaurant $restaurant): void
    {
        $orders = Order::where('restaurant_uuid', $restaurant->uuid)->whereIn('status', Order::ACTIVE_STATUSES)->get();
        foreach ($orders as $order) {
            $this->orderService->finishOrder($restaurant, $order);
        }
    }
}
