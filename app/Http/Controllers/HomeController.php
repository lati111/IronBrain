<?php

namespace App\Http\Controllers;

use App\Models\Config\Module;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Show the home page for IronBrain
     * @return View The home page
     */
    public function show()
    {
        $modules = Module::where('in_overview', true)
            ->accessible()
            ->with('submodules')
            ->orderBy('order')
            ->get();

        // Modules with submodules in the overview become a group of those submodules, the others are shown on their own
        $overviewModules = [];
        $overviewGroups = [];
        foreach ($modules as $module) {
            $submodules = $module->submodules->where('in_overview', true);
            if ($submodules->isNotEmpty()) {
                $overviewGroups[] = ['module' => $module, 'submodules' => $submodules];
            } elseif ($module->route !== null) {
                $overviewModules[] = $module;
            }
        }

        return $this->view('home', [
            'overviewModules' => $overviewModules,
            'overviewGroups' => $overviewGroups,
        ]);
    }
}
