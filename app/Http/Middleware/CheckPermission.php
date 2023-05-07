<?php

namespace App\Http\Middleware;

use App\Models\Auth\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class CheckPermission
{
    private const FORBIDDEN_STRING = "Your account does not have access to this page";
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();
        if ($user === null) {
            abort('401', 'You must be logged in to access this route');
        }

        $role = $user->Role;
        if ($role === null) {
            abort('403', self::FORBIDDEN_STRING);
        }

        $permission = Permission::where('permission', $permission)->first();
        if ($permission === null) {
            abort('500', 'Permission does not exist');
        }

        if ($role->hasPermission($permission) === null) {
            abort('403', self::FORBIDDEN_STRING);
        }

        return $next($request);
    }
}
