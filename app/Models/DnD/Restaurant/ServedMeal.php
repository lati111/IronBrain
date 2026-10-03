<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A plated portion of a cooked meal. It holds the recipe's serving equipment until the meal has been eaten
 * @inheritdoc
 * @property string recipe_uuid The uuid of the recipe the portion is of
 * @property string|null order_item_uuid The uuid of the order entry it was brought for, or null while waiting
 * @property array|null equipment The serving equipment in use, as equipment_uuid and amount
 */
class ServedMeal extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_served_meal';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'equipment' => 'array',
    ];

    //| Relationships

    /**
     * The relationship for the recipe the portion is of
     * @return BelongsTo
     */
    public function Recipe(): BelongsTo {
        return $this->belongsTo(Recipe::class, 'recipe_uuid', 'uuid');
    }

    /**
     * The relationship for the order entry it was brought for
     * @return BelongsTo
     */
    public function OrderItem(): BelongsTo {
        return $this->belongsTo(OrderItem::class, 'order_item_uuid', 'uuid');
    }
}
