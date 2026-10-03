<?php

namespace App\Http\Controllers\Modules\DnD\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DndRestaurantController extends Controller
{
    /**
     * Shows the DnD restaurant simulator homepage
     * @return View Returns a View of the page
     */
    public function showOverview(): View
    {
        return view('modules.dnd.restaurant.home', $this->getBaseVariables());
    }

    /**
     * Shows the management page of a restaurant, where its inventory and recipes are managed
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return View Returns a View of the page
     */
    public function showManagement(string $restaurant_uuid): View
    {
        return $this->view('modules.dnd.restaurant.management', [
            'restaurant' => $this->getRestaurant($restaurant_uuid),
        ]);
    }

    /**
     * Shows the page where the details, rooms, tables and customers of a restaurant are managed
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return View Returns a View of the page
     */
    public function showManage(string $restaurant_uuid): View
    {
        return $this->view('modules.dnd.restaurant.manage', [
            'restaurant' => $this->getRestaurant($restaurant_uuid),
        ]);
    }

    /**
     * Shows the service page of a restaurant. Opening it starts a service, which sets the current time to opening time
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return View Returns a View of the page
     */
    public function showService(string $restaurant_uuid): View
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        $restaurant->startService();

        return $this->view('modules.dnd.restaurant.service', [
            'restaurant' => $restaurant,
        ]);
    }

    /**
     * Gets a restaurant the current user has access to, aborting with a 404 otherwise
     * @param string $uuid The uuid of the restaurant
     * @return Restaurant The restaurant
     */
    private function getRestaurant(string $uuid): Restaurant
    {
        $restaurant = Restaurant::where('uuid', $uuid)->first();
        if ($restaurant === null || $restaurant->hasAccess(Auth::user()) === false) {
            abort(404);
        }

        return $restaurant;
    }
}
