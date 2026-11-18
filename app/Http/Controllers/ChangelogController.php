<?php

namespace App\Http\Controllers;

use App\Models\Config\Module;
use App\Models\Config\Submodule;
use App\Service\ChangelogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class ChangelogController extends Controller
{
    public function __construct(private readonly ChangelogService $changelogService)
    {
    }

    /**
     * Show the changelog page, newest version first, with only the changes the user may see. With a module (and
     * submodule) code in the query it only shows the changes of that (sub)module, which then count as seen
     * @param Request $request The request
     * @return Response The changelog page
     */
    public function show(Request $request): Response
    {
        $module = null;
        $submodule = null;
        if ($request->filled('module')) {
            $module = Module::where('code', $request->query('module'))->first();
            if ($module !== null && $request->filled('submodule')) {
                $submodule = Submodule::where('module_id', $module->id)->where('code', $request->query('submodule'))->first();
            }
        }

        $filtered = $request->filled('module');
        $subject = $request->filled('submodule') ? $submodule : $module;
        $releases = $filtered && $subject === null
            ? []
            : $this->changelogService->getReleases(Auth::user(), $module?->name, $submodule?->name);

        $response = response($this->view('changelog', [
            'releases' => $releases,
            'subject' => $subject?->name,
        ]));

        if ($subject !== null && $releases !== []) {
            $seenKey = $this->changelogService->getSeenKey($module->code, $submodule?->code);
            $response->cookie($this->changelogService->makeSeenCookie($request, $seenKey, $releases[0]['version'] ?? ''));
        }

        return $response;
    }
}
