<?php

namespace App\Http\Controllers\Modules\Compendium;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class CompendiumController extends Controller
{
    /**
     * Shows the docs overview page
     * @return View Returns a View of the page
     */
    public function campaigns(): View
    {
        return $this->view('modules.compendium.campaigns');
    }
}
