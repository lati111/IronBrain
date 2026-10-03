<?php

namespace App\Enum\DnD\Restaurant;

/**
 * When during making a recipe a piece of equipment is used. The case values are the internal codes stored in the
 * database, while the labels are what is displayed to the user
 */
enum EquipmentStage: string
{
    /** In use while the meal is planned, dirty once prepped, or once cooking starts for a container */
    case PREP    = 'prep';
    /** In use while the meal cooks, dirty once cooked, or once the meal is gone for a container */
    case COOKING = 'cooking';
    /** In use each time the meal is served. Not acted on yet, and can't be a container */
    case SERVING = 'serving';

    /**
     * Gets the display name of the stage
     * @return string The display name
     */
    public function label(): string
    {
        return match ($this) {
            self::PREP    => 'Prep',
            self::COOKING => 'Cooking',
            self::SERVING => 'Serving',
        };
    }

    /**
     * Whether equipment used at this stage can be marked as a container
     * @return bool Whether it can be a container
     */
    public function allowsContainer(): bool
    {
        return $this !== self::SERVING;
    }

    /**
     * Gets the internal codes of every stage
     * @return string[] The internal codes
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
