<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A service of a restaurant, opened when it starts and completed with its profit when it ends
 * @inheritdoc
 * @property Carbon started_at When the service started, in the restaurant's time
 * @property Carbon|null ended_at When the service ended, in the restaurant's time, or null while it is ongoing
 * @property float profit What the orders placed during the service were worth
 */
class ServiceHistory extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_service_history';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at'   => 'datetime',
        'profit'     => 'float',
    ];

    //| Relationships

    /**
     * The relationship for the orders placed during the service
     * @return HasMany
     */
    public function Orders(): HasMany {
        return $this->hasMany(Order::class, 'service_uuid', 'uuid');
    }
}
