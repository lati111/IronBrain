<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Makes an attack from an effect
 * @inheritdoc
 * @property string effect_uuid The uuid of the effect that makes the attack
 * @property string attack_uuid The uuid of the attack that is made
 */
class AttackEffect extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_attack_effect';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the effect that makes the attack
     * @return BelongsTo
     */
    public function Effect(): BelongsTo {
        return $this->belongsTo(Effect::class, 'effect_uuid', 'uuid');
    }

    /**
     * The relationship for the attack that is made
     * @return BelongsTo
     */
    public function Attack(): BelongsTo {
        return $this->belongsTo(Attack::class, 'attack_uuid', 'uuid');
    }
}
