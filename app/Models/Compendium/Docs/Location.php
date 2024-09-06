<?php

namespace App\Models\Compendium\Docs;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @property string uuid
 * @property string|null parent_location_uuid The uuid of the parent location, if any
 * @property string campaign_uuid The uuid of the campaign this location belongs to
 * @property string article_uuid The uuid of the article attached to this location
 * @property string name The name given to this location
 * @property string|null description A short description of the location
 * @property string|null map_src The filename for the location map, if any
 * @property string|null tags A list of tags given to this character, seperated by ,s
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Location extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__location';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
