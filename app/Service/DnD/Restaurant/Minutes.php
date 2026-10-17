<?php

namespace App\Service\DnD\Restaurant;

use Carbon\Carbon;

/**
 * How lengths of time are shown during service: in whole minutes, rounded to the nearest one, so 27 minutes and 15
 * seconds shows as 27. Anything that still has time left shows at least a minute, so it never reads as done early
 */
final class Minutes
{
    /**
     * Rounds a number of minutes left for showing
     * @param float $minutes The minutes left
     * @return int The minutes to show, at least 1 while any time is left and never below 0
     */
    public static function display(float $minutes): int
    {
        $minutes = round($minutes, 4);
        return $minutes <= 0 ? 0 : max(1, (int) round($minutes));
    }

    /**
     * Gets how many minutes are left until a moment, rounded for showing
     * @param Carbon $now The restaurant's current time
     * @param Carbon $moment The moment
     * @return int The minutes to show
     */
    public static function until(Carbon $now, Carbon $moment): int
    {
        return self::display(($moment->getTimestamp() - $now->getTimestamp()) / 60);
    }
}
