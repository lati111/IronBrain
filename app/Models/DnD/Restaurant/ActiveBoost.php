<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A boost activated during service, adding to the speed of what it is on until it expires. It keeps the name and bonus
 * the boost had when activated, so changing the boost later leaves it as it was
 * @inheritdoc
 * @property string|null boost_uuid The uuid of the boost it was activated from, if that still exists
 * @property string name The name of the boost
 * @property float speed_bonus Added to the speed of what it is on
 * @property string|null equipment_uuid The uuid of the equipment it is on, if any
 * @property integer|null cooker_slot Which piece of a cooker it is on, from 1, or null when on all of them
 * @property string|null meal_uuid The uuid of the meal it is on, if any
 * @property string|null area The area of the kitchen it is on, one of the Boost::AREA constants, if any
 * @property Carbon starts_at When it was activated, in the restaurant's time
 * @property Carbon expires_at When it runs out, in the restaurant's time
 */
class ActiveBoost extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_active_boost';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'speed_bonus' => 'float',
        'cooker_slot' => 'integer',
        'starts_at'   => 'datetime',
        'expires_at'  => 'datetime',
    ];

    /**
     * Whether the boost is active at a moment
     * @param Carbon $at The moment, in the restaurant's time
     * @return bool Whether it is active
     */
    public function isActiveAt(Carbon $at): bool
    {
        return $this->starts_at->lte($at) && $this->expires_at->gt($at);
    }

    //| Relationships

    /**
     * The relationship for the equipment it is on
     * @return BelongsTo
     */
    public function Equipment(): BelongsTo {
        return $this->belongsTo(Equipment::class, 'equipment_uuid', 'uuid');
    }

    /**
     * The relationship for the meal it is on
     * @return BelongsTo
     */
    public function Meal(): BelongsTo {
        return $this->belongsTo(Meal::class, 'meal_uuid', 'uuid');
    }
}
