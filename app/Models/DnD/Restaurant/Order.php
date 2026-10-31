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
 * @property string status taking while the waiter takes it, open, fulfilled once every entry is served, finished
 *                         once paid for, or cancelled
 * @property string|null service_uuid The uuid of the service the order was placed during
 * @property float total_price What the items of the order are worth together
 * @property float tip What the table gives on top of the price, in bits
 * @property float discount What is taken off the price, in bits
 * @property Carbon ordered_at When the table ordered, in the restaurant's time: once the waiter is done taking the
 *                           order, or while they still are, when they went to take it
 * @property Carbon|null fulfilled_at When the last entry was served, in the restaurant's time
 * @property Carbon|null paid_at When the order's money went into the shift total, in the restaurant's time
 */
class Order extends RestaurantDataModel
{
    /** @var string The waiter is still taking the order */
    public const string STATUS_TAKING = 'taking';
    /** @var string The waiter has taken the order, and what was ordered has yet to be noted down */
    public const string STATUS_TAKEN = 'taken';
    /** @var string Some of the entries still have to be served */
    public const string STATUS_OPEN = 'open';
    /** @var string Every entry has been served, and the meal is being eaten */
    public const string STATUS_FULFILLED = 'fulfilled';
    /** @var string The order has been paid for, and the table is done */
    public const string STATUS_FINISHED = 'finished';
    /** @var string The order was called off, and nothing is paid for it */
    public const string STATUS_CANCELLED = 'cancelled';
    /** @var string[] The statuses of orders that are still being served or eaten */
    public const array ACTIVE_STATUSES = [self::STATUS_TAKING, self::STATUS_TAKEN, self::STATUS_OPEN, self::STATUS_FULFILLED];

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_order';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'total_price'  => 'float',
        'tip'          => 'float',
        'discount'     => 'float',
        'ordered_at'   => 'datetime',
        'fulfilled_at' => 'datetime',
        'paid_at'      => 'datetime',
    ];

    /**
     * Gets what the table pays for the order: the price of its items, less the discount, plus the tip
     * @return float The amount in bits, never below zero
     */
    public function getPayable(): float
    {
        return max(0.0, round($this->total_price - $this->discount + $this->tip, 2));
    }

    /**
     * Recalculates what the items are worth, and whether the order is fulfilled: it is once everything is served, and
     * becomes open again when items are added that still have to be served. An order still being taken, or taken but
     * not noted down yet, stays so.
     * Does not save
     * @param Carbon $now The restaurant's current time
     */
    public function recalculate(Carbon $now): void
    {
        $this->total_price = round((float) $this->Items()->sum('price'), 2);
        if ($this->status === self::STATUS_TAKING || $this->status === self::STATUS_TAKEN) {
            return;
        }

        if ($this->Items()->whereNull('served_at')->exists()) {
            $this->status = self::STATUS_OPEN;
            $this->fulfilled_at = null;
        } elseif ($this->status !== self::STATUS_FULFILLED) {
            $this->status = self::STATUS_FULFILLED;
            $this->fulfilled_at = $now;
        }
    }

    /**
     * Whether the order is still being served or eaten, so it can be changed
     * @return bool Whether it is active
     */
    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

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
