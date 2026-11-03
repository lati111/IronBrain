<?php

namespace App\Service\DnD\Restaurant;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Table;

/**
 * What a player of a restaurant may do during service, which follows from where the employees assigned to them work:
 * an area of the kitchen they work in, or the tables they wait. Any of their employees will do
 */
class PlayerAccessService
{
    /**
     * Checks whether any of a player's employees works in an area of the kitchen
     * @param Restaurant $restaurant The restaurant
     * @param User $player The player
     * @param WorkStation $station The area
     * @return bool Whether one of them works there
     */
    public function worksAt(Restaurant $restaurant, User $player, WorkStation $station): bool
    {
        return Employee::where('restaurant_uuid', $restaurant->uuid)->where('player_uuid', $player->uuid)
            ->whereJsonContains('stations', $station->value)->exists();
    }

    /**
     * Checks whether any of a player's employees waits a table
     * @param Restaurant $restaurant The restaurant
     * @param User $player The player
     * @param Table $table The table
     * @return bool Whether one of them waits it
     */
    public function waits(Restaurant $restaurant, User $player, Table $table): bool
    {
        return $table->employee_uuid !== null && Employee::where('restaurant_uuid', $restaurant->uuid)
            ->where('uuid', $table->employee_uuid)->where('player_uuid', $player->uuid)->exists();
    }

    /**
     * Checks whether any of a player's employees waits any table
     * @param Restaurant $restaurant The restaurant
     * @param User $player The player
     * @return bool Whether one of them waits a table
     */
    public function waitsAnyTable(Restaurant $restaurant, User $player): bool
    {
        return Table::where('restaurant_uuid', $restaurant->uuid)
            ->whereIn('employee_uuid', Employee::where('player_uuid', $player->uuid)->select('uuid'))->exists();
    }
}
