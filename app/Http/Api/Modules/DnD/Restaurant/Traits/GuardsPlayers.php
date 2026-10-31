<?php

namespace App\Http\Api\Modules\DnD\Restaurant\Traits;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Restaurant;
use App\Models\DnD\Restaurant\Table;
use App\Service\DnD\Restaurant\PlayerAccessService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns players away from what their employees aren't in a position to do, for the routes players may use. The owner
 * and managers may always go ahead
 */
trait GuardsPlayers
{
    /**
     * Turns a player away unless they may do something
     * @param Restaurant $restaurant The restaurant
     * @param Closure(User, PlayerAccessService): bool $may Whether the player may do it
     * @param string $message What the player is told when they may not
     * @return JsonResponse|null An error response for a player who may not, or null to go ahead
     */
    protected function denyPlayerUnless(Restaurant $restaurant, Closure $may, string $message): ?JsonResponse
    {
        $user = Auth::user();
        if ($user === null || !$restaurant->isPlayer($user) || $may($user, app(PlayerAccessService::class))) {
            return null;
        }

        return $this->respond(Response::HTTP_FORBIDDEN, $message);
    }

    /**
     * Turns a player away unless one of their employees works in an area of the kitchen
     * @param Restaurant $restaurant The restaurant
     * @param WorkStation $station The area
     * @return JsonResponse|null An error response for a player who may not, or null to go ahead
     */
    protected function denyPlayerUnlessWorking(Restaurant $restaurant, WorkStation $station): ?JsonResponse
    {
        return $this->denyPlayerUnless($restaurant, fn (User $player, PlayerAccessService $access) => $access->worksAt($restaurant, $player, $station),
            sprintf('One of your employees has to work in %s for that', strtolower($station->label())));
    }

    /**
     * Turns a player away unless one of their employees waits a table
     * @param Restaurant $restaurant The restaurant
     * @param Table $table The table
     * @return JsonResponse|null An error response for a player who may not, or null to go ahead
     */
    protected function denyPlayerUnlessWaiting(Restaurant $restaurant, Table $table): ?JsonResponse
    {
        return $this->denyPlayerUnless($restaurant, fn (User $player, PlayerAccessService $access) => $access->waits($restaurant, $player, $table),
            sprintf('One of your employees has to wait %s for that', $table->name));
    }
}
