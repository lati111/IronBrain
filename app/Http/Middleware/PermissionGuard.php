<?php
namespace App\Http\Middleware;

use App\Enum\Auth\PermissionEnum;
use App\Enum\Auth\UserEnum;
use App\Models\Auth\Permission;
use App\Models\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Closure;

class PermissionGuard
{
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        /** @var User $user */
        $user = Auth::user();
        if ($user === null) {
            return response(view('errors.auth.unauthorized'));
        }

        foreach ($permissions as $permission) {
            $permission = Permission::where('permission', $permission)->first();
            if ($permission === null) {
                continue;
            }

            $role = $user->Role()->first();
            if ($role === null) {
                return response(view('errors.auth.forbidden'));
            }

            if ($role->is_admin || $role->hasPermission($permission) > 0) {
                continue;
            }

            return response(view('errors.auth.forbidden'));
        }

        return $next($request);
    }
}
