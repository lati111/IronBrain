<?php

namespace App\Enum\DnD\Restaurant;

/**
 * Where work is queued. The prep and cleaning areas of the kitchen each have a queue worked through by the employees
 * there, while waiting has a queue for every table, worked through by its waiter. The case values are the internal
 * codes stored in the database, while the labels are what is displayed to the user
 */
enum WorkStation: string
{
    /** Prepping planned meals, at the employees' prep speed */
    case PREP     = 'prep';
    /** Cleaning dirty equipment, at the employees' cleaning speed */
    case CLEANING = 'cleaning';
    /** Taking orders and bringing plated portions, by the waiter of the table, at normal speed */
    case WAITING  = 'waiting';

    /**
     * Gets the display name of the work area
     * @return string The display name
     */
    public function label(): string
    {
        return match ($this) {
            self::PREP     => 'Prep',
            self::CLEANING => 'Cleaning',
            self::WAITING  => 'Waiting',
        };
    }

    /**
     * Gets the work areas of the kitchen, which employees are put to work in
     * @return self[] The work areas
     */
    public static function areas(): array
    {
        return [self::PREP, self::CLEANING];
    }

    /**
     * Gets the internal codes of the work areas of the kitchen, which employees can be put to work in
     * @return string[] The internal codes
     */
    public static function values(): array
    {
        return array_map(fn (self $station) => $station->value, self::areas());
    }
}
