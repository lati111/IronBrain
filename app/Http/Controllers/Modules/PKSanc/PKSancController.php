<?php

namespace App\Http\Controllers\Modules\PKSanc;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class PKSancController extends Controller
{
    /**
     * Shows the PKSanc homepage
     * @return View Returns a View of the page
     */
    public function showOverview(): View
    {
        return view('modules.pksanc.home', $this->getBaseVariables());
    }

    /**
     * Shows the PKSanc pokedex
     * @return View Returns a View of the page
     */
    public function showPokedex(): View
    {
        return view('modules.pksanc.pokedex', array_merge($this->getBaseVariables(), [
            'perpageoptions' => ['21']
        ]));
    }
}
