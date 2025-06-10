<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * The relationship to all articles on this campaign.
     * @return HasMany
     */
    public function articles(): HasMany
    {
        return $this->hasMany(\App\Models\Compendium\Docs\Article::class, 'campaign_uuid', 'uuid');
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

    /**
     * Get the path leading to the cover. If no cover is set, the placeholder image is used instead.
     *
     * @return string The path to the cover
     */
    public function getCoverPath(): string {
        return $this->cover_src !== null ? 'img/modules/compendium/campaign_cover/'.$this->cover_src : 'img/placeholder.png';
    }
}
