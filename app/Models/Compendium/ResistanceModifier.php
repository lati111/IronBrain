<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @inheritDoc
 * @property string uuid
 * @property string element The element code for this modifier
 * @property int stage The amount to modify this resistance by
 * @property bool is_base Whether this sets the base resistance, or modifies it
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class ResistanceModifier extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__resistance_modifier';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
