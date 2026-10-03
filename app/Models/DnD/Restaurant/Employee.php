<?php

namespace App\Models\DnD\Restaurant;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;

/**
 * An employee of the restaurant, who can be put to work in a work area of the kitchen during service
 * @inheritdoc
 * @property string name The name of the employee
 * @property string|null icon The path of the employee's icon
 * @property float cleaning_speed How much faster than normal the employee cleans, so 2 cleans twice as fast
 * @property float prep_speed How much faster than normal the employee preps, so 2 preps twice as fast
 * @property WorkStation|null station The work area the employee works in, or null while idle
 */
class Employee extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_employee';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'cleaning_speed' => 'float',
        'prep_speed'     => 'float',
        'station'        => WorkStation::class,
    ];

    /**
     * Gets how fast the employee works in a work area
     * @param WorkStation $station The work area
     * @return float The speed, where 1 is normal
     */
    public function getSpeed(WorkStation $station): float
    {
        return match ($station) {
            WorkStation::PREP     => $this->prep_speed,
            WorkStation::CLEANING => $this->cleaning_speed,
        };
    }
}
