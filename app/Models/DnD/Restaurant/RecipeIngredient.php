<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

/**
 * @inheritdoc
 * @property string recipe_uuid The uuid of the recipe
 * @property string ingredient_uuid The uuid of the ingredient
 * @property integer amount How much of the ingredient the recipe requires
 */
class RecipeIngredient extends DndRestaurantModel
{
    use AsPivot;

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_recipe_ingredient';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** Pivot attributes such as amount are filled on attach, so nothing is guarded, as with Laravel's Pivot class */
    protected $guarded = [];

    /** @inheritdoc */
    protected $casts = [
        'amount' => 'integer',
    ];

    //| Relationships

    /**
     * The relationship for the recipe
     * @return BelongsTo
     */
    public function Recipe(): BelongsTo {
        return $this->belongsTo(Recipe::class, 'recipe_uuid', 'uuid');
    }

    /**
     * The relationship for the ingredient
     * @return BelongsTo
     */
    public function Ingredient(): BelongsTo {
        return $this->belongsTo(Ingredient::class, 'ingredient_uuid', 'uuid');
    }

    //| Getters

    /**
     * Get the recipe
     * @return Recipe
     */
    public function getRecipe(): Recipe
    {
        /** @var Recipe $recipe */
        $recipe = $this->Recipe()->first();
        return $recipe;
    }

    /**
     * Get the ingredient
     * @return Ingredient
     */
    public function getIngredient(): Ingredient
    {
        /** @var Ingredient $ingredient */
        $ingredient = $this->Ingredient()->first();
        return $ingredient;
    }
}
