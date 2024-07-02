<?php

namespace App\Models\Compendium;

use App\Models\AbstractModel;
use App\Models\Compendium\Traits\CompendiumTrait;
use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @inheritDoc
 * @property string uuid
 * @property string name The display for this action
 * @property int type What kind of action it is. 0=free action, 1=action, 2=bonus action, 3=reaction, 4=legendary action
 * @property string description The description for this action
 * @property string created_at The creation date of the model as a string
 * @property string updated_at The date this model was last updated
 */

class Action extends AbstractModel
{
    use HasUuids, HasTimestamps;

    /** { @inheritdoc } */
    protected $table = 'compendium__action';

    /** { @inheritdoc } */
    protected $primaryKey = 'uuid';

    /**
     * The relationship used to retrieve the action costs
     * @return HasMany The action cost relationship
     */
    public function costs(): HasMany {
        return $this->hasMany(ActionCost::class, 'action_uuid', 'uuid')
            ->jointable(Resource::getTableName(), ActionCost::getTableName(), 'resource_uuid', '=', 'uuid')
            ->select([
                sprintf('%s.action_uuid', ActionCost::getTableName()),
                sprintf('%s.resource_uuid', ActionCost::getTableName()),
                sprintf('%s.code as resource_code', Resource::getTableName()),
                sprintf('%s.code as resource_code', Resource::getTableName()),
                sprintf('%s.cost_formula', ActionCost::getTableName()),
            ]);
    }
}
