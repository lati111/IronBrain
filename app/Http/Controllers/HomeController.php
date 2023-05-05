<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Service\TimeService;
use DateTime;

class HomeController extends Controller
{
    public function show()
    {
        $projects = [];

        $projectCollection = Project::where('inOverview', true)
            ->orderBy('updated_at', 'desc')
            ->get();

        foreach ($projectCollection as $project) {
            $timeAgo = TimeService::time_elapsed_string($project->updated_at);
            $array = [
                "name" => $project->name,
                "description" => $project->description,
                "thumbnail" => $project->thumbnail,
                "route" => $project->route,
                "timeAgo" => $timeAgo,
            ];

            $projects[] = $array;
        }

        return view('home', array_merge($this->getBaseVariables(), [
            "projects" => $projects,
        ]));
    }
}
