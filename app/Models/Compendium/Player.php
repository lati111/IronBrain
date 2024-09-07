<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @property string uuid
 * @property string campaign_uuid The uuid of the campaign this player is part of
 * @property string user_uuid The uuid of the user this player is
 * @property string is_dm Whether or not this player is the DM
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Player extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__player';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
