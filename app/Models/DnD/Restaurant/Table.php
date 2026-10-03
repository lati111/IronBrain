<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A table customers can be seated at
 * @inheritdoc
 * @property string|null room_uuid The uuid of the room the table is placed in, or null when it has not been placed
 * @property string name The display name of the table
 * @property integer capacity How many customers can be seated at the table
 */
class Table extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_table';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'capacity' => 'integer',
    ];

    //| Relationships

    /**
     * The relationship for the room the table is placed in
     * @return BelongsTo
     */
    public function Room(): BelongsTo {
        return $this->belongsTo(Room::class, 'room_uuid', 'uuid');
    }

    /**
     * The relationship for the customers seated at the table
     * @return HasMany
     */
    public function Customers(): HasMany {
        return $this->hasMany(Customer::class, 'table_uuid', 'uuid');
    }

    //| Getters

    /**
     * Get the room the table is placed in
     * @return Room|null
     */
    public function getRoom(): Room|null
    {
        /** @var Room|null $room */
        $room = $this->Room()->first();
        return $room;
    }
}
