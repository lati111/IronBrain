<?php

namespace App\Models\DnD\Timeline;

use App\Models\DnD\Timeline\Interfaces\DndTimelineModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A category the items of a timeline are sorted into, shown in its own color
 * @inheritdoc
 * @property string timeline_uuid The uuid of the timeline the category belongs to
 * @property string name The display name of the category, unique within its timeline
 * @property string|null description The description of the category
 * @property string color The color of the category as #RRGGBB
 */
class Category extends DndTimelineModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__timeline_category';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the timeline the category belongs to
     * @return BelongsTo
     */
    public function Timeline(): BelongsTo {
        return $this->belongsTo(Timeline::class, 'timeline_uuid', 'uuid');
    }

    /**
     * The relationship for the events in this category
     * @return HasMany
     */
    public function Events(): HasMany {
        return $this->hasMany(Event::class, 'category_uuid', 'uuid');
    }

    /**
     * The relationship for the timespans in this category
     * @return HasMany
     */
    public function Timespans(): HasMany {
        return $this->hasMany(Timespan::class, 'category_uuid', 'uuid');
    }

    /**
     * The relationship for the periods in this category
     * @return HasMany
     */
    public function Periods(): HasMany {
        return $this->hasMany(Period::class, 'category_uuid', 'uuid');
    }
}
