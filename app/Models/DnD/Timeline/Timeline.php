<?php

namespace App\Models\DnD\Timeline;

use App\Models\Auth\User;
use App\Models\DnD\Timeline\Interfaces\DndTimelineModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The container for everything that takes place within a timeline
 * @inheritdoc
 * @property string owner_uuid The uuid of the user that owns this timeline
 * @property string name The display name of the timeline
 * @property string|null description The description of the timeline
 */
class Timeline extends DndTimelineModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__timeline_timeline';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /**
     * Check whether a user may access this timeline
     * @param User $user The user to check
     * @return bool Whether the user has access
     */
    public function hasAccess(User $user): bool
    {
        return $this->owner_uuid === $user->uuid;
    }

    //| Relationships

    /**
     * The relationship for the timeline's owner
     * @return BelongsTo
     */
    public function Owner(): BelongsTo {
        return $this->belongsTo(User::class, 'owner_uuid', 'uuid');
    }

    /**
     * The relationship for the timeline's categories
     * @return HasMany
     */
    public function Categories(): HasMany {
        return $this->hasMany(Category::class, 'timeline_uuid', 'uuid');
    }

    /**
     * The relationship for the events of the timeline
     * @return HasMany
     */
    public function Events(): HasMany {
        return $this->hasMany(Event::class, 'timeline_uuid', 'uuid');
    }

    /**
     * The relationship for the timespans of the timeline
     * @return HasMany
     */
    public function Timespans(): HasMany {
        return $this->hasMany(Timespan::class, 'timeline_uuid', 'uuid');
    }

    /**
     * The relationship for the periods of the timeline
     * @return HasMany
     */
    public function Periods(): HasMany {
        return $this->hasMany(Period::class, 'timeline_uuid', 'uuid');
    }

    //| Getters

    /**
     * Get the owner of this timeline
     * @return User
     */
    public function getOwner(): User
    {
        /** @var User $user */
        $user = $this->Owner()->first();
        return $user;
    }
}
