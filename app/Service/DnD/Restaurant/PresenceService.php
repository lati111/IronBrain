<?php

namespace App\Service\DnD\Restaurant;

use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Employee;
use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps track of which players have the service page open. The page keeps asking for the service while it is open,
 * which counts as being online for a few seconds after
 */
class PresenceService
{
    /** @var int How long someone counts as online after the page last asked for the service, in seconds */
    public const int ONLINE_SECONDS = 15;

    /**
     * Notes that a user has the service page open right now
     * @param Restaurant $restaurant The restaurant
     * @param User|null $user The user
     */
    public function touch(Restaurant $restaurant, ?User $user): void
    {
        if ($user !== null) {
            Cache::put($this->key($restaurant, $user->uuid), true, self::ONLINE_SECONDS);
        }
    }

    /**
     * Gets the players that control employees and have the service page open
     * @param Restaurant $restaurant The restaurant
     * @return string[] The uuids of the players
     */
    public function getOnlinePlayers(Restaurant $restaurant): array
    {
        return Employee::where('restaurant_uuid', $restaurant->uuid)->whereNotNull('player_uuid')
            ->distinct()->pluck('player_uuid')
            ->filter(fn (string $uuid) => Cache::has($this->key($restaurant, $uuid)))
            ->values()->all();
    }

    private function key(Restaurant $restaurant, string $userUuid): string
    {
        return sprintf('dnd.restaurant.%s.online.%s', $restaurant->uuid, $userUuid);
    }
}
