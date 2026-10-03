<?php

namespace App\Models\DnD\Restaurant;

use App\Enum\DnD\Restaurant\EquipmentStage;
use App\Models\DnD\Restaurant\Interfaces\DndRestaurantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Concerns\AsPivot;

/**
 * @inheritdoc
 * @property string recipe_uuid The uuid of the recipe
 * @property string equipment_uuid The uuid of the equipment
 * @property integer amount How much of the equipment the recipe requires
 * @property EquipmentStage stage When during making the recipe the equipment is used
 * @property bool container Whether the equipment holds the meal, staying in use for longer
 */
class RecipeEquipment extends DndRestaurantModel
{
    use AsPivot;

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_recipe_equipment';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** Pivot attributes such as amount are filled on attach, so nothing is guarded, as with Laravel's Pivot class */
    protected $guarded = [];

    /** @inheritdoc */
    protected $casts = [
        'amount'    => 'integer',
        'stage'     => EquipmentStage::class,
        'container' => 'boolean',
    ];

    //| Relationships

    /**
     * The relationship for the recipe
     * @return BelongsTo
     */
    public function Recipe(): BelongsTo {
        return $this->belongsTo(Recipe::class, 'recipe_uuid', 'uuid');
    }

    /**
     * The relationship for the equipment
     * @return BelongsTo
     */
    public function Equipment(): BelongsTo {
        return $this->belongsTo(Equipment::class, 'equipment_uuid', 'uuid');
    }

    //| Getters

    /**
     * Get the recipe
     * @return Recipe
     */
    public function getRecipe(): Recipe
    {
        /** @var Recipe $recipe */
        $recipe = $this->Recipe()->first();
        return $recipe;
    }

    /**
     * Get the equipment
     * @return Equipment
     */
    public function getEquipment(): Equipment
    {
        /** @var Equipment $equipment */
        $equipment = $this->Equipment()->first();
        return $equipment;
    }
}
