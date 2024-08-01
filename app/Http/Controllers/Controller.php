<?php

namespace App\Http\Controllers;

use App\Models\Config\Module;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Create and return a page view
     * @param string $view The string representation of the view
     * @param array $parameters The parameters to flash to the view
     * @return View The created view
     */
    protected function view(string $view, array $parameters = []) {
        return view($view, array_merge($this->getBaseVariables(), $parameters));
    }

    /**
     * Get the basic parameters for any view
     * @return array The basic parameters
     */
    protected function getBaseVariables(): array {
        $user = Auth::user();

        $modules = Module::orderBy('order')
            ->where('in_nav', true)
            ->with('submodules');

        if ($user === null) {
            $modules->where('requires_login', false);
        }

        return [
            'user' => $user,
            'modules' => $modules->get(),
        ];
    }
}
