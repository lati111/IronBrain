<?php

namespace App\Models\Compendium\Docs;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @property string uuid
 * @property string player_uuid The uuid of the player that created this article
 * @property boolean dm_only Whether this article should only be shown to DMs
 * @property boolean private Whether this article should only be shown to the DM and the player
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Article extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__article';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
