<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritDoc
 * @property string uuid
 * @property string action_uuid The uuid of the action this cost is for
 * @property string resource_uuid The uuid of the resource that is decreased
 * @property string cost_formula The formula used to determine the change in resources
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class ActionCost extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__action_cost';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
