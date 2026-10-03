<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recipe sold at another price than usual, during one service only
 * @inheritdoc
 * @property string service_uuid The uuid of the service the sale is for
 * @property string recipe_uuid The uuid of the recipe on sale
 * @property float price The price of the recipe during the service
 */
class Sale extends DndRestaurantModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_sale';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'price' => 'float',
    ];

    //| Relationships

    /**
     * The relationship for the service the sale is for
     * @return BelongsTo
     */
    public function Service(): BelongsTo {
        return $this->belongsTo(ServiceHistory::class, 'service_uuid', 'uuid');
    }

    /**
     * The relationship for the recipe on sale
     * @return BelongsTo
     */
    public function Recipe(): BelongsTo {
        return $this->belongsTo(Recipe::class, 'recipe_uuid', 'uuid');
    }
}
