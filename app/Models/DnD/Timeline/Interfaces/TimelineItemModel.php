<?php

namespace App\Models\DnD\Timeline\Interfaces;

use App\Models\DnD\Timeline\Category;
use App\Models\DnD\Timeline\Timeline;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something that takes place within a timeline, either on a single year or over a range of years
 * @inheritdoc
 * @property string timeline_uuid The uuid of the timeline the item belongs to
 * @property string|null category_uuid The uuid of the item's category, or null when its category was deleted
 * @property string name The display name of the item
 * @property string|null description The description of the item
 * @property string|null tags The tags of the item as a comma separated list
 * @property string|null source Where the item comes from, which is kept out of the UI
 */
abstract class TimelineItemModel extends DndTimelineModel
{
    /** @var string[] The source is for keeping track of where an item comes from, rather than part of the timeline */
    protected $hidden = ['source'];

    /**
     * Gets the tags of the item, without surrounding whitespace or empty entries
     * @return string[] The tags
     */
    public function getTags(): array
    {
        if ($this->tags === null) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->tags)), fn (string $tag) => $tag !== ''));
    }

    /**
     * Sets the tags of the item, leaving out duplicates and empty entries. Does not save
     * @param string[] $tags The tags
     */
    public function setTags(array $tags): void
    {
        $tags = array_unique(array_filter(array_map('trim', $tags), fn (string $tag) => $tag !== ''));
        $this->tags = count($tags) === 0 ? null : implode(',', $tags);
    }

    /**
     * Checks whether the item has a tag
     * @param string $tag The tag to look for
     * @return bool Whether the item has the tag
     */
    public function hasTag(string $tag): bool
    {
        return in_array(trim($tag), $this->getTags(), true);
    }

    //| Relationships

    /**
     * The relationship for the timeline the item belongs to
     * @return BelongsTo
     */
    public function Timeline(): BelongsTo {
        return $this->belongsTo(Timeline::class, 'timeline_uuid', 'uuid');
    }

    /**
     * The relationship for the item's category
     * @return BelongsTo
     */
    public function Category(): BelongsTo {
        return $this->belongsTo(Category::class, 'category_uuid', 'uuid');
    }
}
