<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function getBaseVariables(): array {
        return [
            'navCollection' => Project::where('inNav', true)->orderBy('order', 'asc')->get(),
        ];
    }
}
