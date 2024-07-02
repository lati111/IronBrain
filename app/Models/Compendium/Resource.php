<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use App\Models\Compendium\Traits\CompendiumTrait;
use App\Models\Compendium\Traits\ResourceTrait;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritDoc
 * @property string uuid
 * @property string code The internal code for this resource
 * @property string name The display for this resource
 * @property string recharge_interval When the resource recharges. 0=not, 1=short rest, 2=long rest, 3=dawn
 * @property string recharge_formula The formula used to calculate how much is recharged. When null recharges all
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Resource extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__resource';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';
}
