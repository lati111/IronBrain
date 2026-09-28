<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritdoc
 * @property string|null parent_uuid The uuid of the parent equipment, if any
 * @property string name The display name of the equipment
 * @property string|null description The description of the equipment
 * @property string category The category this equipment falls under
 * @property integer cleaning_time How long the equipment takes to clean, in minutes
 * @property float multiplier The multiplier this equipment applies
 */
class Equipment extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_equipment';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'cleaning_time' => 'integer',
        'multiplier'    => 'float',
    ];

    //| Relationships

    /**
     * The relationship for the parent equipment
     * @return BelongsTo
     */
    public function Parent(): BelongsTo {
        return $this->belongsTo(Equipment::class, 'parent_uuid', 'uuid');
    }

    /**
     * The relationship for the child equipment
     * @return HasMany
     */
    public function Children(): HasMany {
        return $this->hasMany(Equipment::class, 'parent_uuid', 'uuid');
    }

    /**
     * The relationship for the recipes that require this equipment, with the amount on the pivot
     * @return BelongsToMany
     */
    public function Recipes(): BelongsToMany {
        return $this->belongsToMany(Recipe::class, RecipeEquipment::TABLE_NAME, 'equipment_uuid', 'recipe_uuid', 'uuid', 'uuid')
            ->using(RecipeEquipment::class)
            ->withPivot('uuid', 'amount')
            ->withTimestamps();
    }

    //| Getters

    /**
     * Get the parent equipment
     * @return Equipment|null
     */
    public function getParent(): Equipment|null
    {
        /** @var Equipment|null $parent */
        $parent = $this->Parent()->first();
        return $parent;
    }
}
