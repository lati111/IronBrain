<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritdoc
 * @property string|null parent_uuid The uuid of the base recipe this is a variant of, if any. A variant's own stats and
 *                                   components are its base recipe's with its changes applied
 * @property string name The display name of the recipe
 * @property string type What kind of recipe it is, such as a meal
 * @property string|null category The category of the recipe, which other recipes can require any meal of
 * @property string|null description The description of the recipe
 * @property float price The price the recipe sells for
 * @property integer portions How many portions making the recipe once produces
 * @property integer difficulty How difficult the recipe is to make
 * @property integer prep_time How long the recipe takes to prepare, in minutes
 * @property integer cooking_time How long the recipe takes to cook, in minutes
 * @property integer fresh_for How long the dish stays fresh, in minutes
 * @property integer good_for How long the dish stays good, in hours
 * @property integer expires_in After how long the dish expires, in days
 * @property array|null variant_changes For a variant, what it changes about its base recipe: the stats it adds as
 *                                      stats, the components it adds as add and those it takes away as remove, both
 *                                      per list of VARIANT_LISTS
 */
class Recipe extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_recipe';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @var string[] The stats a variant adds to its base recipe's */
    public const array VARIANT_STATS = ['price', 'portions', 'difficulty', 'prep_time', 'cooking_time', 'fresh_for', 'good_for', 'expires_in'];

    /** @var string[] The lists a variant can add to or take away from its base recipe's */
    public const array VARIANT_LISTS = ['ingredients', 'equipment', 'byproducts', 'categories', 'meals'];

    /** @inheritdoc */
    protected $casts = [
        'variant_changes'   => 'array',
        'price'             => 'float',
        'portions'          => 'integer',
        'difficulty'        => 'integer',
        'prep_time'         => 'integer',
        'cooking_time'      => 'integer',
        'fresh_for'         => 'integer',
        'good_for'          => 'integer',
        'expires_in'        => 'integer',
    ];

    /**
     * Describes what a variant changes about its base recipe: what it adds to each stat, the components it adds in
     * the same form as the recipe's own lists, and what it takes away from each list
     * @return array|null The changes, or null when the recipe is no variant
     */
    public function describeVariantChanges(): ?array
    {
        if ($this->parent_uuid === null) {
            return null;
        }

        $changes = $this->variant_changes ?? [];
        $added = fn (string $list) => collect($changes['add'][$list] ?? [])
            ->map(fn (array $attributes, $uuid) => array_merge(['uuid' => (string) $uuid], $attributes))->values()->all();

        return [
            'stats' => array_merge(array_fill_keys(self::VARIANT_STATS, 0), $changes['stats'] ?? []),
            'ingredients' => $added('ingredients'),
            'equipment' => $added('equipment'),
            'byproducts' => $added('byproducts'),
            'categories' => collect($changes['add']['categories'] ?? [])
                ->map(fn ($amount, $category) => ['category' => (string) $category, 'amount' => (float) $amount])->values()->all(),
            'meals' => array_values($changes['add']['meals'] ?? []),
            'removed' => array_merge(array_fill_keys(self::VARIANT_LISTS, []), $changes['remove'] ?? []),
        ];
    }

    //| Relationships

    /**
     * The relationship for the base recipe this is a variant of
     * @return BelongsTo
     */
    public function Parent(): BelongsTo {
        return $this->belongsTo(Recipe::class, 'parent_uuid', 'uuid');
    }

    /**
     * The relationship for the variants of this recipe
     * @return HasMany
     */
    public function Variants(): HasMany {
        return $this->hasMany(Recipe::class, 'parent_uuid', 'uuid');
    }

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
     * The relationship for the ingredients this recipe takes by category, any ingredient of which will do
     * @return HasMany
     */
    public function RecipeCategories(): HasMany {
        return $this->hasMany(RecipeCategory::class, 'recipe_uuid', 'uuid');
    }

    /**
     * The relationship for the other meals this recipe takes, of a specific recipe or of any recipe of a category
     * @return HasMany
     */
    public function RecipeMeals(): HasMany {
        return $this->hasMany(RecipeMeal::class, 'recipe_uuid', 'uuid');
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
