<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

/**
 * @inheritdoc
 * @property string equipment_uuid The uuid of the owned equipment
 * @property integer amount How much of the equipment the restaurant owns
 */
class OwnedEquipment extends RestaurantDataModel
{
    use AsPivot;

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_owned_equipment';
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
     * The relationship for the owned equipment
     * @return BelongsTo
     */
    public function Equipment(): BelongsTo {
        return $this->belongsTo(Equipment::class, 'equipment_uuid', 'uuid');
    }

    //| Getters

    /**
     * Get the owned equipment
     * @return Equipment
     */
    public function getEquipment(): Equipment
    {
        /** @var Equipment $equipment */
        $equipment = $this->Equipment()->first();
        return $equipment;
    }
}
