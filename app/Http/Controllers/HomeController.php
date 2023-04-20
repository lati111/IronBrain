<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Service\TimeService;
use DateTime;

class HomeController
{
    public function show()
    {
        $projects = [];

        foreach (Project::all() as $project) {
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

        return view('home', [
            "projects" => $projects,
        ]);
    }
}
