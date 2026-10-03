<?php

namespace App\Http\Controllers\Modules\DnD\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\DnD\Restaurant\Restaurant;
use App\Service\DnD\Restaurant\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DndRestaurantController extends Controller
{
    public function __construct(private readonly ShiftService $shiftService)
    {
    }

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
     * Shows the service page of a restaurant. Its time is left as is, so reloading the page doesn't undo time
     * that has passed during the service. Without an ongoing service, this leads back to the management page
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return View|RedirectResponse Returns a View of the page, or redirects to the management page
     */
    public function showService(string $restaurant_uuid): View|RedirectResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);
        if (!$restaurant->isInService()) {
            return redirect()->route('dnd.restaurant.management', ['restaurant_uuid' => $restaurant_uuid]);
        }

        return $this->view('modules.dnd.restaurant.service', [
            'restaurant' => $restaurant,
        ]);
    }

    /**
     * Starts a service at the given time, defaulting to the opening time, and opens the service page. When a
     * service is already ongoing, that one is picked up instead
     * @param Request $request The request parameters as given by Laravel
     * @param string $restaurant_uuid The uuid of the restaurant
     * @return RedirectResponse Redirects to the service page
     */
    public function startService(Request $request, string $restaurant_uuid): RedirectResponse
    {
        $restaurant = $this->getRestaurant($restaurant_uuid);

        if (!$restaurant->isInService()) {
            $validated = $request->validate([
                'time' => 'nullable|date_format:H:i',
            ]);

            $this->shiftService->startService($restaurant, $validated['time'] ?? substr($restaurant->opening_time, 0, 5));
        }

        return redirect()->route('dnd.restaurant.service', ['restaurant_uuid' => $restaurant_uuid]);
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
