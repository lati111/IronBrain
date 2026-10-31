<?php

namespace App\Service\DnD\Restaurant;

use Carbon\Carbon;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Boost;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RestaurantUser;
use App\Models\DnD\Restaurant\Room;
use App\Models\DnD\Restaurant\Table;
use App\Models\DnD\Restaurant\Work;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class FloorService
{
    /**
     * Builds the floor state of a restaurant: its rooms, its tables along with the room each is placed in, and its
     * customers along with the table each is seated at, its employees, its players, and its boosts
     * @param Restaurant $restaurant The restaurant to build the state for
     * @return array The floor state
     */
    public function getFloor(Restaurant $restaurant): array
    {
        $rooms = Room::where('restaurant_uuid', $restaurant->uuid)->orderBy('order')->orderBy('created_at')->get();
        $tables = Table::where('restaurant_uuid', $restaurant->uuid)->withCount('Customers')->orderBy('name')->get();
        $customers = Customer::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get();
        $seating = Work::where('restaurant_uuid', $restaurant->uuid)->whereNotNull('customer_uuid')->get()->keyBy('customer_uuid');
        $employees = Employee::where('restaurant_uuid', $restaurant->uuid)->with('Player')->orderBy('name')->get();
        $players = RestaurantUser::where('restaurant_uuid', $restaurant->uuid)->where('role', RestaurantUser::ROLE_PLAYER)
            ->with('User')->get()->sortBy(fn (RestaurantUser $access) => strtolower($access->User->username));

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
                // Waiting at the entrance to be seated, since when in the restaurant's time, which orders the entrance
                'at_entrance' => $customer->table_uuid === null && $customer->entered_at !== null,
                'entered_at' => $customer->table_uuid === null ? $customer->entered_at?->format('Y-m-d H:i:s') : null,
                // Where a waiter is taking them, while they are on their way there from the entrance
                'seating' => isset($seating[$customer->uuid])
                    ? ['table_uuid' => $seating[$customer->uuid]->table_uuid, 'seat' => $seating[$customer->uuid]->seat]
                    : null,
            ])->values()->all(),
            'employees' => $employees->map(fn (Employee $employee) => [
                'uuid' => $employee->uuid,
                'name' => $employee->name,
                'icon' => $employee->icon !== null ? asset('img/' . $employee->icon) : null,
                'cleaning_speed' => $employee->cleaning_speed,
                'prep_speed' => $employee->prep_speed,
                'player_uuid' => $employee->player_uuid,
                'player_name' => $employee->Player?->username,
            ])->values()->all(),
            // Boosts the restaurant can activate during service
            'boosts' => Boost::where('restaurant_uuid', $restaurant->uuid)->orderBy('name')->get()
            ->map(fn (Boost $boost) => [
                'uuid' => $boost->uuid,
                'name' => $boost->name,
                // What it can be activated on, of the Boost::TARGET constants
                'targets' => $boost->targets ?? [],
                'speed_bonus' => $boost->speed_bonus,
                'minutes_off' => $boost->minutes_off,
                'quality_bonus' => $boost->quality_bonus,
                'fresh_for_bonus' => $boost->fresh_for_bonus,
                'duration' => $boost->duration,
            ])->values()->all(),
            // The users invited to play, who control the employees assigned to them
            'players' => $players->map(fn (RestaurantUser $access) => [
                'uuid' => $access->user_uuid,
                'username' => $access->User->username,
            ])->values()->all(),
        ];
    }

    /**
     * Invites a user to play in the restaurant, giving them access as a player
     * @param Restaurant $restaurant The restaurant
     * @param User $user The user
     * @throws InvalidArgumentException Thrown when the user owns the restaurant or already has access to it
     */
    public function invitePlayer(Restaurant $restaurant, User $user): void
    {
        if ($restaurant->hasAccess($user)) {
            throw new InvalidArgumentException($user->username . ' already has access to ' . $restaurant->name);
        }

        $access = new RestaurantUser();
        $access->restaurant_uuid = $restaurant->uuid;
        $access->user_uuid = $user->uuid;
        $access->role = RestaurantUser::ROLE_PLAYER;
        $access->save();
    }

    /**
     * Removes a player from the restaurant, along with their access. Their employees are no longer controlled by anyone
     * @param Restaurant $restaurant The restaurant
     * @param RestaurantUser $access The player's access
     */
    public function removePlayer(Restaurant $restaurant, RestaurantUser $access): void
    {
        DB::transaction(function () use ($restaurant, $access) {
            Employee::where('restaurant_uuid', $restaurant->uuid)->where('player_uuid', $access->user_uuid)->update(['player_uuid' => null]);
            $access->delete();
        });
    }

    /**
     * Checks whether a user is one of the restaurant's players, who employees can be assigned to
     * @param Restaurant $restaurant The restaurant
     * @param string $userUuid The uuid of the user
     * @return bool Whether they are a player
     */
    public function isPlayer(Restaurant $restaurant, string $userUuid): bool
    {
        return RestaurantUser::where('restaurant_uuid', $restaurant->uuid)->where('user_uuid', $userUuid)
            ->where('role', RestaurantUser::ROLE_PLAYER)->exists();
    }

    /**
     * Seats a customer on a seat of a table, or unseats them when no table is given
     * @param Customer $customer The customer to seat
     * @param Table|null $table The table to seat them at, or null to unseat them
     * @param int|null $seat The seat at the table, from 1 up to its capacity
     * @throws InvalidArgumentException Thrown when the seat does not exist at the table or is already taken
     */
    public function seatCustomer(Customer $customer, ?Table $table, ?int $seat, ?Carbon $toEntrance = null): void
    {
        if ($table === null) {
            $this->unseatCustomer($customer, $toEntrance);
            return;
        }

        DB::transaction(function () use ($customer, $table, $seat) {
            $this->assertSeatFree($customer, $table, $seat);

            // A customer seated straight away isn't on their way anymore, nor waiting at the entrance
            Work::where('customer_uuid', $customer->uuid)->delete();

            $customer->table_uuid = $table->uuid;
            $customer->seat = $seat;
            $customer->entered_at = null;
            $customer->save();
        });
    }

    /**
     * Checks that a seat at a table exists and is free for a customer: nobody else sits there, nor is on their way
     * there with a waiter
     * @param Customer $customer The customer to seat
     * @param Table $table The table
     * @param int|null $seat The seat, from 1 up to the table's capacity
     * @throws InvalidArgumentException Thrown when the seat does not exist at the table or is already taken
     */
    public function assertSeatFree(Customer $customer, Table $table, ?int $seat): void
    {
        if ($seat === null || $seat < 1 || $seat > $table->capacity) {
            throw new InvalidArgumentException($table->name . ' has no seat ' . $seat);
        }

        $taken = Customer::where('table_uuid', $table->uuid)->where('seat', $seat)->where('uuid', '!=', $customer->uuid)
            ->lockForUpdate()->exists()
            || Work::where('table_uuid', $table->uuid)->where('seat', $seat)->whereNotNull('customer_uuid')
                ->where('customer_uuid', '!=', $customer->uuid)->exists();

        if ($taken) {
            throw new InvalidArgumentException('That seat is already taken');
        }
    }

    /**
     * Unseats a customer, or calls off them being seated, either sending them back to the entrance or off the floor
     * @param Customer $customer The customer
     * @param Carbon|null $toEntrance When they arrive back at the entrance, in the restaurant's time, or null when
     *                                they leave the floor
     */
    public function unseatCustomer(Customer $customer, ?Carbon $toEntrance = null): void
    {
        DB::transaction(function () use ($customer, $toEntrance) {
            Work::where('customer_uuid', $customer->uuid)->delete();

            $customer->table_uuid = null;
            $customer->seat = null;
            $customer->entered_at = $toEntrance;
            $customer->save();
        });
    }

    /**
     * Brings a customer who isn't seated to the entrance to wait there, or sends them off the floor, calling off them
     * being seated
     * @param Customer $customer The customer
     * @param Carbon|null $at When they arrive at the entrance, in the restaurant's time, or null to send them off
     * @throws InvalidArgumentException Thrown when the customer is seated
     */
    public function setAtEntrance(Customer $customer, ?Carbon $at): void
    {
        if ($customer->table_uuid !== null) {
            throw new InvalidArgumentException($customer->name . ' is seated');
        }

        DB::transaction(function () use ($customer, $at) {
            if ($at === null) {
                Work::where('customer_uuid', $customer->uuid)->delete();
            }

            // Already waiting there, they keep their place in line
            $customer->entered_at = $at !== null ? ($customer->entered_at ?? $at) : null;
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
