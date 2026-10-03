<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An order placed at a table, made up of an entry for each portion of a recipe ordered
 * @inheritdoc
 * @property string|null table_uuid The uuid of the table the order was placed at
 * @property string|null service_uuid The uuid of the service the order was placed during
 * @property string status open, fulfilled once every entry is served, or finished once the meal is consumed
 * @property float total_price What the order is worth
 * @property Carbon ordered_at When the order was placed, in the restaurant's time
 * @property Carbon|null fulfilled_at When the last entry was served, in the restaurant's time
 */
class Order extends RestaurantDataModel
{
    /** @var string Some of the entries still have to be served */
    public const string STATUS_OPEN = 'open';
    /** @var string Every entry has been served, and the meal is being eaten */
    public const string STATUS_FULFILLED = 'fulfilled';
    /** @var string The meal has been consumed */
    public const string STATUS_FINISHED = 'finished';

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_order';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'total_price'  => 'float',
        'ordered_at'   => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    //| Relationships

    /**
     * The relationship for the entries of the order
     * @return HasMany
     */
    public function Items(): HasMany {
        return $this->hasMany(OrderItem::class, 'order_uuid', 'uuid');
    }

    /**
     * The relationship for the table the order was placed at
     * @return BelongsTo
     */
    public function Table(): BelongsTo {
        return $this->belongsTo(Table::class, 'table_uuid', 'uuid');
    }
}
