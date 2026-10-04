<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ingredient a recipe takes by category, so any ingredient of that category will do. Which one is chosen when a
 * meal of the recipe is planned
 * @inheritdoc
 * @property string recipe_uuid The uuid of the recipe
 * @property string category The category the ingredient is taken from
 * @property float amount How much of the ingredient the recipe takes
 */
class RecipeCategory extends DndRestaurantModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_recipe_category';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'amount' => 'float',
    ];

    //| Relationships

    /**
     * The relationship for the recipe
     * @return BelongsTo
     */
    public function Recipe(): BelongsTo {
        return $this->belongsTo(Recipe::class, 'recipe_uuid', 'uuid');
    }
}
