<?php

namespace App\Models\DnD\Restaurant\Interfaces;

use App\Models\DnD\Restaurant\Restaurant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @inheritdoc
 * @property string restaurant_uuid The uuid of the restaurant this item belongs to
 */
abstract class RestaurantDataModel extends DndRestaurantModel
{
    /**
     * Get the restaurant this item belongs to
     * @return Restaurant
     */
    public function getRestaurant(): Restaurant
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->Restaurant()->first();
        return $restaurant;
    }

    /**
     * The relationship for the restaurant this item belongs to
     * @return BelongsTo
     */
    public function Restaurant(): BelongsTo {
        return $this->belongsTo(Restaurant::class, 'restaurant_uuid', 'uuid');
    }
}
