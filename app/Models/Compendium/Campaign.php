<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @inheritdoc
 * @property string uuid
 * @property string title The campaign title
 * @property string description A short description of the campaign
 * @property string|null cover_src The filename for the cover image, if any
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Campaign extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__campaign';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';

    //| Relationships

    /**
     * The relationship to it's character information, if the article is about a character.
     * @return BelongsTo
     */
    public function character(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Compendium\Docs\Character::class, 'location_uuid', 'uuid');
    }

    //| Public methods

    /**
     * Find a player in this campaign by their user uuid.
     *
     * @param string $userUuid The uuid of the user to find the player for
     * @return Player|null The player
     */
    public function findPlayerByUser(string $userUuid): Player|null {
        /** @var Player|null $player */
        $player = $this->hasMany(Player::class, 'campaign_uuid', 'uuid')
            ->where('user_uuid', $userUuid)
            ->first();

        return $player;
    }
}
