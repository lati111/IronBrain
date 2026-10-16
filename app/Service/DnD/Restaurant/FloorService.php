<?php

namespace App\Service\DnD\Restaurant;

use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Boost;
use App\Models\DnD\Restaurant\Customer;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\RestaurantUser;
use App\Models\DnD\Restaurant\Room;
use App\Models\DnD\Restaurant\Table;
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
                'target' => $boost->target,
                'speed_bonus' => $boost->speed_bonus,
                'minutes_off' => $boost->minutes_off,
                'quality_bonus' => $boost->quality_bonus,
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
