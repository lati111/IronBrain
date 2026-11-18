<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A meal being made in the kitchen from a recipe. Whether a cooking meal is done, and whether a meal has spoiled,
 * follows from comparing its timers to the restaurant's current time
 * @inheritdoc
 * @property string recipe_uuid The uuid of the recipe the meal is made from
 * @property string status The stored status: planned, prepped, cooking or serving
 * @property integer portions_left How many portions are left to be plated once cooked
 * @property integer|null quality How well the meal turned out, set once it is finished
 * @property integer quality_bonus Added to the quality by boosts, so the quality it is served at is both together
 * @property integer fresh_for_bonus Added to the recipe's fresh for time by boosts, in minutes
 * @property integer ingredient_quality The average quality of the ingredients it was made with, also added to the
 *                                      quality it is served at
 * @property integer equipment_bonus What the equipment it was made with adds to the quality it is served at
 * @property float equipment_multiplier What the equipment it was made with adds to the multiplier on how well it turned out
 * @property float cooking_minutes How many minutes the meal has been cooked for, in the restaurant's time
 * @property float cooking_absent How many of those minutes nobody cooked, where a cook busy prepping counts for part
 * @property float cooking_skill The skill of the cooks over the cooking, added up per minute
 * @property string|null cooker_uuid The uuid of the cooker equipment the meal is in, while cooking
 * @property integer|null cooker_slot Which piece of the cooker equipment the meal occupies, from 1
 * @property Carbon|null cooking_ends_at When the meal is done cooking, in the restaurant's time. While it is still
 *                                     cooking this is when it will be done at the speed it goes, worked out again as
 *                                     the clock moves or its speed changes
 * @property float cooking_progress How many minutes of cooking have been done, at normal speed
 * @property Carbon|null expires_at When the meal spoils, in the restaurant's time
 * @property array|null consumed The ingredients taken from storage, as storage_uuid, ingredient_uuid and amount
 * @property array|null equipment The recipe's equipment when planned, as equipment_uuid, amount, stage and container
 * @property array|null supplied How many portions of other meals have been put in, keyed by the uuid of the recipe's
 *                                requirement they fulfil
 * @property array|null carried_equipment Equipment carried over from meals put in, kept in use for this meal's cooking,
 *                                        as amounts keyed by equipment uuid
 * @property bool cooking_settled Whether the equipment used for cooking has been released after the cooking finished
 */
class Meal extends RestaurantDataModel
{
    /** @var string Ingredients have been taken from storage, but the meal hasn't been prepared yet */
    public const string STATUS_PLANNED = 'planned';
    /** @var string The meal has been prepared and can be put in a cooker */
    public const string STATUS_PREPPED = 'prepped';
    /** @var string The meal is in a cooker. Once the cooking timer has passed it is done */
    public const string STATUS_COOKING = 'cooking';
    /** @var string Derived: the meal is in a cooker and has finished cooking */
    public const string STATUS_DONE = 'done';
    /** @var string The meal has been taken out of the cooker to be served */
    public const string STATUS_SERVING = 'serving';

    /** @var int What a meal loses in quality when nobody cooks it for its whole cooking time. Less is lost for less time */
    public const int ABSENCE_PENALTY = 20;

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_meal';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'portions_left'   => 'integer',
        'quality'         => 'integer',
        'quality_bonus'   => 'integer',
        'ingredient_quality' => 'integer',
        'fresh_for_bonus' => 'integer',
        'equipment_bonus' => 'integer',
        'equipment_multiplier' => 'float',
        'cooking_minutes' => 'float',
        'cooking_absent'  => 'float',
        'cooking_skill'   => 'float',
        'cooking_progress' => 'float',
        'cooker_slot'     => 'integer',
        'cooking_ends_at' => 'datetime',
        'expires_at'      => 'datetime',
        'consumed'        => 'array',
        'equipment'       => 'array',
        'supplied'        => 'array',
        'carried_equipment' => 'array',
        'cooking_settled' => 'boolean',
    ];

    /**
     * Gets the status of the meal at a moment in the restaurant, where a cooking meal past its timer is done
     * @param Carbon $now The restaurant's current time
     * @return string The status
     */
    public function getStatusAt(Carbon $now): string
    {
        if ($this->status === self::STATUS_COOKING && $this->cooking_ends_at !== null && $now->gte($this->cooking_ends_at)) {
            return self::STATUS_DONE;
        }

        return $this->status;
    }

    /**
     * Checks whether the meal is finished at a moment in the restaurant: done cooking, or ready to serve
     * @param Carbon $now The restaurant's current time
     * @return bool Whether it is finished
     */
    public function isFinishedAt(Carbon $now): bool
    {
        return in_array($this->getStatusAt($now), [self::STATUS_DONE, self::STATUS_SERVING], true);
    }

    /**
     * Gets what the cooking added to the quality: what the cooks' skill gave over the time they looked after the meal,
     * less what the time nobody did took off
     * @return int The quality, negative when the cooking went badly
     */
    public function getCookingQuality(): int
    {
        if ($this->cooking_minutes <= 0) {
            return 0;
        }

        return (int) round($this->cooking_skill / $this->cooking_minutes - self::ABSENCE_PENALTY * $this->cooking_absent / $this->cooking_minutes);
    }

    /**
     * Gets the quality the meal is served at: how well it turned out, made more by the equipment's multiplier, with what
     * boosts, its ingredients, the cooking and the equipment's bonuses added
     * @return int|null The quality, or null when it hasn't been set yet
     */
    public function getServedQuality(): ?int
    {
        if ($this->quality === null) {
            return null;
        }

        return (int) round($this->quality * (1 + $this->equipment_multiplier))
            + $this->quality_bonus + $this->ingredient_quality + $this->getCookingQuality() + $this->equipment_bonus;
    }

    /**
     * Checks whether the meal has spoiled at a moment in the restaurant
     * @param Carbon $now The restaurant's current time
     * @return bool Whether it has spoiled
     */
    public function isSpoiledAt(Carbon $now): bool
    {
        return $this->expires_at !== null && $now->gte($this->expires_at);
    }

    //| Relationships

    /**
     * The relationship for the recipe the meal is made from
     * @return BelongsTo
     */
    public function Recipe(): BelongsTo {
        return $this->belongsTo(Recipe::class, 'recipe_uuid', 'uuid');
    }

    /**
     * The relationship for the cooker the meal is in
     * @return BelongsTo
     */
    public function Cooker(): BelongsTo {
        return $this->belongsTo(Equipment::class, 'cooker_uuid', 'uuid');
    }

    //| Getters

    /**
     * Get the recipe the meal is made from
     * @return Recipe
     */
    public function getRecipe(): Recipe
    {
        /** @var Recipe $recipe */
        $recipe = $this->Recipe()->first();
        return $recipe;
    }
}
