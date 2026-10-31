<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritdoc
 * @property string name The display name of the storage space
 * @property integer capacity The maximum capacity of the storage space
 * @property float expiration_multiplier The multiplier applied to the expiration of stored ingredients
 * @property string|null background_image The path to the background image, if any
 * @property integer order The position of the storage space within the restaurant
 */
class Storage extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_storage';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'capacity'              => 'integer',
        'expiration_multiplier' => 'float',
        'order'                 => 'integer',
    ];

    /**
     * Get how much of the storage capacity is taken up by the stored ingredients and equipment. Ingredient sizes
     * can be fractional, so the result is rounded to the two decimals sizes are stored with
     * @return float The used capacity
     */
    public function getUsedCapacity(): float
    {
        $ingredients = (float) $this->StorageIngredients()
            ->join(Ingredient::TABLE_NAME, Ingredient::TABLE_NAME.'.uuid', '=', StorageIngredient::TABLE_NAME.'.ingredient_uuid')
            ->sum($this->getConnection()->raw(StorageIngredient::TABLE_NAME.'.amount * '.Ingredient::TABLE_NAME.'.size'));

        $equipment = (int) $this->StorageEquipment()
            ->join(Equipment::TABLE_NAME, Equipment::TABLE_NAME.'.uuid', '=', StorageEquipment::TABLE_NAME.'.equipment_uuid')
            ->sum($this->getConnection()->raw(StorageEquipment::TABLE_NAME.'.amount * '.Equipment::TABLE_NAME.'.size'));

        return round($ingredients + $equipment, 2);
    }

    //| Relationships

    /**
     * The relationship for the ingredient entries in this storage
     * @return HasMany
     */
    public function StorageIngredients(): HasMany {
        return $this->hasMany(StorageIngredient::class, 'storage_uuid', 'uuid');
    }

    /**
     * The relationship for the ingredients in this storage, with the amount on the pivot
     * @return BelongsToMany
     */
    public function Ingredients(): BelongsToMany {
        return $this->belongsToMany(Ingredient::class, StorageIngredient::TABLE_NAME, 'storage_uuid', 'ingredient_uuid', 'uuid', 'uuid')
            ->using(StorageIngredient::class)
            ->withPivot('uuid', 'amount')
            ->withTimestamps();
    }

    /**
     * The relationship for the equipment entries in this storage
     * @return HasMany
     */
    public function StorageEquipment(): HasMany {
        return $this->hasMany(StorageEquipment::class, 'storage_uuid', 'uuid');
    }

    /**
     * The relationship for the equipment in this storage, with the amount on the pivot
     * @return BelongsToMany
     */
    public function Equipment(): BelongsToMany {
        return $this->belongsToMany(Equipment::class, StorageEquipment::TABLE_NAME, 'storage_uuid', 'equipment_uuid', 'uuid', 'uuid')
            ->using(StorageEquipment::class)
            ->withPivot('uuid', 'amount')
            ->withTimestamps();
    }
}
