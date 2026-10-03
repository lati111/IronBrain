<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @inheritdoc
 * @property string name The display name of the ingredient
 * @property string|null icon The path to the icon for the ingredient, if any
 * @property integer expires_in After how long the ingredient expires, in days
 * @property float size How much storage capacity a single unit of the ingredient takes up
 */
class Ingredient extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_ingredient';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'expires_in' => 'integer',
        'size'       => 'float',
    ];

    //| Relationships

    /**
     * The relationship for the recipes this ingredient is used in, with the amount on the pivot
     * @return BelongsToMany
     */
    public function Recipes(): BelongsToMany {
        return $this->belongsToMany(Recipe::class, RecipeIngredient::TABLE_NAME, 'ingredient_uuid', 'recipe_uuid', 'uuid', 'uuid')
            ->using(RecipeIngredient::class)
            ->withPivot('uuid', 'amount')
            ->withTimestamps();
    }

    /**
     * The relationship for the storage spaces this ingredient is stored in, with the amount on the pivot
     * @return BelongsToMany
     */
    public function Storages(): BelongsToMany {
        return $this->belongsToMany(Storage::class, StorageIngredient::TABLE_NAME, 'ingredient_uuid', 'storage_uuid', 'uuid', 'uuid')
            ->using(StorageIngredient::class)
            ->withPivot('uuid', 'amount')
            ->withTimestamps();
    }
}
