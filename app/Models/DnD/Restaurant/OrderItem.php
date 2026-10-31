<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A single portion of a recipe within an order
 * @inheritdoc
 * @property string order_uuid The uuid of the order the entry is part of
 * @property string|null recipe_uuid The uuid of the recipe ordered, or null when the recipe was deleted
 * @property string|null customer_uuid The uuid of the customer it is for, or null when for the table as a whole
 * @property string name The name of the recipe when it was ordered
 * @property float price The price of the recipe when it was ordered
 * @property Carbon|null served_at When the portion was brought to the table, in the restaurant's time
 * @property Carbon|null consumed_at When the portion was eaten, in the restaurant's time
 * @property integer|null satisfaction How satisfied the customer was with the portion, from 1 to 5, rated once served
 */
class OrderItem extends DndRestaurantModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_order_item';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'price'     => 'float',
        'served_at'   => 'datetime',
        'consumed_at' => 'datetime',
        'satisfaction' => 'integer',
    ];

    //| Relationships

    /**
     * The relationship for the order the entry is part of
     * @return BelongsTo
     */
    public function Order(): BelongsTo {
        return $this->belongsTo(Order::class, 'order_uuid', 'uuid');
    }

    /**
     * The relationship for the customer the entry is for
     * @return BelongsTo
     */
    public function Customer(): BelongsTo {
        return $this->belongsTo(Customer::class, 'customer_uuid', 'uuid');
    }

    /**
     * The relationship for the plated portion that was brought for this entry
     * @return HasOne
     */
    public function ServedMeal(): HasOne {
        return $this->hasOne(ServedMeal::class, 'order_item_uuid', 'uuid');
    }
}
