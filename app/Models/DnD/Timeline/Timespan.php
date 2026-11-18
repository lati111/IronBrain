<?php

namespace App\Models\DnD\Timeline;

use App\Models\DnD\Timeline\Interfaces\TimelineItemModel;

/**
 * Something that takes place from one year to another
 * @inheritdoc
 * @property int start_year The year the timespan starts on
 * @property int end_year The year the timespan ends on
 */
class Timespan extends TimelineItemModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__timeline_timespan';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'start_year' => 'integer',
        'end_year' => 'integer',
    ];

    /**
     * Checks whether a year falls within the timespan, including its start and end year
     * @param int $year The year to check
     * @return bool Whether the year falls within the timespan
     */
    public function contains(int $year): bool
    {
        return $year >= $this->start_year && $year <= $this->end_year;
    }
}
