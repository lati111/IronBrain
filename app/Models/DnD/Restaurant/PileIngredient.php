<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An ingredient brought in during service, waiting in the restaurant's pile to be sorted into a storage space
 * @inheritdoc
 * @property string ingredient_uuid The uuid of the ingredient
 * @property float amount How much of the ingredient is in the pile
 * @property float days_left How many days the stack has left before it expires. Stacks of the same ingredient with
 *                           different days left are kept apart
 */
class PileIngredient extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_pile_ingredient';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'amount'    => 'float',
        'days_left' => 'float',
    ];

    //| Relationships

    /**
     * The relationship for the ingredient
     * @return BelongsTo
     */
    public function Ingredient(): BelongsTo {
        return $this->belongsTo(Ingredient::class, 'ingredient_uuid', 'uuid');
    }
}
