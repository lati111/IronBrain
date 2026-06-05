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
}
