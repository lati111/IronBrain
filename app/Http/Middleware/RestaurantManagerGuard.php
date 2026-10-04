<?php
namespace App\Http\Middleware;

use App\Models\DnD\Restaurant\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the players of a restaurant out of what only its owner and managers may do. Routes players may use opt out
 * of this, and check what the player may do with it themselves. Restaurants the user has no access to at all are left
 * for the route itself to turn away
 */
class RestaurantManagerGuard
{
    /** @var string What a player is told when they try something only the owner and managers may do */
    public const string MESSAGE = 'Players can\'t do that';

    public function handle(Request $request, Closure $next): Response
    {
        $uuid = $request->route('restaurant_uuid');
        $user = Auth::user();
        if ($uuid === null || $user === null) {
            return $next($request);
        }

        $restaurant = Restaurant::where('uuid', $uuid)->first();
        if ($restaurant === null || $restaurant->isPlayer($user) === false) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['code' => Response::HTTP_FORBIDDEN, 'message' => self::MESSAGE, 'data' => null, 'errors' => null],
                Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, self::MESSAGE);
    }
}
