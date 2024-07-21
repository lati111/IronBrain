<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Show the home page for IronBrain
     * @return View The home page
     */
    public function show()
    {
        return $this->view('home');
    }
}
