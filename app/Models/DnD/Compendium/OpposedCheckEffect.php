<?php

namespace App\Models\DnD\Compendium;

use App\Models\DnD\Compendium\Interfaces\DndCompendiumModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Forces an opposed check from an effect
 * @inheritdoc
 * @property string effect_uuid The uuid of the effect that forces the opposed check
 * @property string opposed_check_uuid The uuid of the opposed check that is forced
 */
class OpposedCheckEffect extends DndCompendiumModel
{
    /** @inheritdoc */
    public const string TABLE_NAME = 'dnd__compendium_opposed_check_effect';
    /** @inheritdoc */
    protected $table = self::TABLE_NAME;

    //| Relationships

    /**
     * The relationship for the effect that forces the opposed check
     * @return BelongsTo
     */
    public function Effect(): BelongsTo {
        return $this->belongsTo(Effect::class, 'effect_uuid', 'uuid');
    }

    /**
     * The relationship for the opposed check that is forced
     * @return BelongsTo
     */
    public function OpposedCheck(): BelongsTo {
        return $this->belongsTo(OpposedCheck::class, 'opposed_check_uuid', 'uuid');
    }
}
