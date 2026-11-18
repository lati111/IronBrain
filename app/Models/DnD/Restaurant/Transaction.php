<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money going in or out of a restaurant, such as the profit of a service being paid into it, or one added by hand
 * @inheritdoc
 * @property string type What the transaction was, one of the TYPE constants
 * @property string title What the transaction is shown as
 * @property string|null description More about the transaction, shown when it is opened
 * @property float amount How much money went in, in bits, negative when it went out
 * @property string|null service_uuid The uuid of the service the transaction is the profit of
 * @property Carbon occurred_at When the transaction happened, in the restaurant's time
 */
class Transaction extends RestaurantDataModel
{
    /** @var string The profit of a service, paid in when it ends */
    public const string TYPE_SERVICE = 'service';
    /** @var string Added to the log by hand, for money going in or out outside of service */
    public const string TYPE_MANUAL = 'manual';

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_transaction';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'amount'      => 'float',
        'occurred_at' => 'datetime',
    ];

    //| Relationships

    /**
     * The relationship for the service the transaction is the profit of
     * @return BelongsTo
     */
    public function Service(): BelongsTo {
        return $this->belongsTo(ServiceHistory::class, 'service_uuid', 'uuid');
    }
}
