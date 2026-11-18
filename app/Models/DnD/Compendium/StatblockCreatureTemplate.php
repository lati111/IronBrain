<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @inheritDoc
 * @property string uuid
 * @property string statblock_uuid The uuid of the statblock this creature template applies to
 * @property string creature_template The creature template to apply to the statblock
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class StatblockCreatureTemplate extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__statblock_creature_template';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
