<?php
namespace App\Http\Middleware;

use App\Models\DnD\Restaurant\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts the changes made to a restaurant, so pages that refresh can tell when nothing changed. Every request that
 * isn't a GET may change it, so it counts before the request is handled, letting the response carry the new count.
 * A request that turns out to change nothing only costs a refresh one fetch
 */
class RestaurantStateVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->route('restaurant_uuid');
        if ($uuid !== null && !$request->isMethodSafe()) {
            DB::table(Restaurant::TABLE_NAME)->where('uuid', $uuid)->increment('state_version');
        }

        return $next($request);
    }
}
