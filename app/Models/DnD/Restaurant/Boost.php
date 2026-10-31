<?php

namespace App\Models\DnD\Restaurant;

use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;

/**
 * A boost the restaurant can activate during service as often as it likes. It is activated on any of its targets: a
 * single cooker, a piece of other equipment, an area of the kitchen, or a meal that is planned, prepped, cooking or
 * cooked.
 * While it lasts it adds to the speed of what it is on, as an equipment multiplier does, and when activated it can do
 * minutes of work straight away. On a meal it can also improve the quality it turns out at, and how long it stays
 * fresh once served
 * @inheritdoc
 * @property string name The display name of the boost
 * @property string[] targets What it can be activated on, of the TARGET constants
 * @property float speed_bonus Added to the speed of what it is on while it lasts, where normal speed is 1, so 0.1 adds
 *                             10% to whatever speed it is going at
 * @property float minutes_off Minutes of prep or cooking done straight away when activated
 * @property integer quality_bonus Added to the quality of a meal it is activated on
 * @property integer fresh_for_bonus Minutes added to how long a meal it is activated on stays fresh once served
 * @property integer duration How many minutes of the restaurant's time the speed bonus lasts
 */
class Boost extends RestaurantDataModel
{
    /** @var string Activated on a single cooker, speeding up the meal cooking in it */
    public const string TARGET_COOKER = 'cooker';
    /** @var string Activated on a piece of equipment other than a cooker, speeding up the meals that use it */
    public const string TARGET_EQUIPMENT = 'equipment';
    /** @var string Activated on the cooking area: everything cooking */
    public const string TARGET_COOKING_AREA = 'cooking_area';
    /** @var string Activated on the prep area: everything being prepped */
    public const string TARGET_PREP_AREA = 'prep_area';
    /** @var string Activated on the cleaning area: everything being cleaned */
    public const string TARGET_CLEANING_AREA = 'cleaning_area';
    /** @var string Activated on a meal that is planned, so waiting for or being prepped */
    public const string TARGET_PLANNED_MEAL = 'planned_meal';
    /** @var string Activated on a prepped meal, waiting to be cooked */
    public const string TARGET_PREPPED_MEAL = 'prepped_meal';
    /** @var string Activated on a meal that is cooking in a cooker */
    public const string TARGET_COOKING_MEAL = 'cooking_meal';
    /** @var string Activated on a meal that is done cooking or being served */
    public const string TARGET_COOKED_MEAL = 'cooked_meal';

    /** @var string[] Everything a boost can be activated on, in the order they are offered */
    public const array TARGETS = [
        self::TARGET_COOKER, self::TARGET_EQUIPMENT, self::TARGET_COOKING_AREA, self::TARGET_PREP_AREA,
        self::TARGET_CLEANING_AREA, self::TARGET_PLANNED_MEAL, self::TARGET_PREPPED_MEAL, self::TARGET_COOKING_MEAL,
        self::TARGET_COOKED_MEAL,
    ];

    /** @var string[] The targets that are meals, which quality and freshness bonuses are for */
    public const array MEAL_TARGETS = [
        self::TARGET_PLANNED_MEAL, self::TARGET_PREPPED_MEAL, self::TARGET_COOKING_MEAL, self::TARGET_COOKED_MEAL,
    ];

    /** @var string The area where meals and conversions are prepped */
    public const string AREA_PREP = 'prep';
    /** @var string The area where meals cook */
    public const string AREA_COOKING = 'cooking';
    /** @var string The area where equipment is cleaned */
    public const string AREA_CLEANING = 'cleaning';
    /** @var array<string, string> The areas a boost can be activated on, with the target each is */
    public const array AREAS = [
        self::AREA_PREP => self::TARGET_PREP_AREA,
        self::AREA_COOKING => self::TARGET_COOKING_AREA,
        self::AREA_CLEANING => self::TARGET_CLEANING_AREA,
    ];

    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_boost';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'targets'         => 'array',
        'speed_bonus'   => 'float',
        'minutes_off'   => 'float',
        'quality_bonus' => 'integer',
        'fresh_for_bonus' => 'integer',
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

    /**
     * Whether the boost can be activated on a kind of target
     * @param string $target The target, one of the TARGET constants
     * @return bool Whether it can
     */
    public function allows(string $target): bool
    {
        return in_array($target, $this->targets ?? [], true);
    }

    /**
     * Whether the boost can be activated on any kind of meal, which its quality and freshness bonuses are for
     * @return bool Whether it can
     */
    public function allowsMeals(): bool
    {
        return array_intersect($this->targets ?? [], self::MEAL_TARGETS) !== [];
    }
}
