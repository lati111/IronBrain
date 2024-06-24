<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @inheritDoc
 * @property string uuid
 * @property string creature_template The creature template to apply the resistance modifier to
 * @property string resistance_modifier_uuid The uuid of the resistance modifier to add
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class CreatureTemplateResistance extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__creature_template_resistance_modifier';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
