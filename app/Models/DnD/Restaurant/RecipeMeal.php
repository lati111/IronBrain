<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Another meal a recipe takes as an ingredient: either a meal of a specific recipe, or of any recipe of a category.
 * During service a finished meal is put into the meal requiring it
 * @inheritdoc
 * @property string recipe_uuid The uuid of the recipe requiring the meal
 * @property string|null required_recipe_uuid The uuid of the recipe the meal must be of, or null when any recipe of the
 *                                            category will do
 * @property string|null category The category of recipe the meal must be of, when no specific recipe is required
 * @property integer portions How many portions of the meal the recipe takes
 */
class RecipeMeal extends DndRestaurantModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_recipe_meal';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'portions' => 'integer',
    ];

    /**
     * Checks whether a meal of a recipe fulfils this requirement
     * @param Recipe $recipe The recipe of the meal
     * @return bool Whether it does
     */
    public function accepts(Recipe $recipe): bool
    {
        return $this->required_recipe_uuid !== null
            ? $this->required_recipe_uuid === $recipe->uuid
            : $this->category !== null && $this->category === $recipe->category;
    }

    /**
     * Gets what the requirement is shown as: the required recipe's name, or any recipe of the category
     * @return string The label
     */
    public function getLabel(): string
    {
        return $this->required_recipe_uuid !== null ? ($this->RequiredRecipe->name ?? 'Unknown') : 'Any ' . $this->category;
    }

    //| Relationships

    /**
     * The relationship for the recipe requiring the meal
     * @return BelongsTo
     */
    public function Recipe(): BelongsTo {
        return $this->belongsTo(Recipe::class, 'recipe_uuid', 'uuid');
    }

    /**
     * The relationship for the recipe the meal must be of
     * @return BelongsTo
     */
    public function RequiredRecipe(): BelongsTo {
        return $this->belongsTo(Recipe::class, 'required_recipe_uuid', 'uuid');
    }
}
