<?php

namespace App\Http\Controllers\Modules\DndRestaurant;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DndRestaurantController extends Controller
{
    /**
     * Shows the DnD restaurant simulator homepage
     * @return View Returns a View of the page
     */
    public function showOverview(): View
    {
        return view('modules.dnd_restaurant.home', $this->getBaseVariables());
    }
}
