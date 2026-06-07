<?php

namespace App\Http\Controllers\Modules\Arsenal;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ArsenalController extends Controller
{
    /**
     * Shows the arsenal homepage
     * @return View Returns a View of the page
     */
    public function showOverview(): View
    {
        return view('modules.arsenal.home', $this->getBaseVariables());
    }

    /**
     * Shows the arsenal armory page
     * @return View Returns a View of the page
     */
    public function showArmory(): View
    {
        return view('modules.arsenal.armory', $this->getBaseVariables());
    }

    /**
     * Shows the arsenal foundry page
     * @return View Returns a View of the page
     */
    public function showFoundry(): View
    {
        return view('modules.arsenal.foundry', $this->getBaseVariables());
    }
}
