<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Turns an ingredient into another, such as a potato into fries. In the inventory it happens straight away, while
 * during service it is done in the prep area, taking time for every piece converted
 * @inheritdoc
 * @property string input_uuid The uuid of the ingredient converted
 * @property string output_uuid The uuid of the ingredient it becomes
 * @property float output_amount How much of the output each piece of the input becomes
 * @property float minutes How many minutes converting a piece of the input takes during service, at normal speed
 */
class Conversion extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_conversion';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'output_amount' => 'float',
        'minutes'       => 'float',
    ];

    /**
     * Gets how much of the output an amount of the input becomes
     * @param float $amount How much of the input
     * @return float How much of the output, with two decimals
     */
    public function getOutputFor(float $amount): float
    {
        return round($amount * $this->output_amount, 2);
    }

    //| Relationships

    /**
     * The relationship for the ingredient converted
     * @return BelongsTo
     */
    public function Input(): BelongsTo {
        return $this->belongsTo(Ingredient::class, 'input_uuid', 'uuid');
    }

    /**
     * The relationship for the ingredient it becomes
     * @return BelongsTo
     */
    public function Output(): BelongsTo {
        return $this->belongsTo(Ingredient::class, 'output_uuid', 'uuid');
    }
}
