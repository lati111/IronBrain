<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A saving throw to be made, triggering effects
 * @inheritdoc
 * @property string name The displayed name for the saving throw
 * @property string dc_formula The formula to determine the DC of the saving throw
 * @property string stat The stat that should be used in the saving throw
 * @property string|null tags The tags associated with this saving throw separated by ,
 */
class SavingThrow extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_saving_throw';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the links to the effects that force this saving throw
     * @return HasMany
     */
    public function Effects(): HasMany {
        return $this->hasMany(SavingThrowEffect::class, 'saving_throw_uuid', 'uuid');
    }

    /**
     * The relationship for the effects triggered by the result of this saving throw
     * @return HasMany
     */
    public function ResultEffects(): HasMany {
        return $this->hasMany(OnSavingThrowResultEffect::class, 'saving_throw_uuid', 'uuid');
    }
}
