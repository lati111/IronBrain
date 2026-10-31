<?php

namespace App\Enum\DnD\Restaurant;

/**
 * The categories equipment can fall under. The case values are the internal codes stored in the database, while
 * the labels are what is displayed to the user
 */
enum EquipmentCategory: string
{
    case COOKER    = 'cooker';
    case COOKWARE  = 'cookware';
    case TABLEWARE = 'tableware';

    /**
     * Gets the display name of the category
     * @return string The display name
     */
    public function label(): string
    {
        return match ($this) {
            self::COOKER    => 'Cooker',
            self::COOKWARE  => 'Cookware',
            self::TABLEWARE => 'Tableware',
        };
    }

    /**
     * Gets the internal codes of every category
     * @return string[] The internal codes
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
