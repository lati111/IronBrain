<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer that visits the restaurant
 * @inheritdoc
 * @property string|null table_uuid The uuid of the table the customer is seated at, or null when not seated
 * @property string name The name of the customer
 * @property string|null description A description of the customer
 */
class Customer extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_customer';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

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
}
