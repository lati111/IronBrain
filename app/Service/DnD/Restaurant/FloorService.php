<?php

namespace App\Service\DnD\Restaurant;

use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Room;
use App\Models\DnD\Restaurant\Table;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FloorService
{
    /**
     * Builds the floor state of a restaurant: its rooms, its tables along with the room each is placed in, and its
     * customers along with the table each is seated at
     * @param Restaurant $restaurant The restaurant to build the state for
     * @return array The floor state
     */
    public function getFloor(Restaurant $restaurant): array
    {
        $rooms = Room::where('restaurant_uuid', $restaurant->uuid)->orderBy('order')->orderBy('created_at')->get();
        $tables = Table::where('restaurant_uuid', $restaurant->uuid)->withCount('Customers')->orderBy('name')->get();
        $customers = Customer::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get();

        return [
            'rooms' => $rooms->map(fn (Room $room) => [
                'uuid' => $room->uuid,
                'name' => $room->name,
                'background_image' => $room->background_image !== null ? asset('img/' . $room->background_image) : null,
            ])->values()->all(),
            'tables' => $tables->map(fn (Table $table) => [
                'uuid' => $table->uuid,
                'name' => $table->name,
                'capacity' => $table->capacity,
                'room_uuid' => $table->room_uuid,
                'seated' => (int) $table->customers_count,
            ])->values()->all(),
            'customers' => $customers->map(fn (Customer $customer) => [
                'uuid' => $customer->uuid,
                'name' => $customer->name,
                'description' => $customer->description,
                'table_uuid' => $customer->table_uuid,
                'seat' => $customer->seat,
            ])->values()->all(),
        ];
    }

    /**
     * Seats a customer on a seat of a table, or unseats them when no table is given
     * @param Customer $customer The customer to seat
     * @param Table|null $table The table to seat them at, or null to unseat them
     * @param int|null $seat The seat at the table, from 1 up to its capacity
     * @throws InvalidArgumentException Thrown when the seat does not exist at the table or is already taken
     */
    public function seatCustomer(Customer $customer, ?Table $table, ?int $seat): void
    {
        if ($table === null) {
            $customer->table_uuid = null;
            $customer->seat = null;
            $customer->save();
            return;
        }

        if ($seat === null || $seat < 1 || $seat > $table->capacity) {
            throw new InvalidArgumentException($table->name . ' has no seat ' . $seat);
        }

        DB::transaction(function () use ($customer, $table, $seat) {
            $taken = Customer::where('table_uuid', $table->uuid)
                ->where('seat', $seat)
                ->where('uuid', '!=', $customer->uuid)
                ->lockForUpdate()
                ->exists();

            if ($taken) {
                throw new InvalidArgumentException('That seat is already taken');
            }

            $customer->table_uuid = $table->uuid;
            $customer->seat = $seat;
            $customer->save();
        });
    }

    /**
     * Gets the highest seat number that is occupied at a table
     * @param Table $table The table
     * @return int The highest occupied seat, or 0 when nobody is seated
     */
    public function getHighestOccupiedSeat(Table $table): int
    {
        return (int) Customer::where('table_uuid', $table->uuid)->max('seat');
    }
}
