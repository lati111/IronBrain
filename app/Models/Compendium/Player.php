<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @property string uuid
 * @property string campaign_uuid The uuid of the campaign this character belongs to
 * @property string article_uuid The uuid of the article attached to this character
 * @property string name The name given to this character
 * @property string|null titles A list of titles given to this character, seperated by ,s
 * @property string|null description A short description of the character
 * @property string|null image_src The filename for the character image, if any
 * @property string|null tags A list of tags given to this character, seperated by ,s
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Player extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__character';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
