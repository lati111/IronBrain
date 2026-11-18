<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An opposed check between an initiator and an opposer
 * @inheritdoc
 * @property string initiator_check_formula The formula used to make the check for the initiator
 * @property string opposer_check_formula The formula used to make the check for the opposer
 * @property string|null tags The tags associated with this check separated by ,
 */
class OpposedCheck extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_opposed_check';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the links to the effects that force this check
     * @return HasMany
     */
    public function Effects(): HasMany {
        return $this->hasMany(OpposedCheckEffect::class, 'opposed_check_uuid', 'uuid');
    }

    /**
     * The relationship for the effects triggered by the result of this check
     * @return HasMany
     */
    public function ResultEffects(): HasMany {
        return $this->hasMany(OpposedCheckResultEffect::class, 'opposed_check_uuid', 'uuid');
    }
}
