<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

/**
 * @inheritdoc
 * @property string storage_uuid The uuid of the storage space
 * @property string equipment_uuid The uuid of the equipment
 * @property integer amount How much of the equipment is stored
 */
class StorageEquipment extends DndRestaurantModel
{
    use AsPivot;

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_storage_equipment';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'amount' => 'integer',
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
     * The relationship for the equipment
     * @return BelongsTo
     */
    public function Equipment(): BelongsTo {
        return $this->belongsTo(Equipment::class, 'equipment_uuid', 'uuid');
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
     * Get the equipment
     * @return Equipment
     */
    public function getEquipment(): Equipment
    {
        /** @var Equipment $equipment */
        $equipment = $this->Equipment()->first();
        return $equipment;
    }
}
