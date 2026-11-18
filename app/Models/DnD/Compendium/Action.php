<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An action that can be taken by a creature
 * @inheritdoc
 * @property string name The display name for this action
 * @property int type What kind of action it is. 0=free action, 1=action, 2=bonus action, 3=reaction, 4=legendary action
 * @property string description The description for this action
 */
class Action extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_action';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'type' => 'integer',
    ];

    //| Relationships

    /**
     * The relationship for the effect links of this action
     * @return HasMany
     */
    public function Effects(): HasMany {
        return $this->hasMany(ActionEffect::class, 'action_uuid', 'uuid');
    }

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
                sprintf('%s.cost_formula', ActionCost::getTableName()),
            ]);
    }
}
