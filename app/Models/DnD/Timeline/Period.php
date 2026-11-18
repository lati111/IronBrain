<?php

namespace App\Models\DnD\Timeline;

/**
 * Works the same as a timespan, but covers a wider period of time, such as an age or an era
 * @inheritdoc
 */
class Period extends Timespan
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__timeline_period';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;
}
