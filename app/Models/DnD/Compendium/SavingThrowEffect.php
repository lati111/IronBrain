<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Forces a saving throw from an effect
 * @inheritdoc
 * @property string effect_uuid The uuid of the effect that forces the saving throw
 * @property string saving_throw_uuid The uuid of the saving throw that is forced
 */
class SavingThrowEffect extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_saving_throw_effect';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the effect that forces the saving throw
     * @return BelongsTo
     */
    public function Effect(): BelongsTo {
        return $this->belongsTo(Effect::class, 'effect_uuid', 'uuid');
    }

    /**
     * The relationship for the saving throw that is forced
     * @return BelongsTo
     */
    public function SavingThrow(): BelongsTo {
        return $this->belongsTo(SavingThrow::class, 'saving_throw_uuid', 'uuid');
    }
}
