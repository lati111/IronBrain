<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @inheritDoc
 * @property string uuid
 * @property string statblock_uuid The uuid of the statblock this creature template applies to
 * @property string trait_uuid The uuid of the trait to apply
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class StatblockTrait extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__statblock_trait';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
