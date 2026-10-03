<?php

namespace App\Service\DnD\Restaurant;

use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Room;
use App\Models\DnD\Restaurant\Table;

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
            ])->values()->all(),
        ];
    }
}
