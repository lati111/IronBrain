<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Makes an action require a resource to use
 * @inheritdoc
 * @property string action_uuid The uuid of the action this cost is for
 * @property string resource_uuid The uuid of the resource that is decreased
 * @property string cost_formula The formula used to determine the change in resources
 */
class ActionCost extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_action_cost';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the action this cost is for
     * @return BelongsTo
     */
    public function Action(): BelongsTo {
        return $this->belongsTo(Action::class, 'action_uuid', 'uuid');
    }

    /**
     * The relationship for the resource that is spent
     * @return BelongsTo
     */
    public function Resource(): BelongsTo {
        return $this->belongsTo(Resource::class, 'resource_uuid', 'uuid');
    }
}
