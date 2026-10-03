<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritdoc
 * @property string name The display name of the recipe
 * @property string|null description The description of the recipe
 * @property float price The price the recipe sells for
 * @property integer portions How many portions making the recipe once produces
 * @property integer difficulty How difficult the recipe is to make
 * @property integer prep_time How long the recipe takes to prepare, in minutes
 * @property integer cooking_time How long the recipe takes to cook, in minutes
 * @property integer fresh_for How long the dish stays fresh, in minutes
 * @property integer good_for How long the dish stays good, in hours
 * @property integer expires_in After how long the dish expires, in days
 */
class Recipe extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_recipe';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'price'             => 'float',
        'portions'          => 'integer',
        'difficulty'        => 'integer',
        'prep_time'         => 'integer',
        'cooking_time'      => 'integer',
        'fresh_for'         => 'integer',
        'good_for'          => 'integer',
        'expires_in'        => 'integer',
    ];

    //| Relationships

    /**
     * The relationship for the ingredient entries of this recipe
     * @return HasMany
     */
    public function RecipeIngredients(): HasMany {
        return $this->hasMany(RecipeIngredient::class, 'recipe_uuid', 'uuid');
    }

    /**
     * The relationship for the ingredients of this recipe, with the amount on the pivot
     * @return BelongsToMany
     */
    public function Ingredients(): BelongsToMany {
        return $this->belongsToMany(Ingredient::class, RecipeIngredient::TABLE_NAME, 'recipe_uuid', 'ingredient_uuid', 'uuid', 'uuid')
            ->using(RecipeIngredient::class)
            ->withPivot('uuid', 'amount')
            ->withTimestamps();
    }

    /**
     * The relationship for the byproduct entries of this recipe
     * @return HasMany
     */
    public function RecipeByproducts(): HasMany {
        return $this->hasMany(RecipeByproduct::class, 'recipe_uuid', 'uuid');
    }

    /**
     * The relationship for the ingredients produced as byproducts of this recipe, with the amount on the pivot
     * @return BelongsToMany
     */
    public function Byproducts(): BelongsToMany {
        return $this->belongsToMany(Ingredient::class, RecipeByproduct::TABLE_NAME, 'recipe_uuid', 'ingredient_uuid', 'uuid', 'uuid')
            ->using(RecipeByproduct::class)
            ->withPivot('uuid', 'amount')
            ->withTimestamps();
    }

    /**
     * The relationship for the equipment entries of this recipe
     * @return HasMany
     */
    public function RecipeEquipment(): HasMany {
        return $this->hasMany(RecipeEquipment::class, 'recipe_uuid', 'uuid');
    }

    /**
     * The relationship for the equipment required by this recipe, with the amount on the pivot
     * @return BelongsToMany
     */
    public function Equipment(): BelongsToMany {
        return $this->belongsToMany(Equipment::class, RecipeEquipment::TABLE_NAME, 'recipe_uuid', 'equipment_uuid', 'uuid', 'uuid')
            ->using(RecipeEquipment::class)
            ->withPivot('uuid', 'amount')
            ->withTimestamps();
    }
}
