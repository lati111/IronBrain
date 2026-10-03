<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

/**
 * @inheritdoc
 * @property string storage_uuid The uuid of the storage space
 * @property string ingredient_uuid The uuid of the ingredient
 * @property float amount How much of the ingredient is stored
 */
class StorageIngredient extends DndRestaurantModel
{
    use AsPivot;

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_storage_ingredient';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** Pivot attributes such as amount are filled on attach, so nothing is guarded, as with Laravel's Pivot class */
    protected $guarded = [];

    /** @inheritdoc */
    protected $casts = [
        'amount' => 'float',
    ];

    //| Relationships

    /**
     * The relationship for the storage space
     * @return BelongsTo
     */
    public function Storage(): BelongsTo {
        return $this->belongsTo(Storage::class, 'storage_uuid', 'uuid');
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
     * Get the storage space
     * @return Storage
     */
    public function getStorage(): Storage
    {
        /** @var Storage $storage */
        $storage = $this->Storage()->first();
        return $storage;
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
