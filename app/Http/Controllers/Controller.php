<?php

namespace App\Http\Controllers;

use App\Models\Config\Module;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function getBaseVariables(): array {
        $role_id = null;
        $user = Auth::user();
        if ($user !== null) {
            $role_id = $user->role_id;
        }

        return [
            'user' => $user,
            'navCollection' => $this->getNavItems($role_id),
        ];
    }

    private function getNavItems(?int $role_id) {
        return Module::select('nav__project.*')
            ->leftJoin(
                'auth__permission',
                'nav__project.permission_id',
                '=',
                'auth__permission.permission')
            ->where(function ($query) use ($role_id) {
                $query
                    ->where('nav__project.permission_id', null)
                    ->orWhere(function ($query) use ($role_id) {
                        return $query
                            ->selectRaw('count(auth__role_permission.permission_id)')
                            ->from('auth__role_permission')
                            ->whereColumn('auth__role_permission.permission_id', 'nav__project.permission_id')
                            ->where('auth__role_permission.role_id', $role_id);
                    }, 1);
            })
            ->where('in_nav', true)
            ->where('active', true)
            ->orderBy('order', 'asc')
            ->get();
    }
}
