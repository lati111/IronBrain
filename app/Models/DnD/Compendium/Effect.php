<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An effect to apply, the specifics of which are held by the tables linking to it, such as damage or healing
 * @inheritdoc
 */
class Effect extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_effect';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the damage this effect applies
     * @return HasMany
     */
    public function DamageEffects(): HasMany {
        return $this->hasMany(DamageEffect::class, 'effect_uuid', 'uuid');
    }

    /**
     * The relationship for the healing this effect applies
     * @return HasMany
     */
    public function HealEffects(): HasMany {
        return $this->hasMany(HealEffect::class, 'effect_uuid', 'uuid');
    }
}
