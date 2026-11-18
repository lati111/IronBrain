<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An action that triggers an effect
 * @inheritdoc
 * @property string action_uuid The uuid of the action that triggers the effect
 * @property string effect_uuid The uuid of the effect that is triggered
 */
class ActionEffect extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_action_effect';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the action that triggers the effect
     * @return BelongsTo
     */
    public function Action(): BelongsTo {
        return $this->belongsTo(Action::class, 'action_uuid', 'uuid');
    }

    /**
     * The relationship for the effect that is triggered
     * @return BelongsTo
     */
    public function Effect(): BelongsTo {
        return $this->belongsTo(Effect::class, 'effect_uuid', 'uuid');
    }
}
