<?php

namespace App\Http\Controllers\Config;

use App\Models\Project;
use Illuminate\Http\Request;

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

    public function new()
    {
        return view('config.projects.modify', [
        ]);
    }

    public function saveProject(Request $request) {
        $data = $request->validate([
            'id' => 'nullable|exists:project,id',
            'thumbnail' => 'required|mimes:png,jpg,jpeg,svg,webp|max:240',
            'name' => 'required|string|max:64',
            'route' => 'required|string|max:255',
            'description' => 'required|string',
            'permission' => 'nullable|string"exists:permission,permission',
        ]);
    }
}
