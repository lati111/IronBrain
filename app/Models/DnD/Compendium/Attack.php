<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An action that makes an attack roll, triggering effects on a hit
 * @inheritdoc
 * @property string name The displayed name for the attack
 * @property string formula The formula to determine the to hit of the attack
 * @property string|null tags The tags associated with this attack separated by ,
 */
class Attack extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_attack';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the links to the effects that make this attack
     * @return HasMany
     */
    public function Effects(): HasMany {
        return $this->hasMany(AttackEffect::class, 'attack_uuid', 'uuid');
    }

    /**
     * The relationship for the effects triggered by the result of this attack
     * @return HasMany
     */
    public function ResultEffects(): HasMany {
        return $this->hasMany(AttackResultEffect::class, 'attack_uuid', 'uuid');
    }
}
