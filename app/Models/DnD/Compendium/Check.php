<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A check to be made, triggering effects
 * @inheritdoc
 * @property string check The formula used to make the check
 * @property string|null tags The tags associated with this check separated by ,
 */
class Check extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_check';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the links to the effects that force this check
     * @return HasMany
     */
    public function Effects(): HasMany {
        return $this->hasMany(CheckEffect::class, 'check_uuid', 'uuid');
    }

    /**
     * The relationship for the effects triggered by the result of this check
     * @return HasMany
     */
    public function ResultEffects(): HasMany {
        return $this->hasMany(CheckResultEffect::class, 'check_uuid', 'uuid');
    }
}
