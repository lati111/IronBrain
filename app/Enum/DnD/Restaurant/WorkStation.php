<?php

namespace App\Enum\DnD\Restaurant;

/**
 * Where employees work and work is queued. The prep and cleaning areas of the kitchen each have a queue worked through
 * by the employees there, while waiting has a queue for every table, worked through by its waiter. The cooking area
 * has no queue, as meals cook in the cookers, but only while someone works there. The case values are the internal
 * codes stored in the database, while the labels are what is displayed to the user
 */
enum WorkStation: string
{
    /** Prepping planned meals, at the employees' prep speed */
    case PREP     = 'prep';
    /** Cleaning dirty equipment, at the employees' cleaning speed */
    case CLEANING = 'cleaning';
    /** Looking after the cookers, which only cook while someone does */
    case COOKING  = 'cooking';
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
            self::COOKING  => 'Cooking',
            self::WAITING  => 'Waiting',
        };
    }

    /**
     * Gets the work areas of the kitchen with a queue of work, which the employees there work through
     * @return self[] The work areas
     */
    public static function areas(): array
    {
        return [self::PREP, self::CLEANING];
    }

    /**
     * Gets the work areas of the kitchen employees can be put to work in
     * @return self[] The work areas
     */
    public static function assignable(): array
    {
        return [self::PREP, self::COOKING, self::CLEANING];
    }

    /**
     * Gets the internal codes of the work areas of the kitchen, which employees can be put to work in
     * @return string[] The internal codes
     */
    public static function values(): array
    {
        return array_map(fn (self $station) => $station->value, self::assignable());
    }
}
