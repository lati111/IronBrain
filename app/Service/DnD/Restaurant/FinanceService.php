<?php

namespace App\Service\DnD\Restaurant;

use App\Models\DnD\Restaurant\Order;
use App\Models\DnD\Restaurant\OrderItem;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The restaurant's finances: the log of money going in and out, and what each transaction was made up of
 */
class FinanceService
{
    /** @var string What portions ordered for the table as a whole, or for customers that no longer exist, count under */
    private const string UNASSIGNED = 'Unassigned';

    /**
     * Lists the restaurant's transactions, the most recent first
     * @param Restaurant $restaurant The restaurant
     * @return array The transactions, each with when it happened and how much went in
     */
    public function getTransactions(Restaurant $restaurant): array
    {
        return Transaction::where('restaurant_uuid', $restaurant->uuid)
            ->orderByDesc('occurred_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Transaction $transaction) => $this->describeTransaction($transaction))
            ->values()->all();
    }

    /**
     * Adds a transaction to the log by hand, at the restaurant's current time, paying the amount into the restaurant
     * or taking it out of its money when it is negative
     * @param Restaurant $restaurant The restaurant
     * @param string $title What the transaction is shown as
     * @param float $amount How much money went in, in bits, negative when it went out
     * @param string|null $description More about the transaction, if anything
     * @return Transaction The transaction
     * @throws InvalidArgumentException Thrown when more money goes out than the restaurant has
     */
    public function addTransaction(Restaurant $restaurant, string $title, float $amount, ?string $description): Transaction
    {
        return DB::transaction(function () use ($restaurant, $title, $amount, $description) {
            $amount = round($amount, 2);
            $amount >= 0 ? $restaurant->addMoney($amount) : $restaurant->spendMoney(-$amount);
            $restaurant->save();

            $transaction = new Transaction();
            $transaction->restaurant_uuid = $restaurant->uuid;
            $transaction->type = Transaction::TYPE_MANUAL;
            $transaction->title = $title;
            $transaction->description = $description;
            $transaction->amount = $amount;
            $transaction->occurred_at = $restaurant->getNow();
            $transaction->save();

            return $transaction;
        });
    }

    /**
     * Gets the details of a transaction. The profit of a service is broken down into its orders, the profit per
     * customer, and how many of each recipe were sold
     * @param Transaction $transaction The transaction
     * @return array The transaction, with its breakdown when there is one
     */
    public function getDetails(Transaction $transaction): array
    {
        $details = $this->describeTransaction($transaction);
        $service = $transaction->Service;
        if ($transaction->type !== Transaction::TYPE_SERVICE || $service === null) {
            return $details;
        }

        $orders = Order::where('service_uuid', $service->uuid)
            ->with(['Items.Customer', 'Table'])
            ->orderBy('ordered_at')
            ->orderBy('created_at')
            ->get();
        // Cancelled orders are listed, though nothing was paid for them
        $paid = $orders->filter(fn (Order $order) => $order->status !== Order::STATUS_CANCELLED);

        return $details + [
            'service' => [
                'started_at' => $service->started_at->format('Y-m-d H:i'),
                'ended_at' => $service->ended_at?->format('Y-m-d H:i'),
                'profit' => $service->profit,
            ],
            'orders' => $orders->map(fn (Order $order) => $this->describeOrder($order))->values()->all(),
            'customers' => $this->getCustomerProfits($paid),
            'recipes' => $this->getRecipesSold($paid),
        ];
    }

    /**
     * Describes a transaction as listed
     */
    private function describeTransaction(Transaction $transaction): array
    {
        return [
            'uuid' => $transaction->uuid,
            'type' => $transaction->type,
            'title' => $transaction->title,
            'description' => $transaction->description,
            'amount' => $transaction->amount,
            'occurred_at' => $transaction->occurred_at->format('Y-m-d H:i'),
        ];
    }

    /**
     * Describes an order of a service, with what was ordered and what was paid for it
     */
    private function describeOrder(Order $order): array
    {
        $cancelled = $order->status === Order::STATUS_CANCELLED;

        return [
            'uuid' => $order->uuid,
            'table' => $order->Table?->name,
            'status' => $order->status,
            'ordered_at' => $order->ordered_at->format('H:i'),
            'items' => $order->Items->sortBy('created_at')->map(fn (OrderItem $item) => [
                'name' => $item->name,
                'price' => $item->price,
                'customer' => $item->Customer?->name,
            ])->values()->all(),
            'total_price' => $order->total_price,
            'discount' => $order->discount,
            'tip' => $order->tip,
            'paid' => $cancelled ? 0.0 : $order->getPayable(),
        ];
    }

    /**
     * Works out what each customer brought in: the price of what they ordered, with the order's tip and discount
     * shared out over its portions by price. Portions for the table as a whole count as unassigned
     * @param Collection<Order> $orders The orders that were paid for, with their items and customers
     * @return array The customers, each with how many portions they had and the profit, the most profitable first
     */
    private function getCustomerProfits(Collection $orders): array
    {
        $customers = [];
        foreach ($orders as $order) {
            $items = $order->Items;
            $paid = $order->getPayable();
            if ($items->isEmpty()) {
                continue;
            }

            $total = $items->sum('price');
            foreach ($items as $item) {
                // Free portions share in the tip and discount evenly when nothing in the order had a price
                $share = $total > 0 ? $item->price / $total : 1 / $items->count();
                $name = $item->Customer?->name ?? self::UNASSIGNED;
                $key = $item->customer_uuid ?? self::UNASSIGNED;

                $customers[$key] ??= ['name' => $name, 'portions' => 0, 'profit' => 0.0];
                $customers[$key]['portions']++;
                $customers[$key]['profit'] += $paid * $share;
            }
        }

        return collect($customers)
            ->map(fn (array $customer) => ['name' => $customer['name'], 'portions' => $customer['portions'], 'profit' => round($customer['profit'], 2)])
            ->sortBy([['profit', 'desc'], ['name', 'asc']])
            ->values()->all();
    }

    /**
     * Counts how many portions of each recipe were sold, by the name they were ordered under
     * @param Collection<Order> $orders The orders that were paid for, with their items
     * @return array The recipes, each with how many were sold and what they were worth, the most sold first
     */
    private function getRecipesSold(Collection $orders): array
    {
        return $orders->flatMap(fn (Order $order) => $order->Items)
            ->groupBy('name')
            ->map(fn (Collection $items, string $name) => [
                'name' => $name,
                'amount' => $items->count(),
                'total' => round($items->sum('price'), 2),
            ])
            ->sortBy([['amount', 'desc'], ['name', 'asc']])
            ->values()->all();
    }
}
