<?php

namespace App\Http\Controllers;

use Doctrine\DBAL\Query\QueryBuilder;
use Illuminate\Support\Facades\Auth;
use App\Models\Config\Project;
use App\Service\TimeService;
use Illuminate\Database\Query\Builder;

class HomeController extends Controller
{
    public function show()
    {
        $role_id = null;
        $user = Auth::user();
        if ($user !== null) {
            $role_id = $user->role_id;
        }

        $projects = [];

        $projectCollection = $this->getProjects($role_id);

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

    private function getProjects(?int $role_id) {
        return Project::select('nav__project.*')
            ->leftJoin(
                'auth__permission',
                'nav__project.permission_id',
                '=',
                'auth__permission.permission')
            ->where(function ($query) use ($role_id) {
                $query->where('nav__project.permission_id', null)
                    ->orWhere(function ($query) use ($role_id) {
                        return $query
                            ->selectRaw('count(auth__role_permission.permission_id)')
                            ->from('auth__role_permission')
                            ->whereColumn('auth__role_permission.permission_id', 'nav__project.permission_id')
                            ->where('auth__role_permission.role_id', $role_id);
                    }, 1);
            })
            ->where('in_overview', true)
            ->orderBy('updated_at', 'desc')
            ->get();
    }
}
