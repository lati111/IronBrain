<?php

namespace App\Http\Api\Modules\DnD\Restaurant\Traits;

use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait ResolvesRestaurant
{
    /**
     * Gets a restaurant the current user has access to
     * @param string $uuid The uuid of the restaurant
     * @return Restaurant|null The restaurant, or null if it does not exist or the user has no access
     */
    private function getRestaurant(string $uuid): ?Restaurant
    {
        $restaurant = Restaurant::where('uuid', $uuid)->first();
        if ($restaurant === null || $restaurant->hasAccess(Auth::user()) === false) {
            return null;
        }

        return $restaurant;
    }

    /**
     * Gets one of the restaurant's records, such as a meal, a table or an employee
     * @template T of Model
     * @param class-string<T> $model The model of the record
     * @param Restaurant $restaurant The restaurant
     * @param string|null $uuid The uuid of the record
     * @return T|null The record, or null if it does not belong to the restaurant
     */
    private function findInRestaurant(string $model, Restaurant $restaurant, ?string $uuid): ?Model
    {
        return $model::where('uuid', $uuid)->where('restaurant_uuid', $restaurant->uuid)->first();
    }
}
