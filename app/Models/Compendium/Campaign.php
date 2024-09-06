<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
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
}
