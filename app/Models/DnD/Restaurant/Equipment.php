<?php

namespace App\Models\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentCategory;
use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritdoc
 * @property string|null parent_uuid The uuid of the parent equipment, if any
 * @property string name The display name of the equipment
 * @property string|null description The description of the equipment
 * @property EquipmentCategory category The category this equipment falls under
 * @property float cleaning_time How long the equipment takes to clean, in minutes
 * @property float multiplier The multiplier this equipment applies
 * @property float size How much storage capacity a single piece of the equipment takes up
 * @property bool requires_storage Whether this equipment must be placed in a storage space to be stored
 * @property integer amount_dirty How many pieces are dirty and need cleaning before they can be used, during service
 * @property integer amount_in_use How many pieces are being used by meals, during service
 */
class Equipment extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_equipment';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'category'         => EquipmentCategory::class,
        'cleaning_time'    => 'float',
        'multiplier'       => 'float',
        'size'             => 'float',
        'requires_storage' => 'boolean',
        'amount_dirty'     => 'integer',
        'amount_in_use'    => 'integer',
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

    /**
     * The relationship for the storage spaces this equipment is stored in, with the amount on the pivot
     * @return BelongsToMany
     */
    public function Storages(): BelongsToMany {
        return $this->belongsToMany(Storage::class, StorageEquipment::TABLE_NAME, 'equipment_uuid', 'storage_uuid', 'uuid', 'uuid')
            ->using(StorageEquipment::class)
            ->withPivot('uuid', 'amount')
            ->withTimestamps();
    }

    /**
     * The relationship for the owned equipment entries for this equipment, used when it does not require storage
     * @return HasMany
     */
    public function Owned(): HasMany {
        return $this->hasMany(OwnedEquipment::class, 'equipment_uuid', 'uuid');
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
