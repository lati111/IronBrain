<?php

namespace App\Service\DnD\Restaurant;

use App\Models\DnD\Restaurant\OrderItem;
use App\Models\DnD\Restaurant\Recipe;
use App\Models\DnD\Restaurant\ServedMeal;
use Carbon\Carbon;

/**
 * How satisfied customers are with what they were served, from 1 to 5. A portion starts at 3, and goes up or down with
 * how well it turned out against the recipe's difficulty, how long the order waited for it, and whether it had spoiled
 * by the time it arrived. It is rated once, when it is served, so changing a recipe later leaves past ratings as they were
 */
class SatisfactionService
{
    /** @var int What a portion is rated at before anything counts for or against it */
    public const int BASE = 3;
    /** @var int The lowest rating */
    public const int MIN = 1;
    /** @var int The highest rating */
    public const int MAX = 5;

    /** @var array<int, int> What turning out above or below the recipe's difficulty adds, by the least it was over by */
    private const array QUALITY = [5 => 1, 0 => 0, -5 => -1];
    /** @var int What turning out further below the recipe's difficulty than that adds */
    private const int QUALITY_WORST = -2;

    /** @var array<int, int> What waiting adds, by the most minutes waited from ordering until the portion arrived */
    private const array WAIT = [20 => 1, 40 => 0, 60 => -1];
    /** @var int What waiting longer than that adds */
    private const int WAIT_WORST = -2;

    /** @var int What serving a portion that had already spoiled adds */
    private const int SPOILED = -2;

    /**
     * Rates how satisfied the customer is with a portion as it is served
     * @param OrderItem $item The order entry being served, with its order
     * @param ServedMeal $plate The plated portion served for it
     * @param Carbon $at When it was served, in the restaurant's time
     * @return int The rating, from MIN to MAX
     */
    public function rate(OrderItem $item, ServedMeal $plate, Carbon $at): int
    {
        $rating = self::BASE
            + $this->rateQuality($plate->quality, $item->recipe_uuid !== null ? Recipe::find($item->recipe_uuid) : null)
            + $this->rateWait((int) floor(($at->getTimestamp() - $item->Order->ordered_at->getTimestamp()) / 60))
            + ($plate->expires_at !== null && $at->gte($plate->expires_at) ? self::SPOILED : 0);

        return max(self::MIN, min(self::MAX, $rating));
    }

    /**
     * Gets the average rating of order entries, leaving out those that weren't rated
     * @param iterable<OrderItem> $items The order entries
     * @return float|null The average, with one decimal, or null when none was rated
     */
    public function average(iterable $items): ?float
    {
        $ratings = collect($items)->pluck('satisfaction')->filter(fn (?int $rating) => $rating !== null);
        return $ratings->isEmpty() ? null : round($ratings->avg(), 1);
    }

    /**
     * What the quality a meal turned out at adds, against the difficulty of its recipe. Nothing counts when the
     * quality wasn't set, or the recipe no longer exists
     */
    private function rateQuality(?int $quality, ?Recipe $recipe): int
    {
        if ($quality === null || $recipe === null) {
            return 0;
        }

        $margin = $quality - $recipe->difficulty;
        foreach (self::QUALITY as $least => $change) {
            if ($margin >= $least) {
                return $change;
            }
        }

        return self::QUALITY_WORST;
    }

    /**
     * What waiting a number of minutes adds
     */
    private function rateWait(int $minutes): int
    {
        foreach (self::WAIT as $most => $change) {
            if ($minutes <= $most) {
                return $change;
            }
        }

        return self::WAIT_WORST;
    }
}
