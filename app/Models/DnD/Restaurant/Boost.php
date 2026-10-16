<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;

/**
 * A boost the restaurant can activate during service as often as it likes. It is activated on a piece of equipment, or
 * a single cooker of it, a prepped or cooking meal, or a whole area of the kitchen. While it lasts it adds to the speed
 * of what it is on, as an equipment multiplier does, and when activated it can do minutes of work straight away. On a
 * meal it can also improve the quality it turns out at
 * @inheritdoc
 * @property string name The display name of the boost
 * @property string target What it is activated on, one of the TARGET constants
 * @property float speed_bonus Added to the speed of what it is on while it lasts, so 0.5 makes normal speed 1.5
 * @property float minutes_off Minutes of prep or cooking done straight away when activated
 * @property integer quality_bonus Added to the quality of a meal it is activated on
 * @property integer duration How many minutes of the restaurant's time the speed bonus lasts
 */
class Boost extends RestaurantDataModel
{
    /** @var string Activated on a piece of equipment, speeding up the meals that use it */
    public const string TARGET_EQUIPMENT = 'equipment';
    /** @var string Activated on a prepped or cooking meal */
    public const string TARGET_MEAL = 'meal';
    /** @var string Activated on a whole area of the kitchen: everything being prepped, cooking or cleaned */
    public const string TARGET_AREA = 'area';
    /** @var string[] Everything a boost can be activated on */
    public const array TARGETS = [self::TARGET_EQUIPMENT, self::TARGET_MEAL, self::TARGET_AREA];

    /** @var string The area where meals and conversions are prepped */
    public const string AREA_PREP = 'prep';
    /** @var string The area where meals cook */
    public const string AREA_COOKING = 'cooking';
    /** @var string The area where equipment is cleaned */
    public const string AREA_CLEANING = 'cleaning';
    /** @var string[] The areas a boost can be activated on */
    public const array AREAS = [self::AREA_PREP, self::AREA_COOKING, self::AREA_CLEANING];

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_boost';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'speed_bonus'   => 'float',
        'minutes_off'   => 'float',
        'quality_bonus' => 'integer',
        'duration'      => 'integer',
    ];

    /**
     * Whether activating the boost leaves something that lasts: a speed bonus with a duration
     * @return bool Whether it lasts
     */
    public function lasts(): bool
    {
        return $this->speed_bonus != 0 && $this->duration > 0;
    }
}
