<?php

namespace App\Enum\DnD\Timeline;

use App\Models\DnD\Timeline\Event;
use App\Models\DnD\Timeline\Interfaces\TimelineItemModel;
use App\Models\DnD\Timeline\Period;
use App\Models\DnD\Timeline\Timespan;

/**
 * The kinds of items a timeline holds. The case values are the codes used by the API, while the labels are what is
 * displayed to the user
 */
enum TimelineItemType: string
{
    /** Takes place on a single year, shown as a dot */
    case EVENT    = 'event';
    /** Takes place from one year to another, shown as a bar */
    case TIMESPAN = 'timespan';
    /** Like a timespan but wider, shown by coloring the background of the timeline */
    case PERIOD   = 'period';

    /**
     * Gets the display name of the type
     * @return string The display name
     */
    public function label(): string
    {
        return match ($this) {
            self::EVENT    => 'Event',
            self::TIMESPAN => 'Timespan',
            self::PERIOD   => 'Period',
        };
    }

    /**
     * Gets the model items of this type are saved as
     * @return class-string<TimelineItemModel> The model
     */
    public function model(): string
    {
        return match ($this) {
            self::EVENT    => Event::class,
            self::TIMESPAN => Timespan::class,
            self::PERIOD   => Period::class,
        };
    }

    /**
     * Whether items of this type cover a range of years, rather than a single year
     * @return bool Whether it covers a range of years
     */
    public function isRange(): bool
    {
        return $this !== self::EVENT;
    }

    /**
     * Gets the codes of every type
     * @return string[] The codes
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
