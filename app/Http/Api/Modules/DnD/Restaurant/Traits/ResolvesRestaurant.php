<?php

namespace App\Http\Api\Modules\DnD\Restaurant\Traits;

use App\Models\DnD\Restaurant\Restaurant;
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
}
