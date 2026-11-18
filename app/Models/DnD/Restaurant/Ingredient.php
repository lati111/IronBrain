<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritdoc
 * @property string|null parent_uuid The uuid of the ingredient this is a variant of, which it can be used instead of
 * @property string name The display name of the ingredient
 * @property string|null category The category of the ingredient, which recipes can take any ingredient of
 * @property string|null icon The path to the icon for the ingredient, if any
 * @property integer expires_in After how long the ingredient expires, in days, where 0 never expires
 * @property float size How much storage capacity a single unit of the ingredient takes up
 * @property integer quality How good the ingredient is, which meals made with it add on top of how well they turned out
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
        'quality'    => 'integer',
    ];

    /**
     * Whether the ingredient goes off at all, as one that expires in 0 days keeps forever
     * @return bool Whether it expires
     */
    public function expires(): bool
    {
        return $this->expires_in > 0;
    }

    //| Relationships

    /**
     * The relationship for the ingredient this is a variant of
     * @return BelongsTo
     */
    public function Parent(): BelongsTo {
        return $this->belongsTo(Ingredient::class, 'parent_uuid', 'uuid');
    }

    /**
     * The relationship for the variants of this ingredient
     * @return HasMany
     */
    public function Children(): HasMany {
        return $this->hasMany(Ingredient::class, 'parent_uuid', 'uuid');
    }

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
