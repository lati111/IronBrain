<?php

namespace App\Models\DnD\Timeline;

use App\Models\DnD\Timeline\Interfaces\TimelineItemModel;

/**
 * Something that takes place on a single year
 * @inheritdoc
 * @property int year The year the event takes place on
 */
class Event extends TimelineItemModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__timeline_event';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'year' => 'integer',
    ];
}
