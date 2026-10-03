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
     * Shows the inventory management page of a restaurant
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return View Returns a View of the page
     */
    public function showInventory(string $restaurant_uuid): View
    {
        $restaurant = Restaurant::where('uuid', $restaurant_uuid)->first();
        if ($restaurant === null || $restaurant->hasAccess(Auth::user()) === false) {
            abort(404);
        }

        return $this->view('modules.dnd.restaurant.inventory', [
            'restaurant' => $restaurant,
        ]);
    }
}
