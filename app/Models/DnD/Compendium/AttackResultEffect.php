<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An effect triggered by the result of an attack
 * @inheritdoc
 * @property string attack_uuid The uuid of the attack that triggers the effect
 * @property string effect_uuid The uuid of the effect that is triggered
 * @property int result_type The result that triggers the effect. 0=miss, 1=hit, 2=crit
 */
class AttackResultEffect extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_attack_result_effect';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    /** @inheritdoc */
    protected $casts = [
        'result_type' => 'integer',
    ];

    //| Relationships

    /**
     * The relationship for the attack that triggers the effect
     * @return BelongsTo
     */
    public function Attack(): BelongsTo {
        return $this->belongsTo(Attack::class, 'attack_uuid', 'uuid');
    }

    /**
     * The relationship for the effect that is triggered
     * @return BelongsTo
     */
    public function Effect(): BelongsTo {
        return $this->belongsTo(Effect::class, 'effect_uuid', 'uuid');
    }
}
