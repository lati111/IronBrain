<?php

namespace App\Models\Compendium\Interfaces;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * @inheritDoc
 * @property string uuid
 * @property string trait_uuid The uuid of the trait this applies to
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

abstract class AbstractTrait extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
