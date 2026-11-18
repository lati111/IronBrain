<?php

namespace App\Models\DnD\Restaurant;

use App\Enum\DnD\Restaurant\WorkStation;
use App\Models\Auth\User;
use App\Models\DnD\Restaurant\Interfaces\RestaurantDataModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An employee of the restaurant, who during service can be put to work in the work areas of the kitchen, or wait tables.
 * They do one or the other, apart from working in both prep and cooking
 * @inheritdoc
 * @property string|null player_uuid The uuid of the player controlling the employee during service, if any
 * @property string name The name of the employee
 * @property string|null icon The path of the employee's icon
 * @property float cleaning_speed How much faster than normal the employee cleans, so 2 cleans twice as fast
 * @property float prep_speed How much faster than normal the employee preps, so 2 preps twice as fast
 * @property integer cooking_quality How much quality the employee adds to the meals they cook over their whole cooking time
 * @property integer capacity How many plates the employee carries to a table in one trip, as a waiter
 * @property string[]|null stations The codes of the work areas the employee works in, or null while idle or waiting
 * @property WorkStation|null station The first work area the employee works in, or null while idle. Setting it makes
 *                                    it the only one
 */
class Employee extends RestaurantDataModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__restaurant_employee';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @var int How many plates a new employee carries in one trip */
    public const int DEFAULT_CAPACITY = 2;

    /** @inheritdoc */
    protected $casts = [
        'cleaning_speed' => 'float',
        'prep_speed'     => 'float',
        'capacity'       => 'integer',
        'cooking_quality' => 'integer',
        'stations'       => 'array',
    ];

    /** @var float How fast an employee preps while they also work in the cooking area, compared to when they only prep */
    public const float PREP_WHILE_COOKING = 0.5;

    /**
     * The first work area the employee works in. Setting it has them work there only
     * @return Attribute
     */
    protected function station(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->getStations()[0] ?? null,
            set: fn (?WorkStation $station) => ['stations' => $station !== null ? json_encode([$station->value]) : null],
        );
    }

    /**
     * Gets the work areas the employee works in
     * @return WorkStation[] The work areas
     */
    public function getStations(): array
    {
        return array_map(fn (string $code) => WorkStation::from($code), $this->stations ?? []);
    }

    /**
     * Sets the work areas the employee works in
     * @param WorkStation[] $stations The work areas, where none leaves them idle
     */
    public function setStations(array $stations): void
    {
        $codes = array_values(array_unique(array_map(fn (WorkStation $station) => $station->value, $stations)));
        $this->stations = $codes !== [] ? $codes : null;
    }

    /**
     * Checks whether the employee works in a work area
     * @param WorkStation $station The work area
     * @return bool Whether they work there
     */
    public function worksAt(WorkStation $station): bool
    {
        return in_array($station->value, $this->stations ?? [], true);
    }

    /**
     * The relationship for the player controlling the employee during service
     * @return BelongsTo
     */
    public function Player(): BelongsTo {
        return $this->belongsTo(User::class, 'player_uuid', 'uuid');
    }

    /**
     * Gets how fast the employee works in a work area
     * @param WorkStation $station The work area
     * @return float The speed, where 1 is normal
     */
    public function getSpeed(WorkStation $station): float
    {
        return match ($station) {
            // Looking after the cookers as well takes up half of their time
            WorkStation::PREP     => $this->prep_speed * ($this->worksAt(WorkStation::COOKING) ? self::PREP_WHILE_COOKING : 1),
            WorkStation::CLEANING => $this->cleaning_speed,
            // Cooking goes at the cookers' speed, as long as someone looks after them
            WorkStation::COOKING  => 1.0,
            // Waiters walk at the same pace
            WorkStation::WAITING  => 1.0,
        };
    }

    //| Relationships

    /**
     * The relationship for the tables the employee waits
     * @return HasMany
     */
    public function Tables(): HasMany {
        return $this->hasMany(Table::class, 'employee_uuid', 'uuid');
    }
}
