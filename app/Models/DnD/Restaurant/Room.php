<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A room of the restaurant. Rooms only group the tables placed in them, and have no capacity of their own
 * @inheritdoc
 * @property string name The display name of the room
 * @property string|null background_image The path to the background image, if any
 * @property integer order The position of the room within the restaurant
 */
class Room extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_room';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'order' => 'integer',
    ];

    //| Relationships

    /**
     * The relationship for the tables placed in this room
     * @return HasMany
     */
    public function Tables(): HasMany {
        return $this->hasMany(Table::class, 'room_uuid', 'uuid');
    }
}
