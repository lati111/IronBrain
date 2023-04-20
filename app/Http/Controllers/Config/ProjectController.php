<?php

namespace App\Http\Controllers\Config;

use App\Models\Project;

class ProjectController
{
    public function overview()
    {
        $page = isset($_GET["page"]) ? $_GET["page"] : 1;
        $perPage = isset($_GET["perPage"]) ? $_GET["perPage"] : 10;
        $projects = Project::offset(($page - 1) * $perPage)->limit($perPage)->get();
        $projectCount = Project::all()->count();

        return view('config.projects.overview', [
            "projectCount" => $projectCount,
            "projects" => $projects,
            "perPage" => $perPage,
            "page" => $page,
        ]);
    }
}
