<?php

namespace App\Enum\DnD\Restaurant;

/**
 * A work area of the kitchen where employees work through a queue of jobs. The case values are the internal codes
 * stored in the database, while the labels are what is displayed to the user
 */
enum WorkStation: string
{
    /** Prepping planned meals, at the employees' prep speed */
    case PREP     = 'prep';
    /** Cleaning dirty equipment, at the employees' cleaning speed */
    case CLEANING = 'cleaning';

    /**
     * Gets the display name of the work area
     * @return string The display name
     */
    public function label(): string
    {
        return match ($this) {
            self::PREP     => 'Prep',
            self::CLEANING => 'Cleaning',
        };
    }

    /**
     * Gets the internal codes of every work area
     * @return string[] The internal codes
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
