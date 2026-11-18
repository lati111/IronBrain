<?php

namespace App\Http\Controllers;

use App\Models\Config\Module;
use App\Models\Config\Submodule;
use App\Service\ChangelogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Show the home page for IronBrain
     * @return View The home page
     */
    public function show(Request $request, ChangelogService $changelogService)
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

        // The latest changelog update of each card, by the seen key of its (sub)module
        $updates = [];
        foreach ($overviewModules as $module) {
            $updates[$changelogService->getSeenKey($module->code)] = $this->getUpdate($request, $changelogService, $module);
        }
        foreach ($overviewGroups as $group) {
            foreach ($group['submodules'] as $submodule) {
                $updates[$changelogService->getSeenKey($group['module']->code, $submodule->code)] =
                    $this->getUpdate($request, $changelogService, $group['module'], $submodule);
            }
        }

        return $this->view('home', [
            'overviewModules' => $overviewModules,
            'overviewGroups' => $overviewGroups,
            'updates' => array_filter($updates),
        ]);
    }

    /**
     * Gets the latest changelog update the user can see for a module or submodule
     * @param Request $request The request
     * @param ChangelogService $changelogService The changelog service
     * @param Module $module The module
     * @param Submodule|null $submodule The submodule, or null for the module itself
     * @return array{date: string, seen: bool, url: string}|null The update, or null when there is none
     */
    private function getUpdate(Request $request, ChangelogService $changelogService, Module $module, ?Submodule $submodule = null): ?array
    {
        $release = $changelogService->getLatestRelease(Auth::user(), $module->name, $submodule?->name);
        if ($release === null || empty($release['date']) || empty($release['version'])) {
            return null;
        }

        $seenKey = $changelogService->getSeenKey($module->code, $submodule?->code);
        return [
            'date' => $release['date'],
            'seen' => $changelogService->hasSeen($request, $seenKey, $release['version']),
            'url' => route('changelog.show', array_filter(['module' => $module->code, 'submodule' => $submodule?->code])),
        ];
    }
}
