<?php

namespace App\Models\DnD\Timeline;

use App\Models\DnD\Timeline\Interfaces\DndTimelineModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved set of tag lists to filter and highlight a timeline by
 * @inheritdoc
 * @property string timeline_uuid The uuid of the timeline the preset belongs to
 * @property string name The display name of the preset, unique within its timeline
 * @property string|null blacklist The tags that hide an item, as a comma separated list
 * @property string|null whitelist The tags an item must all have to be shown, as a comma separated list
 * @property string|null highlight The tags that highlight an item, as a comma separated list
 */
class FilterPreset extends DndTimelineModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__timeline_filter_preset';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @var string[] The tag lists of a preset */
    public const array LISTS = ['blacklist', 'whitelist', 'highlight'];

    /**
     * Gets one of the tag lists, without surrounding whitespace or empty entries
     * @param string $list The name of the list, one of LISTS
     * @return string[] The tags
     */
    public function getList(string $list): array
    {
        if ($this->{$list} === null) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $this->{$list})), fn (string $tag) => $tag !== ''));
    }

    /**
     * Sets one of the tag lists, leaving out duplicates and empty entries. Does not save
     * @param string $list The name of the list, one of LISTS
     * @param string[] $tags The tags
     */
    public function setList(string $list, array $tags): void
    {
        $tags = array_unique(array_filter(array_map('trim', $tags), fn (string $tag) => $tag !== ''));
        $this->{$list} = count($tags) === 0 ? null : implode(',', $tags);
    }

    //| Relationships

    /**
     * The relationship for the timeline the preset belongs to
     * @return BelongsTo
     */
    public function Timeline(): BelongsTo {
        return $this->belongsTo(Timeline::class, 'timeline_uuid', 'uuid');
    }
}
