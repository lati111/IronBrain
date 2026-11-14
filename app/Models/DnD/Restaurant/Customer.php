<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * A customer that visits the restaurant. Players know them by their hidden name until whoever runs the restaurant
 * reveals their real one
 * @inheritdoc
 * @property string|null table_uuid The uuid of the table the customer is seated at, or null when not seated
 * @property integer|null seat The seat at the table the customer is seated on, from 1 up to its capacity
 * @property \Carbon\Carbon|null entered_at When the customer arrived at the entrance, in the restaurant's time, while
 *                                         they wait there to be seated
 * @property string name The real name of the customer
 * @property string|null hidden_name What players know the customer as until their name is revealed
 * @property bool revealed Whether players know the customer's real name
 * @property string|null description A description of the customer
 * @property integer tip_expectation How satisfied, from 1 to 5, a portion has to leave the customer for them to tip for it
 * @property float tip_rate What the customer tips for a portion, as a percentage of its price, for each satisfaction
 *                          from their expectation on
 * @property float tip_cap The most the customer tips for an order, in bits
 */
class Customer extends RestaurantDataModel
{
    /** @var string What players know a customer as that has no hidden name, until their name is revealed */
    public const string UNKNOWN_NAME = 'Stranger';

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_customer';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'entered_at' => 'datetime',
        'seat' => 'integer',
        'revealed' => 'boolean',
        'tip_expectation' => 'integer',
        'tip_rate' => 'float',
        'tip_cap' => 'float',
    ];

    /** @var array<string, bool> Whether the names of a restaurant's customers are hidden from the one looking, by restaurant and user */
    private static array $hiddenFor = [];

    //| Relationships

    /**
     * The relationship for the table the customer is seated at
     * @return BelongsTo
     */
    public function Table(): BelongsTo {
        return $this->belongsTo(Table::class, 'table_uuid', 'uuid');
    }

    //| Getters

    /**
     * Get the table the customer is seated at. Not named getTable, as that would override Eloquent's getter for the
     * database table name
     * @return Table|null
     */
    public function getSeatedTable(): Table|null
    {
        /** @var Table|null $table */
        $table = $this->Table()->first();
        return $table;
    }

    /**
     * Gets the name the one looking knows the customer by: the real name for whoever runs the restaurant or once it is
     * revealed, and the hidden name for players before that
     * @return string The name
     */
    public function getShownName(): string
    {
        if ($this->revealed || !$this->isHiddenFromViewer()) {
            return $this->name;
        }

        return $this->hidden_name ?: self::UNKNOWN_NAME;
    }

    /**
     * Whether the one looking is a player of the restaurant, who doesn't know customers' real names until revealed
     */
    private function isHiddenFromViewer(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        $key = $this->restaurant_uuid . ':' . $user->uuid;
        return self::$hiddenFor[$key] ??= Restaurant::find($this->restaurant_uuid)?->isPlayer($user) ?? false;
    }
}
